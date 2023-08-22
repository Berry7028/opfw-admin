<?php

namespace App\Http\Controllers;

use App\Ban;
use App\Helpers\GeneralHelper;
use App\Helpers\OPFWHelper;
use App\Helpers\PermissionHelper;
use App\Http\Requests\BanStoreRequest;
use App\Http\Requests\BanUpdateRequest;
use App\Http\Resources\BanResource;
use App\Http\Resources\PlayerResource;
use App\Player;
use App\Warning;
use App\PanelLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;

class PlayerBanController extends Controller
{
    /**
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        return $this->bans($request, false, false);
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function indexMine(Request $request): Response
    {
        return $this->bans($request, true, false);
    }

    /**
     * @param Request $request
     * @return Response
     */
    public function indexSystem(Request $request): Response
    {
        return $this->bans($request, false, true);
    }

    public function findUserBanHash(string $hash)
    {
        $ban = Ban::query()
            ->leftJoin('users', 'identifier', '=', 'license_identifier')
            ->where('ban_hash', $hash)
            ->whereNotNull('license_identifier')
            ->first();

        if (!$ban) {
            abort(404);
        }

        return redirect('/players/' . $ban->license_identifier);
    }

    public function banInfo(string $hash)
    {
        $ban = Ban::query()
            ->leftJoin('users', 'identifier', '=', 'license_identifier')
            ->where('ban_hash', $hash)
            ->whereNotNull('license_identifier')
            ->first();

        if (!$ban) {
            return $this->json(true, null, "Not found");
        }

        $creator = Player::query()
            ->where('license_identifier', $ban->creator_identifier)
            ->first();

        $note = $creator ? Warning::query()
            ->where('issuer_id', $creator->user_id)
            ->where('player_id', $ban->user_id)
            ->where('warning_type', Warning::TypeNote)
            ->where('can_be_deleted', '=', 1)
            ->orderBy('created_at', 'desc')
            ->first() : null;

        $data = [
            "player" => $ban->player_name,
            "creator" => $creator ? $creator->player_name : $ban->creator_name,
            "reason" => $ban->reason,
            "date" => $ban->timestamp->format('jS \\of F Y'),

            "note" => $note ? $note->message : false,

            "url" => "/players/{$ban->license_identifier}"
        ];

        return $this->json(true, $data);
    }

