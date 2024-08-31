<?php

namespace App;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use kanalumaddela\LaravelSteamLogin\SteamUser;
use SteamID;

/**
 * @package App
 */
class PlayerStatus
{
    const STATUS_UNAVAILABLE = 'unavailable';
    const STATUS_OFFLINE     = 'offline';
    const STATUS_ONLINE      = 'online';

    /**
     * Connection Status
     *
     * @var string
     */
    public string $status = self::STATUS_UNAVAILABLE;

    /**
     * The ID in server (the one you see when pressing U)
     *
     * @var int
     */
    public int $serverId = 0;

    /**
     * The loaded character id if they are loaded into one
     *
     * @var int|null
     */
    public ?int $character = 0;

    /**
     * The ip of the server the player is in
     *
     * @var string
     */
    public string $serverIp;

    /**
     * The subdomain of the server (c3s1)
     *
     * @var string
     */
    public string $serverName = '';

    /**
     * The metadata of the character
     *
     * @var array
     */
    public array $characterMetadata = [];

    public function __construct(string $status, string $serverIp, int $serverId, ?int $character = null, ?array $characterMetadata = null)
    {
        $this->status = $status;
        $this->serverIp = $serverIp ? Server::fixApiUrl($serverIp) : "";
        $this->serverId = $serverId;
        $this->character = $character;

        $this->characterMetadata = $characterMetadata ?? [];

        $this->serverName = Server::getServerName($serverIp);
    }

    public function isOnline(): bool
    {
        return $this->status === self::STATUS_ONLINE;
    }

    public function isInShell(): bool
    {
        return $this->isOnline() && $this->characterMetadata && in_array('in_shell', $this->characterMetadata);
    }
}
