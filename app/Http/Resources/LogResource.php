<?php

namespace App\Http\Resources;

use App\Player;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LogResource extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'action'            => $this->action,
            'details'           => $this->details,
            'metadata'          => $this->metadata,
            'timestamp'         => $this->timestamp,
            'server'            => $this->metadata['serverId'],
            'licenseIdentifier' => $this->identifier,
            'playerName'        => $this->player_name,
            'status'            => $this->identifier ? Player::getOnlineStatus($this->identifier, true) : false,
        ];
    }

}
