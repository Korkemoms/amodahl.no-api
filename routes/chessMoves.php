<?php

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

$app->get("/chess-moves", function ($request, $response, $arguments) {


    /* Check if token has needed scope. */
    if (false === $this->token->hasScope(["chess-move.all", "chess-move.list"])) {
        throw new ForbiddenException("Token not allowed to list chess moves.", 403);
    }

    $moves = $this->spot->mapper("App\ChessMove")
        ->all()
        ->order(["updated_at" => "DESC"]);

    /* Serialize the response data. */
    $fractal = new Manager();
    $fractal->setSerializer(new DataArraySerializer);
    $resource = new Collection($moves, new ChessMoveTransformer);
    $data = $fractal->createData($resource)->toArray();

    return $response->withStatus(200)
        ->withHeader("Content-Type", "application/json")
        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
});

$app->post("/chess-moves", function ($request, $response, $arguments) {

    /* Check if token has needed scope. */
    if (false === $this->token->hasScope(["chess-move.all", "chess-move.create"])) {
        throw new ForbiddenException("Token not allowed to create chess moves.", 403);
    }

    $body = json_decode($request->getBody(), true);
    $body["player_uid"] = $this["token"]->decoded->uid;

    // check that the move number is uniqe
    $withDuplicateNumber = $this->spot->mapper("App\ChessMove")
    ->where(["number" => $body["number"]])
    ->where(["chess_game_id" => $body["chess_game_id"]]);

    if(sizeof($withDuplicateNumber) > 0){
      throw new ForbiddenException("Duplicate move number!", 403);
    }

    $pdo = $this->spot->config()->defaultConnection();
    // (1) and (2) must be in same transaction to avoid race conditions
    $pdo->beginTransaction();
    $body['update_index'] = App\ActionCounter::getActionId($pdo); // (1)
    $move = new ChessMove($body);
    $this->spot->mapper("App\ChessMove")->save($move); // (2)
    $pdo->commit();


    /* Serialize the response data. */
    $fractal = new Manager();
    $fractal->setSerializer(new DataArraySerializer);
    $resource = new Item($move, new ChessMoveTransformer);
    $data = $fractal->createData($resource)->toArray();
    $data["status"] = "ok";
    $data["message"] = "New chess move created";

    return $response->withStatus(201)
        ->withHeader("Content-Type", "application/json")
        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
});
