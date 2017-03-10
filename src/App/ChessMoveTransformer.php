<?php

namespace App;

use App\User;
use League\Fractal;

class ChessMoveTransformer extends Fractal\TransformerAbstract
{
  public function transform(ChessMove $chessMove)
  {
    return [
      "id" => (string)$chessMove->id ?: null,
      "chessGameId" => (string)$chessMove->chess_game_id ?: null,
      "playerEmail" => (string)$chessMove->player_email ?: null,
      "fromRow" => (string)$chessMove->from_row ?: null,
      "fromCol" => (string)$chessMove->from_col ?: null,
      "toRow" => (string)$chessMove->to_row ?: null,
      "toCol" => (string)$chessMove->to_col ?: null,
      "number" => (string)$chessMove->number ?: null,
      "updateIndex" => $chessMove->update_index ?: null
    ];
  }
}
