<?php

namespace App;

use App\User;
use League\Fractal;

class UserTransformer extends Fractal\TransformerAbstract
{
    public function transform(User $user)
    {
        return [
            "id" => (string)$user->id ?: null,
            "uid" => (string)$user->uid ?: null,
            "name" => (string)$user->name ?: null,
            "email" => (string)$user->email ?: null,
            "updateIndex" => $user->update_index ?: null,
            "updatedAt" => $user->updated_at ?: null
        ];
    }
}
