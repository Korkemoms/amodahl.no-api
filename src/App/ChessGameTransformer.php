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
            "challengerName" => (string)$chessGame->challenger_name ?: null,
            "opponentName" => (string)$chessGame->opponent_name ?: null,
            "challengerEmail" => (string)$chessGame->challenger_email ?: null,
            "opponentEmail" => (string)$chessGame->opponent_email ?: null,
            "accepted" => (string)$chessGame->accepted ?: null,
            "updateIndex" => $chessGame->update_index ?: null
        ];
    }
}