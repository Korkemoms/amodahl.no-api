<?php

use App\User;
use App\UserTransformer;

use App\ChessGame;
use App\ChessGameTransformer;

use App\ChessMove;
use App\ChessMoveTransformer;

use Exception\NotFoundException;
use Exception\ForbiddenException;
use Exception\PreconditionFailedException;
use Exception\PreconditionRequiredException;

use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use League\Fractal\Resource\Collection;
use League\Fractal\Serializer\DataArraySerializer;

/* Clients can requeste this periodically to stay updated. */
// TODO update_index should be indexed in the database to improve speed
$app->get("/updates", function ($request, $response, $arguments) {

    $email = $this["token"]->decoded->email;

    /* Check if token has needed scope. */
    if (false === $this->token->hasScope(["update.all", "update.list"])) {
        throw new ForbiddenException("Token not allowed to list updates.", 403);
    }

    $updateIndex = $request->hasHeader('update-index')
      ? $request->getHeader('update-index')[0] : -1;


    // get updated players
    if ($updateIndex !== -1) {
      $users = $this->spot->mapper("App\user")
          ->where(['update_index >' => $updateIndex])
          ->order(["update_index" => "DESC"]);
    }else{
      $users = $this->spot->mapper("App\user")
          ->all()
          ->order(["update_index" => "DESC"]);
    }
    $fractal = new Manager();
    $fractal->setSerializer(new DataArraySerializer);
    $resource = new Collection($users, new UserTransformer);
    $playerData = $fractal->createData($resource)->toArray();


    // get updated chess games
    if ($request->hasHeader('update-index')) {
      $chessGames = $this->spot->mapper("App\ChessGame")
          ->where(['update_index >' => $updateIndex])
          ->order(["update_index" => "DESC"]);
    }else{
      $chessGames = $this->spot->mapper("App\ChessGame")
          ->all()
          ->order(["update_index" => "DESC"]);
    }
    $fractal = new Manager();
    $fractal->setSerializer(new DataArraySerializer);
    $resource = new Collection($chessGames, new ChessGameTransformer);
    $chessGameData = $fractal->createData($resource)->toArray();


    // get updated chess moves
    if ($request->hasHeader('update-index')) {
      $chessMoves = $this->spot->mapper("App\ChessMove")
          ->where(['update_index >' => $updateIndex])
          ->order(["update_index" => "DESC"]);
    }else{
      $chessMoves = $this->spot->mapper("App\ChessMove")
          ->all()
          ->order(["update_index" => "DESC"]);
    }
    $fractal = new Manager();
    $fractal->setSerializer(new DataArraySerializer);
    $resource = new Collection($chessMoves, new ChessMoveTransformer);
    $chessMoveData = $fractal->createData($resource)->toArray();


    $data = [
      "players" => $playerData,
      "chessGames" => $chessGameData,
      "chessMoves" => $chessMoveData
    ];

    return $response->withStatus(200)
        ->withHeader("Content-Type", "application/json")
        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
});
