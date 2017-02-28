<?php

namespace App;

use App\User;
use League\Fractal;

class UserTransformer extends Fractal\TransformerAbstract
{

    public function transform(User $player)
    {
        return [
            "uid" => (string)$player->uid ?: null,
            "name" => (string)$player->name ?: null,
            "email" => (string)$player->email ?: null,
            "links" => [
                "self" => "/players/{$player->uid}"
            ]
        ];
    }
}
