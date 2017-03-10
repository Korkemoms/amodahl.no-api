<?php

namespace App;

use Spot\EntityInterface;
use Spot\MapperInterface;
use Spot\EventEmitter;

use Tuupola\Base62;

use Ramsey\Uuid\Uuid;
use Psr\Log\LogLevel;

class ChessMove extends \Spot\Entity
{
    protected static $table = "chess_moves";

    public static function fields()
    {
        return [
            "id" => ["type" => "integer", "unsigned" => true, "primary" => true, "autoincrement" => true],
            "chess_game_id" => ["type" => "integer", "unsigned" => true],
            "player_email" => ["type" => "string", "length" => 255],
            "from_row" => ["type" => "integer", "unsigned" => true],
            "from_col" => ["type" => "integer", "unsigned" => true],
            "to_row" => ["type" => "integer", "unsigned" => true],
            "to_col" => ["type" => "integer", "unsigned" => true],
            "number" => ["type" => "integer", "unsigned" => true],
            "update_index"   => ["type" => "integer", "unsigned" => true, "value" => 0]
        ];
    }
}
