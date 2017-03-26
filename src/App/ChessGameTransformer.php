<?php

namespace App;

use App\User;
use League\Fractal;

class ChessGameTransformer extends Fractal\TransformerAbstract
{
    public function transform(ChessGame $chessGame)
    {
        return [
            "id" => (string)$chessGame->id ?: null,
            "uid" => (string)$chessGame->uid ?: null,
            "whitePlayerName" => (string)$chessGame->white_player_name ?: null,
            "blackPlayerName" => (string)$chessGame->black_player_name ?: null,
            "whitePlayerUid" => (string)$chessGame->white_player_uid ?: null,
            "blackPlayerUid" => (string)$chessGame->black_player_uid ?: null,
            "accepted" => (string)$chessGame->accepted ?: null,
            "updateIndex" => $chessGame->update_index ?: null
        ];
    }
}
