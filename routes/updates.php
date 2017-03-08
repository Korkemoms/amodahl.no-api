<?php

use App\User;
use App\UserTransformer;

use App\ChessGame;
use App\ChessGameTransformer;

use Exception\NotFoundException;
use Exception\ForbiddenException;
use Exception\PreconditionFailedException;
use Exception\PreconditionRequiredException;

use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use League\Fractal\Resource\Collection;
use League\Fractal\Serializer\DataArraySerializer;

$app->get("/updates", function ($request, $response, $arguments) {

    $email = $this["token"]->decoded->email;
    
    /* Check if token has needed scope. */
    if (false === $this->token->hasScope(["update.all", "update.list"])) {
        throw new ForbiddenException("Token not allowed to list updates.", 403);
    }

    // get updated players
    if ($request->hasHeader('update-index')) {
      $updateIndex = $request->getHeader('update-index')[0];
      $users = $this->spot->mapper("App\user")
          ->where(['update_index >' => $updateIndex])
          ->order(["updated_at" => "DESC"]);
    }else{
      $users = $this->spot->mapper("App\user")
          ->all()
          ->order(["updated_at" => "DESC"]);
    }
    $fractal = new Manager();
    $fractal->setSerializer(new DataArraySerializer);
    $resource = new Collection($users, new UserTransformer);
    $playerData = $fractal->createData($resource)->toArray();


    // get updated chess games
    if ($request->hasHeader('update-index')) {
      $updateIndex = $request->getHeader('update-index')[0];
      $chessGames = $this->spot->mapper("App\ChessGame")
          ->where(['update_index >' => $updateIndex])
          ->order(["updated_at" => "DESC"]);
    }else{
      $chessGames = $this->spot->mapper("App\ChessGame")
          ->all()
          ->order(["updated_at" => "DESC"]);
    }
    $fractal = new Manager();
    $fractal->setSerializer(new DataArraySerializer);
    $resource = new Collection($chessGames, new ChessGameTransformer);
    $chessGameData = $fractal->createData($resource)->toArray();

    $data = [
      "players" => $playerData,
      "chessGames" => $chessGameData
    ];

    return $response->withStatus(200)
        ->withHeader("Content-Type", "application/json")
        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
});
