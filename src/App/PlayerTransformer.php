<?php

namespace App;

use App\Player;
use League\Fractal;

class PlayerTransformer extends Fractal\TransformerAbstract
{

    public function transform(Player $player)
    {
        return [
            "uid" => (string)$player->uid ?: null,
            "name" => (string)$player->name ?: null,
            "email" => (string)$player->email ?: null,
            "email_verified" => !!$player->email_verified,
            "links"        => [
                "self" => "/players/{$player->uid}"
            ]
        ];
    }
}
