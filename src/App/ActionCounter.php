<?php

namespace App;

use Spot\EntityInterface;
use Spot\MapperInterface;
use Spot\EventEmitter;

use Tuupola\Base62;

use Ramsey\Uuid\Uuid;
use Psr\Log\LogLevel;

class ActionCounter extends \Spot\Entity
{
    protected static $table = "counter";

    public static function getActionId($pdo) {
      $pdo->exec("DELETE FROM counter");
      $pdo->exec("INSERT INTO counter VALUES ()");
      return $pdo->lastInsertId();
    }

    public static function fields()
    {
        return [
            "id" => ["type" => "integer", "unsigned" => true, "primary" => true, "autoincrement" => true]
        ];
    }

    public static function events(EventEmitter $emitter)
    {
    }

    public function timestamp()
    {
    }

    public function etag()
    {
    }
}
