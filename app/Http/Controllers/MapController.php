<?php

namespace App\Http\Controllers;

use App\Ban;
use App\Character;
use App\Helpers\GeneralHelper;
use App\Helpers\PermissionHelper;
use App\Helpers\SessionHelper;
use App\Player;
use App\Server;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MapController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @param string $server
     * @return Response
     */
    public function index(Request $request, string $server = ''): Response
    {
        if (!PermissionHelper::hasPermission($request, PermissionHelper::PERM_LIVEMAP)) {
            abort(401);
        }

        SessionHelper::dumpSessions();

        $servers = [];

        $rawServerIps = explode(',', env('OP_FW_SERVERS', ''));

        $serverIps = [];
        foreach ($rawServerIps as $index => $rawServerIp) {
            $name = Server::getServerName($rawServerIp);

            if (!$server) {
                $server = $name;
            }

            $serverIps[] = [
                'name' => $name,
            ];

            $servers[$name] = $rawServerIp;
        }

        if (!isset($servers[$server])) {
            abort(404);
        }

        $staff = Player::query()->where(function ($q) {
            $q->orWhere('is_staff', '=', 1)
                ->orWhere('is_senior_staff', '=', 1)
                ->orWhere('is_super_admin', '=', 1)
                ->orWhereIn('license_identifier', GeneralHelper::getRootUsers());
        })->select(['license_identifier', 'player_name'])->get()->toArray();

        $marker = $request->query('m') ?? null;
        if ($marker) {
            $xy = explode(',', $marker);

            if (sizeof($xy) == 2 && is_numeric($xy[0]) && is_numeric($xy[1])) {
                $marker = [
                    floatval($xy[0]),
                    floatval($xy[1]),
                ];
            } else {
                $marker = null;
            }
        }

        return Inertia::render('Map/Index', [
            'servers'      => $serverIps,
            'activeServer' => $server,
            'staff'        => $staff ? array_map(function ($player) {
                return $player['license_identifier'];
            }, $staff) : [],
            'staffMap'     => $staff,
            'blips'        => GeneralHelper::parseMapFile(__DIR__ . '/../../../helpers/markers.map') ?? [],
            'token'        => sessionKey(),
            'cluster'      => CLUSTER,
            'myself'       => license(),
            'marker'       => $marker,
        ]);
    }

    public function playerNames(Request $request): \Illuminate\Http\Response
    {
        $licenses = $request->input('licenses') ?? [];

        if (!PermissionHelper::hasPermission($request, PermissionHelper::PERM_LIVEMAP)) {
            return self::json(false, null, 'You can not use the livemap functionality');
        }

        if (!is_array($licenses) || empty($licenses)) {
            return self::json(false, null, 'Invalid licenses');
        }

        $licenses = array_unique($licenses);

        $data = Player::query()->select(['player_name', 'license_identifier'])->whereIn('license_identifier', $licenses)->get()->toArray();
        $characters = Character::query()->select(['character_id', 'first_name', 'last_name'])->whereIn('license_identifier', $licenses)->get()->toArray();

        $map = [];
        $characterMap = [];

        foreach ($data as $player) {
            $map[$player['license_identifier']] = $player['player_name'];
        }

        foreach ($characters as $character) {
            $characterMap[$character['character_id']] = $character['first_name'] . ' ' . $character['last_name'];
        }

        return self::json(true, [
            'players'    => $map,
            'characters' => $characterMap,
        ]);
    }

    public function noclipBans(Request $request): \Illuminate\Http\Response
    {
        if (!PermissionHelper::hasPermission($request, PermissionHelper::PERM_LIVEMAP)) {
            return self::json(false, null, 'You can not use the livemap functionality');
        }

        $data = Ban::query()
            ->select(["identifier", "timestamp"])
            ->where("identifier", "LIKE", "license:%")
            ->whereIn("reason", ["MODDING-ILLEGAL_FREEZE"])
            ->where("timestamp", ">", strtotime("-10 days"))
            ->orderByDesc("timestamp")
            ->get()->toArray();

        return self::json(true, $data);
    }

}
