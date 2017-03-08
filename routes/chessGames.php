<?php

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

$app->get("/chess-games", function ($request, $response, $arguments) {

    /* Check if token has needed scope. */
    if (false === $this->token->hasScope(["chess-game.all", "chess-game.list"])) {
        throw new ForbiddenException("Token not allowed to list chess games.", 403);
    }

    $games = $this->spot->mapper("App\ChessGame")
        ->all()
        ->order(["updated_at" => "DESC"]);

    /* Serialize the response data. */
    $fractal = new Manager();
    $fractal->setSerializer(new DataArraySerializer);
    $resource = new Collection($games, new ChessGameTransformer);
    $data = $fractal->createData($resource)->toArray();

    return $response->withStatus(200)
        ->withHeader("Content-Type", "application/json")
        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
});

$app->post("/chess-games", function ($request, $response, $arguments) {

    /* Check if token has needed scope. */
    if (false === $this->token->hasScope(["chess-game.all", "chess-game.create"])) {
        throw new ForbiddenException("Token not allowed to create chess games.", 403);
    }

    $body = $request->getParsedBody();


    $pdo = $this->spot->config()->defaultConnection();
    // (1) and (2) must be in same transaction to avoid race conditions
    $pdo->beginTransaction();
    $body['update_index'] = App\ActionCounter::getActionId($pdo); // (1)
    $game = new ChessGame($body);
    $this->spot->mapper("App\ChessGame")->save($game); // (2)
    $pdo->commit();


    /* Serialize the response data. */
    $fractal = new Manager();
    $fractal->setSerializer(new DataArraySerializer);
    $resource = new Item($game, new ChessGameTransformer);
    $data = $fractal->createData($resource)->toArray();
    $data["status"] = "ok";
    $data["message"] = "New chess game created";

    return $response->withStatus(201)
        ->withHeader("Content-Type", "application/json")
        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
});

$app->get("/chess-games/{uid}", function ($request, $response, $arguments) {

    /* Check if token has needed scope. */
    if (false === $this->token->hasScope(["chess-game.all", "chess-game.read"])) {
        throw new ForbiddenException("Token not allowed to list chess games.", 403);
    }

    /* Load existing chess game using provided uid */
    if (false === $game = $this->spot->mapper("App\ChessGame")->first([
        "uid" => $arguments["uid"]
    ])) {
        throw new NotFoundException("Chess game not found.", 404);
    };

    /* Serialize the response data. */
    $fractal = new Manager();
    $fractal->setSerializer(new DataArraySerializer);
    $resource = new Item($game, new ChessGameTransformer);
    $data = $fractal->createData($resource)->toArray();

    return $response->withStatus(200)
        ->withHeader("Content-Type", "application/json")
        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
});

$app->patch("/chess-games/{uid}", function ($request, $response, $arguments) {

    /* Check if token has needed scope. */
    if (false === $this->token->hasScope(["chess-game.all", "chess-game.update"])) {
        throw new ForbiddenException("Token not allowed to update chess games.", 403);
    }

    /* Load existing chess game using provided uid */
    if (false === $game = $this->spot->mapper("App\ChessGame")->first([
        "uid" => $arguments["uid"]
    ])) {
        throw new NotFoundException("Chess game not found.", 404);
    };


    $body = $request->getParsedBody();

    $pdo = $this->spot->config()->defaultConnection();
    // (1) and (2) must be in same transaction to avoid race conditions
    $pdo->beginTransaction();
    $body['update_index'] = App\ActionCounter::getActionId($pdo); // (1)
    $game->data($body);
    $this->spot->mapper("App\ChessGame")->save($game); // (2)
    $pdo->commit();


    $fractal = new Manager();
    $fractal->setSerializer(new DataArraySerializer);
    $resource = new Item($game, new ChessGameTransformer);
    $data = $fractal->createData($resource)->toArray();
    $data["status"] = "ok";
    $data["message"] = "Chess game updated";

    return $response->withStatus(200)
        ->withHeader("Content-Type", "application/json")
        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
});

$app->put("/chess-games/{uid}", function ($request, $response, $arguments) {

    /* Check if token has needed scope. */
    if (false === $this->token->hasScope(["chess-game.all", "chess-game.update"])) {
        throw new ForbiddenException("Token not allowed to update chess games.", 403);
    }

    /* Load existing chess game using provided uid */
    if (false === $game = $this->spot->mapper("App\ChessGame")->first([
        "uid" => $arguments["uid"]
    ])) {
        throw new NotFoundException("Chess game not found.", 404);
    };


    $body = $request->getParsedBody();

    $pdo = $this->spot->config()->defaultConnection();
    // (1) and (2) must be in same transaction to avoid race conditions
    $pdo->beginTransaction();
    $body['update_index'] = App\ActionCounter::getActionId($pdo); // (1)

    /* PUT request assumes full representation. If any of the properties is */
    /* missing set them to default values by clearing the chess game object first. */
    $game->clear();
    $game->data($body);
    $this->spot->mapper("App\ChessGame")->save($game); // (2)
    $pdo->commit();


    $fractal = new Manager();
    $fractal->setSerializer(new DataArraySerializer);
    $resource = new Item($game, new ChessGameTransformer);
    $data = $fractal->createData($resource)->toArray();
    $data["status"] = "ok";
    $data["message"] = "Chess game updated";

    return $response->withStatus(200)
        ->withHeader("Content-Type", "application/json")
        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
});
