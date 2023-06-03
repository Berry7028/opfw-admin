<?php

namespace App\Http\Controllers;

use App\Helpers\CacheHelper;
use App\Helpers\GeneralHelper;
use App\Helpers\PermissionHelper;
use App\Http\Resources\LogResource;
use App\Log;
use App\Player;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use WhichBrowser\Cache;

class LogController extends Controller
{
    const DRUG_LOGS = [
        "Gun Run",
		"Gun Run Drop",
        "Cocaine Run",
        "Oxy Run Started",
        "Oxy Run Ended",
        "Oxy Run Failed",
        "Jim's Gun Shop",
    ];

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $start = round(microtime(true) * 1000);

		$logs = [];
		$canSearchDrugs = true;
		$page = 1;

		if (env('RESTRICT_DRUG_LOGS', false)) {
			$player = $request->user()->player;

			if ((!isset($player->panel_drug_department) || !$player->panel_drug_department) && !GeneralHelper::isUserRoot($player->license_identifier)) {
				$canSearchDrugs = false;
			}
		}

		if (!$request->query('empty')) {
			$query = Log::query()->orderByDesc('timestamp');

			if (!$canSearchDrugs) {
				$query->whereNotIn('action', self::DRUG_LOGS);
			}

			// Filtering by identifier.
			if ($identifier = $this->multiValues($request->input('identifier'))) {
				/**
				 * @var $q Builder
				 */
				$query->where(function ($q) use ($identifier) {
					foreach ($identifier as $i) {
						$q->orWhere('identifier', $i);
					}
				});
			}

			// Filtering by before.
			if ($before = $request->input('before')) {
				$query->where(DB::raw('UNIX_TIMESTAMP(`timestamp`)'), '<', $before);
			}

			// Filtering by after.
			if ($after = $request->input('after')) {
				$query->where(DB::raw('UNIX_TIMESTAMP(`timestamp`)'), '>', $after);
			}

			// Filtering by server.
			if ($server = $this->multiValues($request->input('server'))) {
				/**
				 * @var $q Builder
				 */
				$query->where(function ($q) use ($server) {
					foreach ($server as $s) {
						$q->orWhere('details', 'LIKE', '% [' . intval($s) . '] %');
					}
				});
			}

			// Filtering by action.
			if ($action = $this->multiValues($request->input('action'))) {
				/**
				 * @var $q Builder
				 */
				$query->where(function ($q) use ($action) {
					foreach ($action as $a) {
						if (Str::startsWith($a, '=')) {
							$a = Str::substr($a, 1);
							$q->orWhere('action', $a);
						} else {
							$q->orWhere('action', 'like', "%{$a}%");
						}
					}
				});
			}

			// Filtering by details.
			if ($details = $request->input('details')) {
				if (Str::startsWith($details, '=')) {
					$details = Str::substr($details, 1);
					$query->where('details', $details);
				} else {
					$query->where('details', 'like', "%{$details}%");
				}
			}

			$actionInput = $request->input('action');
			$detailsInput = $request->input('details');
			$identifierInput = $request->input('identifier');
			$serverInput = $request->input('server');

			$action = $actionInput ? trim($actionInput) : null;
			$details = $detailsInput ? trim($detailsInput) : null;
			$identifier = $identifierInput ? trim($identifierInput) : null;
			$server = $serverInput ? trim($serverInput) : null;

			$page = Paginator::resolveCurrentPage('page');

			if ($action || $details || $identifier || $server) {
				DB::table('panel_log_searches')
					->insert([
						'action' => $action,
						'details' => $details,
						'identifier' => $identifier,
						'server' => $server,
						'page' => $page,
						'license_identifier' => $request->user()->player->license_identifier,
						'timestamp' => time()
					]);

				DB::table('panel_log_searches')
					->where('timestamp', '<', time() - CacheHelper::YEAR)
					->delete();
			}

			$query->select(['id', 'identifier', 'action', 'details', 'metadata', 'timestamp']);
			$query->limit(15)->offset(($page - 1) * 15);

			$logs = $query->get();
		}

		$logs = LogResource::collection($logs);

        $end = round(microtime(true) * 1000);

