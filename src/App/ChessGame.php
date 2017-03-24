<?php

namespace App;

use Spot\EntityInterface;
use Spot\MapperInterface;
use Spot\EventEmitter;

use Tuupola\Base62;

use Ramsey\Uuid\Uuid;
use Psr\Log\LogLevel;

class ChessGame extends \Spot\Entity
{
    protected static $table = "chess_games";

    public static function fields()
    {
        return [
            "id" => ["type" => "integer", "unsigned" => true, "primary" => true, "autoincrement" => true],
            "uid" => ["type" => "string", "length" => 16, "unique" => true],
            "challenger_name" => ["type" => "string", "length" => 255],
            "opponent_name" => ["type" => "string", "length" => 255],
            "challenger_uid" => ["type" => "string", "length" => 16],
            "opponent_uid" => ["type" => "string", "length" => 16],
            "accepted" => ["type" => "boolean", "value" => false],
            "created_at"   => ["type" => "datetime", "value" => new \DateTime()],
            "update_index"   => ["type" => "integer", "unsigned" => true, "value" => 0],
            "updated_at"   => ["type" => "datetime", "value" => new \DateTime()]
        ];
    }

    public static function events(EventEmitter $emitter)
    {
        $emitter->on("beforeInsert", function (EntityInterface $entity, MapperInterface $mapper) {
            $entity->uid = Base62::encode(random_bytes(9));
        });

        $emitter->on("beforeUpdate", function (EntityInterface $entity, MapperInterface $mapper) {
            $entity->updated_at = new \DateTime();
        });
    }

    public function timestamp()
    {
        return $this->updated_at->getTimestamp();
    }

    public function etag()
    {
        return md5($this->uid . $this->timestamp());
    }
}