    private function bans(Request $request, bool $showMine, bool $showSystem): Response
    {
        $query = Player::query();

        $query->select([
            'license_identifier', 'player_name',
            'reason', 'timestamp', 'expire', 'creator_name', 'creator_identifier'
        ]);

		// Filtering by ban hash.
		if ($banHash = $request->input('banHash')) {
            if (Str::startsWith($banHash, '=')) {
                $banHash = Str::substr($banHash, 1);
                $query->where('ban_hash', $banHash);
            } else {
                $query->where('ban_hash', 'like', "%{$banHash}%");
            }
        }

		// Filtering by reason.
		if ($reason = $request->input('reason')) {
            if (Str::startsWith($reason, '=')) {
                $banHash = Str::substr($reason, 1);
                $query->where('reason', $reason);
            } else {
                $query->where('reason', 'like', "%{$reason}%");
            }
        }

		// Filtering by creator.
		if ($creator = $request->input('creator')) {
            $query->where('creator_identifier', $creator);
        }

        $query->leftJoin('user_bans', 'identifier', '=', 'license_identifier');

        if ($showMine) {
            $player = user();

            $alias = is_array($player->player_aliases) ? $player->player_aliases : json_decode($player->player_aliases, true);

            $query->where(function ($query) use ($player, $alias) {
                $query->orWhere('creator_identifier', '=', $player->license_identifier);
                $query->orWhereIn('creator_name', $alias);
            });
        }

		if ($showSystem) {
			$query->whereNull('creator_name');
		} else {
			$query->whereNotNull('creator_name');
		}

        $query
            ->whereNotNull('reason')
            ->orderByDesc('timestamp');

        $page = Paginator::resolveCurrentPage('page');
        $query->limit(15)->offset(($page - 1) * 15);

        $players = $query->get();

        $staff = GeneralHelper::getAllStaff();

        return Inertia::render('Players/Bans', [
            'players' => $players->toArray(),
            'staff' => $staff,
            'links' => $this->getPageUrls($page),
            'page' => $page,
			'filters' => [
                'banHash' => $request->input('banHash'),
                'reason' => $request->input('reason'),
                'creator' => $request->input('creator') ?: "",
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Player $player
     * @param BanStoreRequest $request
     * @return RedirectResponse
     */
    public function store(Player $player, BanStoreRequest $request): RedirectResponse
    {
        if ($player->isBanned()) {
            return backWith('error', 'Player is already banned');
        }

        // Create a unique hash to go with this player's batch of bans.
        $user = user();
        $hash = Ban::generateHash();

        // Create ban.
        $ban = array_merge([
            'ban_hash' => $hash,
            'creator_name' => $user->player_name,
            'creator_identifier' => $user->license_identifier,
        ], $request->validated());

        // Get identifiers to ban.
        $identifiers = $player->getBannableIdentifiers();

        // Go through the player's identifiers and create a ban record for each of them.
        foreach ($identifiers as $identifier) {
            $b = $ban;
            $b['identifier'] = $identifier;

            $player->bans()->updateOrCreate($b);
        }

        // Create reason.
        $reason = $request->input('reason')
            ? 'I banned this person with the reason: `' . $request->input('reason') . '`'
            : 'I banned this person without a reason';

        $reason .= ($ban['expire'] ? ' for ' . $this->formatSeconds(intval($ban['expire'])) : ' indefinitely') . '.';

        // Automatically log the ban as a warning.
        $player->warnings()->create([
            'issuer_id' => $user->user_id,
            'message' => $reason . ' This warning was generated automatically as a result of banning someone.',
            'can_be_deleted' => 0,
        ]);

        $staffName = $user->player_name;

        if (env('HIDE_BAN_CREATOR')) {
            $staffName = "a staff member";
        }

        $kickReason = $request->input('reason')
            ? 'You have been banned by ' . $staffName . ' for reason `' . $request->input('reason') . '`.'
            : 'You have been banned without a specified reason by ' . $staffName;

        OPFWHelper::kickPlayer($user->license_identifier, $user->player_name, $player, $kickReason);

        return backWith('success', 'The player has successfully been banned.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Player $player
     * @param Ban $ban
     * @param Request $request
     * @return RedirectResponse
     */
    public function destroy(Player $player, Ban $ban, Request $request): RedirectResponse
    {
        if ($ban->locked && !PermissionHelper::hasPermission($request, PermissionHelper::PERM_LOCK_BAN)) {
            abort(401);
        }

        $user = user();

        // Delete ban
        Ban::query()
            ->where('ban_hash', $ban->ban_hash)
            ->whereNotNull('ban_hash')
            ->delete();

        // Delete linked bans
        Ban::query()
            ->where('smurf_account', $ban->ban_hash)
            ->whereNotNull('smurf_account')
            ->delete();

		if (!$ban->creator_name) {
			PanelLog::logSystemBanRemove($user->license_identifier, $player->license_identifier);
		}

        // Automatically log the ban update as a warning.
        $player->warnings()->create([
            'issuer_id' => $user->user_id,
            'message' => 'I removed this players ban.',
        ]);

        return backWith('success', 'The player has successfully been unbanned.');
    }

    public function lockBan(Player $player, Ban $ban, Request $request): RedirectResponse
    {
        if (!PermissionHelper::hasPermission($request, PermissionHelper::PERM_LOCK_BAN)) {
            abort(401);
        }

        $ban->update([
            'locked' => 1
        ]);

        return backWith('success', 'The ban has been successfully locked.');
    }

    public function unlockBan(Player $player, Ban $ban, Request $request): RedirectResponse
    {
        if (!PermissionHelper::hasPermission($request, PermissionHelper::PERM_LOCK_BAN)) {
            abort(401);
        }

        $ban->update([
            'locked' => 0
        ]);

        return backWith('success', 'The ban has been successfully unlocked.');
    }

    public function unlinkHWID(Player $player, Player $player2, Request $request): RedirectResponse
    {
        if (!$this->isSuperAdmin($request)) {
            abort(401);
        }

        $tokens = $player->getTokens();
        $tokens2 = $player2->getTokens();

        if (empty(array_intersect($tokens, $tokens2))) {
            return backWith('error', 'Players are not linked.');
        }

        $newTokens = array_values(array_diff($tokens, $tokens2));
        $newTokens2 = array_values(array_diff($tokens2, $tokens));

        $player->update([
            'player_tokens' => $newTokens
        ]);

        $player2->update([
            'player_tokens' => $newTokens2
        ]);

        return backWith('success', 'The players have been successfully unlinked.');
    }

    public function unlinkIdentifiers(Player $player, Player $player2, Request $request): RedirectResponse
    {
        if (!$this->isSuperAdmin($request)) {
            abort(401);
        }

        $identifiers = $player->getIdentifiers();
        $identifiers2 = $player2->getIdentifiers();

        if (empty(array_intersect($identifiers, $identifiers2))) {
            return backWith('error', 'Players are not linked.');
        }

        $lastUsed = $player->getLastUsedIdentifiers();
        $lastUsed2 = $player2->getLastUsedIdentifiers();

        $newIdentifiers = [];

        foreach($identifiers as $identifier) {
            if (in_array($identifier, $lastUsed) || !in_array($identifier, $identifiers2)) {
                $newIdentifiers[] = $identifier;
            }
        }

        $newIdentifiers2 = [];

        foreach($identifiers2 as $identifier) {
            if (in_array($identifier, $lastUsed2) || !in_array($identifier, $identifiers)) {
                $newIdentifiers2[] = $identifier;
            }
        }

        // Check if still linked
        if (!empty(array_intersect($newIdentifiers, $newIdentifiers2))) {
            return backWith('error', 'Unable to unlink players.');
        }

        $player->update([
            'identifiers' => $newIdentifiers
        ]);

        $player2->update([
            'identifiers' => $newIdentifiers2
        ]);

        return backWith('success', 'The players have been successfully unlinked.');
    }

    /**
     * Display the specified resource for editing.
     *
     * @param Request $request
     * @param Player $player
     * @param Ban $ban
     * @return Response
     */
    public function edit(Request $request, Player $player, Ban $ban): Response
    {
        if ($ban->locked && !PermissionHelper::hasPermission($request, PermissionHelper::PERM_LOCK_BAN)) {
            abort(401);
        }

        return Inertia::render('Players/Ban/Edit', [
            'player' => new PlayerResource($player),
            'ban' => new BanResource($ban),
        ]);
    }

    /**
     * Updates the specified resource.
     *
     * @param Player $player
     * @param Ban $ban
     * @param BanUpdateRequest $request
     * @return RedirectResponse
     */
    public function update(Player $player, Ban $ban, BanUpdateRequest $request): RedirectResponse
    {
        if ($ban->locked && !PermissionHelper::hasPermission($request, PermissionHelper::PERM_LOCK_BAN)) {
            abort(401);
        }

        $user = user();
        $reason = $request->input('reason') ?: 'No reason.';

        $expireBefore = $ban->getExpireTimeInSeconds() ? $this->formatSeconds($ban->getExpireTimeInSeconds()) : 'permanent';
        $expireAfter = $request->input('expire') ? $this->formatSeconds(intval($request->input('expire')) + (time() - $ban->getTimestamp())) : 'permanent';

		$before = $ban->getExpireTimeInSeconds() || null;
		$after = $request->input('expire') ? intval($request->input('expire')) + (time() - $ban->getTimestamp()) : null;

        $message = '';

        if ($before === $after && $reason === $ban->reason) {
            return backWith('error', 'You did not change anything!');
        } else if ($before === $after) {
            $message = 'I changed this bans reason to be "' . $reason . '". ';
        } else if ($reason === $ban->reason) {
            $message = 'I updated this ban to be "' . $expireAfter . '" instead of "' . $expireBefore . '". ';
        } else {
            $message = 'I updated this ban to be "' . $expireAfter . '" instead of "' . $expireBefore . '" and changed the reason to "' . $reason . '". ';
        }

        $bans = Ban::query()->where('ban_hash', '=', $ban->ban_hash)->get();
        foreach ($bans->values() as $b) {
            $b->update($request->validated());
        }

        // Automatically log the ban update as a warning.
        $player->warnings()->create([
            'issuer_id' => $user->user_id,
            'message' => $message .
                'This warning was generated automatically as a result of updating a ban.',
        ]);

        return backWith('success', 'Ban was successfully updated, redirecting back to player page...');
    }

    public function smurfBan(Request $request, string $hash): RedirectResponse
	{
		if (!$hash) {
			abort(404);
		}

		$ban = Ban::query()->where('ban_hash', '=', $hash)->whereRaw("SUBSTRING_INDEX(identifier, ':', 1) = 'license'")->first();

		if (!$ban) {
			abort(404);
		}

		$license = $ban->identifier;

		return redirect("/players/{$license}");
	}

    public function linkedIPs(Request $request, string $license): \Illuminate\Http\Response
    {
        $player = $this->findPlayer($request, $license);

        if (!$player) {
			return $this->text(404, "Player not found.");
		}

		$ips = $player->getIps();

        if (empty($ips)) {
            return $this->text(404, "No ips found.");
        }

		$ips = array_filter($ips, function($ip) {
			$info = GeneralHelper::ipInfo($ip);

			if ($info) {
				if (in_array($info['isp'], ['OVH SAS'])) {
					return false;
				}

				if ($info['proxy']) {
					return false;
				}
			}

			return true;
		});

		$where = implode(' OR ', array_map(function($ip) {
			return 'JSON_CONTAINS(ips, \'"' . $ip . '"\', \'$\')';
		}, $ips));

		return $this->drawLinked($player, $where);
    }

    public function linkedTokens(Request $request, string $license): \Illuminate\Http\Response
    {
        $player = $this->findPlayer($request, $license);

        if (!$player) {
			return $this->text(404, "Player not found.");
		}

		$tokens = $player->getTokens();

        if (empty($tokens)) {
            return $this->text(404, "No tokens found.");
        }

		$where = "JSON_OVERLAPS(player_tokens, '" . json_encode($player->getTokens()) . "') = 1";

		return $this->drawLinked($player, $where);
    }

    public function linkedIdentifiers(Request $request, string $license): \Illuminate\Http\Response
    {
        $player = $this->findPlayer($request, $license);

        if (!$player) {
			return $this->text(404, "Player not found.");
		}

		$identifiers = $player->getBannableIdentifiers();

        if (empty($identifiers)) {
            return $this->text(404, "No identifiers found.");
        }

		$where = "JSON_OVERLAPS(identifiers, '" . json_encode($player->getBannableIdentifiers()) . "') = 1";

		return $this->drawLinked($player, $where);
    }

    public function linkedPrint(Request $request, string $license): \Illuminate\Http\Response
    {
        $player = $this->findPlayer($request, $license);

        if (!$player) {
			return $this->text(404, "Player not found.");
		}

		$fingerprint = $player->getFingerprint();

        if (!$fingerprint) {
            return $this->text(404, "No fingerprint found.");
        }

		$where = "JSON_EXTRACT(user_variables, '$.ofFingerprint') = '" . $fingerprint . "'";

		return $this->drawLinked($player, $where);
    }

	protected function findPlayer(Request $request, string $license)
	{
		if (!PermissionHelper::hasPermission($request, PermissionHelper::PERM_LINKED)) {
            abort(401);
        }

        if (!$license || !Str::startsWith($license, 'license:')) {
            return false;
        }

        $player = Player::query()->select(['player_name', 'license_identifier', 'player_tokens', 'ips', 'identifiers', 'user_variables'])->where('license_identifier', '=', $license)->get()->first();

        if (!$player) {
			return false;
        }

		return $player;
	}

	protected function drawLinked(Player $player, string $where)
	{
		$license = $player->license_identifier;

		$tokens = $player->getTokens();
		$ips = $player->getIps();
		$identifiers = $player->getBannableIdentifiers();
		$fingerprint = $player->getFingerprint();

		$players = Player::query()->select(['player_name', 'license_identifier', 'player_tokens', 'ips', 'identifiers', 'user_variables', 'last_connection', 'ban_hash', 'playtime'])->leftJoin('user_bans', function($join) {
			$join->on(DB::raw("JSON_CONTAINS(identifiers, JSON_QUOTE(identifier), '$')"), '=', DB::raw('1'));
		})->whereRaw($where)->groupBy('license_identifier')->get();

        $raw = [];

        foreach ($players as $found) {
            if ($found->license_identifier !== $license) {
                $foundTokens = $found->getTokens();
				$foundIps = $found->getIps();
				$foundIdentifiers = $found->getBannableIdentifiers();
				$foundFingerprint = $found->getFingerprint();

				$count = sizeof(array_intersect($tokens, $foundTokens));
				$countIps = sizeof(array_intersect($ips, $foundIps));
				$countIdentifiers = sizeof(array_intersect($identifiers, $foundIdentifiers));
				$countFingerprint = $fingerprint && $foundFingerprint && $fingerprint === $foundFingerprint ? 1 : 0;

				$total = $count + $countIps + $countIdentifiers + $countFingerprint;

				$counts = '<span style="color:#ff5b5b">' . $count . '</span>/<span style="color:#5bc2ff">' . $countIps . '</span>/<span style="color:#65d54e">' . $countIdentifiers . '</span>/<span style="color:#f0c622">' . $countFingerprint . '</span>';

				$playtime = "Playtime is about " . $this->formatSeconds($found->playtime);

                $raw[] = [
					'label' => '[' . $counts . '] - ' . $this->formatTimestamp($found->last_connection) . ' - <a href="/players/' . $found->license_identifier . '" target="_blank" title="' . $playtime . '">' . $found->player_name . '</a>',
					'connection' => $found->last_connection,
					'count' => $total,
					'banned' => $found->ban_hash !== null
				];
            }
        }

		usort($raw, function($a, $b) {
			if ($a['connection'] === $b['connection']) {
				return $a['count'] < $b['count'];
			}

			return $a['connection'] < $b['connection'];
		});

		$linked = [];
		$banned = [];

		foreach ($raw as $item) {
			if ($item['banned']) {
				$banned[] = $item['label'];
			} else {
				$linked[] = $item['label'];
			}
		}

		if (empty($linked)) {
			$linked[] = "<i>None</i>";
		}

		if (empty($banned)) {
			$banned[] = "<i>None</i>";
		}

		$counts = '<span style="color:#ff5b5b">Tokens</span> / <span style="color:#5bc2ff">IPs</span> / <span style="color:#65d54e">Identifiers</span> / <span style="color:#f0c622">Fingerprint</span>';

		$print = $fingerprint ? " <span style='color:#a0bcff'>{<i>" . $fingerprint . "</i>}</span>" : "";

        return $this->fakeText(200, "Found: <b>" . sizeof($raw) . "</b> Accounts for <a href='/players/" . $license . "' target='_blank'>" . $player->player_name . "</a>" . $print . "\n\n<i style='color:#c68dbf'>[" . $counts . "] - Last Connection - Player Name</i>\n\n<i style='color:#a3ff9b'>- Not Banned</i>\n" . implode("\n", $linked) . "\n\n<i style='color:#ff8e8e'>- Banned</i>\n" . implode("\n", $banned));
	}
}