        return Inertia::render('Logs/Index', [
            'logs' => $logs,
            'filters' => $request->all(
                'identifier',
                'server',
                'action',
                'details',
                'after',
                'before'
            ),
            'links' => $this->getPageUrls($page),
            'time' => $end - $start,
            'playerMap' => Player::fetchLicensePlayerNameMap($logs->toArray($request), 'licenseIdentifier'),
            'page' => $page,
            'drugActions' => self::DRUG_LOGS,
            'canSearchDrugs' => $canSearchDrugs,
            'actions' => CacheHelper::getLogActions()
        ]);
    }

    /**
     * Display a phone message logs.
     *
     * @param Request $request
     * @return Response
     */
    public function phoneLogs(Request $request): Response
    {
        if (!$this->isSuperAdmin($request)) {
            abort(403);
        }

        $start = round(microtime(true) * 1000);

        $query = DB::table("phone_message_logs")->select()->orderByDesc('timestamp');

        // Filtering by number.
        if ($number = $this->multiValues($request->input('number'))) {
            /**
             * @var $q Builder
             */
            $query->where(function ($q) use ($number) {
                foreach ($number as $i) {
                    $q->orWhere('sender_number', $i);
                    $q->orWhere('receiver_number', $i);
                }
            });
        }

        // Filtering by message.
        if ($message = $request->input('message')) {
            if (Str::startsWith($message, '=')) {
                $message = Str::substr($message, 1);
                $query->where('message', $message);
            } else {
                $query->where('message', 'like', "%{$message}%");
            }
        }

        $page = Paginator::resolveCurrentPage('page');

        $query->limit(15)->offset(($page - 1) * 15);

        $logs = $query->get()->toArray();

        $end = round(microtime(true) * 1000);

        return Inertia::render('Logs/Phone', [
            'logs' => $logs,
            'filters' => $request->all(
                'number',
                'message'
            ),
            'links' => $this->getPageUrls($page),
            'time' => $end - $start,
            'page' => $page
        ]);
    }

    public function searches(Request $request): Response
    {
        if (!PermissionHelper::hasPermission($request, PermissionHelper::PERM_ADVANCED)) {
            abort(401);
        }

        $query = DB::table('panel_log_searches')->orderByDesc('timestamp')->select();

        // Filtering by identifier.
        if ($identifier = $this->multiValues($request->input('identifier'))) {
            /**
             * @var $q Builder
             */
            $query->where(function ($q) use ($identifier) {
                foreach ($identifier as $i) {
                    $q->orWhere('license_identifier', $i);
                }
            });
        }

        // Filtering by search query.
        if ($details = $this->multiValues($request->input('details'))) {
            /**
             * @var $q Builder
             */
            $query->where(function ($q) use ($details) {
                foreach ($details as $a) {
                    if (Str::startsWith($a, '=')) {
                        $a = Str::substr($a, 1);
                        $q->orWhere('action', $a);
                        $q->orWhere('details', $a);
                        $q->orWhere('identifier', $a);
                    } else {
                        $q->orWhere('action', 'like', "%{$a}%");
                        $q->orWhere('details', 'like', "%{$a}%");
                        $q->orWhere('identifier', 'like', "%{$a}%");
                    }
                }
            });
        }

        // Filtering by before.
        if ($before = $request->input('before')) {
            $query->where('timestamp', '<', $before);
        }

        // Filtering by after.
        if ($after = $request->input('after')) {
            $query->where('timestamp', '>', $after);
        }

        $page = Paginator::resolveCurrentPage('page');

        $query->limit(15)->offset(($page - 1) * 15);

        $logs = $query->get();

        return Inertia::render('Logs/Searches', [
            'logs' => $logs,
            'filters' => $request->all(
                'identifier',
				'details',
				'after',
				'before'
            ),
            'links' => $this->getPageUrls($page),
            'playerMap' => Player::fetchLicensePlayerNameMap($logs->toArray($request), 'license_identifier'),
            'page' => $page,
        ]);
    }

    public function screenshotLogs(Request $request): Response
    {
        if (!PermissionHelper::hasPermission($request, PermissionHelper::PERM_ADVANCED)) {
            abort(401);
        }

        $query = DB::table('panel_screenshot_logs')->orderByDesc('timestamp')->select();

        // Filtering by identifier.
        if ($identifier = $this->multiValues($request->input('identifier'))) {
            /**
             * @var $q Builder
             */
            $query->where(function ($q) use ($identifier) {
                foreach ($identifier as $i) {
                    $q->orWhere('source_license', $i);
                }
            });
        }

        // Filtering by character.
        if ($character = $this->multiValues($request->input('character'))) {
            /**
             * @var $q Builder
             */
            $query->where(function ($q) use ($character) {
                foreach ($character as $i) {
                    $q->orWhere('target_character', $i);
                }
            });
        }

        // Filtering by before.
        if ($before = $request->input('before')) {
            $query->where('timestamp', '<', $before);
        }

        // Filtering by after.
        if ($after = $request->input('after')) {
            $query->where('timestamp', '>', $after);
        }

        $page = Paginator::resolveCurrentPage('page');

        $logs = $query->get()->toArray();

        $groupedLogs = [];

        foreach ($logs as $log) {
            $entry = [
                "url" => $log->url,
                "timestamp" => $log->timestamp,
                "type" => $log->type
            ];

            $foundEntry = false;

            foreach($groupedLogs as &$groupedLog) {
                if ($groupedLog['source_license'] !== $log->source_license || $groupedLog['target_license'] !== $log->target_license || $groupedLog['target_character'] !== $log->target_character) {
                    continue;
                }

                $diff = $log->timestamp - $groupedLog['till'];

                if ($diff > 10*60) {
                    continue;
                }

                $foundEntry = true;

                $groupedLog['till'] = $log->timestamp;

                $groupedLog['entries'][] = $entry;
            }

            if ($foundEntry) {
                continue;
            }

            $groupedLogs[] = $entry;
        }

        $paginated = array_slice($groupedLogs, ($page - 1) * 15, 15);

        return Inertia::render('Logs/Screenshots', [
            'logs' => $paginated,
            'filters' => $request->all(
                'identifier',
				'character',
				'after',
				'before'
            ),
            'links' => $this->getPageUrls($page),
            'playerMap' => Player::fetchLicensePlayerNameMap($logs, ['source_license', 'target_license']),
            'page' => $page,
            'maxPage' => ceil(sizeof($groupedLogs) / 15)
        ]);
    }

    private function multiValues(?string $val): ?array
    {
        if (!$val) {
            return null;
        }

        return array_values(array_map(function ($v) {
            return trim($v);
        }, explode(',', $val)));
    }



}
