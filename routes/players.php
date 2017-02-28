<?php


use App\Player;
use App\PlayerTransformer;

use Exception\NotFoundException;
use Exception\ForbiddenException;
use Exception\PreconditionFailedException;
use Exception\PreconditionRequiredException;

use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use League\Fractal\Resource\Collection;
use League\Fractal\Serializer\DataArraySerializer;

$app->get("/chessapi/players", function ($request, $response, $arguments) {

    /* Check if token has needed scope. */
    if (false === $this->token->hasScope(["player.all", "player.list"])) {
        throw new ForbiddenException("Token not allowed to list players.", 403);
    }

    /* Use ETag and date from player with most recent update. */
    $first = $this->spot->mapper("App\player")
        ->all()
        ->order(["updated_at" => "DESC"])
        ->first();

    /* Add Last-Modified and ETag headers to response when atleast on player exists. */
    if ($first) {
        $response = $this->cache->withEtag($response, $first->etag());
        $response = $this->cache->withLastModified($response, $first->timestamp());
    }

    /* If-Modified-Since and If-None-Match request header handling. */
    /* Heads up! Apache removes previously set Last-Modified header */
    /* from 304 Not Modified responses. */
    if ($this->cache->isNotModified($request, $response)) {
        return $response->withStatus(304);
    }

    $players = $this->spot->mapper("App\player")
        ->all()
        ->order(["updated_at" => "DESC"]);

    /* Serialize the response data. */
    $fractal = new Manager();
    $fractal->setSerializer(new DataArraySerializer);
    $resource = new Collection($players, new playerTransformer);
    $data = $fractal->createData($resource)->toArray();

    return $response->withStatus(200)
        ->withHeader("Content-Type", "application/json")
        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
});

$app->get("/chessapi/players/{uid}", function ($request, $response, $arguments) {

    /* Check if token has needed scope. */
    if (false === $this->token->hasScope(["player.all", "player.read"])) {
        throw new ForbiddenException("Token not allowed to list players.", 403);
    }

    /* Load existing player using provided uid */
    if (false === $player = $this->spot->mapper("App\player")->first([
        "uid" => $arguments["uid"]
    ])) {
        throw new NotFoundException("player not found.", 404);
    };

    /* Add Last-Modified and ETag headers to response. */
    $response = $this->cache->withEtag($response, $player->etag());
    $response = $this->cache->withLastModified($response, $player->timestamp());

    /* If-Modified-Since and If-None-Match request header handling. */
    /* Heads up! Apache removes previously set Last-Modified header */
    /* from 304 Not Modified responses. */
    if ($this->cache->isNotModified($request, $response)) {
        return $response->withStatus(304);
    }

    /* Serialize the response data. */
    $fractal = new Manager();
    $fractal->setSerializer(new DataArraySerializer);
    $resource = new Item($player, new playerTransformer);
    $data = $fractal->createData($resource)->toArray();

    return $response->withStatus(200)
        ->withHeader("Content-Type", "application/json")
        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
});

$app->post("/chessapi/players/new", function ($request, $response, $arguments) {

    /* Don't need token to create player */
    //if (false === $this->token->hasScope(["player.create"])) {
        //throw new ForbiddenException("Token not allowed to create players.", 403);
    //}

    $body = $request->getParsedBody();


    /* Send email with link to verify email address */
    $body["email_token"] = md5(uniqid(rand(), true));
    $emailResult = VerificationEmail::sendVerificationEmail($body["email"], $body["email_token"]);
    if($emailResult !== true){
      return $response->withStatus(403)
        ->write('Message could not be sent: ' . $emailResult);
    }


    $body["hash"] = password_hash($body["password"], PASSWORD_DEFAULT);
    unset($body["password"]);

    $player = new player($body);
    $this->spot->mapper("App\player")->save($player);

    /* Add Last-Modified and ETag headers to response. */
    $response = $this->cache->withEtag($response, $player->etag());
    $response = $this->cache->withLastModified($response, $player->timestamp());

    /* Serialize the response data. */
    $fractal = new Manager();
    $fractal->setSerializer(new DataArraySerializer);
    $resource = new Item($player, new playerTransformer);
    $data = $fractal->createData($resource)->toArray();
    $data["status"] = "ok";
    $data["message"] = "New player created";

    return $response->withStatus(201)
        ->withHeader("Content-Type", "application/json")
        ->withHeader("Location", $data["data"]["links"]["self"])
        ->write(json_encode($body, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
});

$app->patch("/chessapi/players/verify-email/{email}/{email_token}", function ($request, $response, $arguments) {

    /* Must be able to verify without token */
    //if (false === $this->token->hasScope(["player.all", "player.update"])) {
        //throw new ForbiddenException("Token not allowed to update players.", 403);
    //}

    /* Load existing player using provided email and email_token */
    if (false === $player = $this->spot->mapper("App\player")->first([
      "email" => $arguments["email"],
      "email_token" => $arguments["email_token"]
    ])) {
        throw new NotFoundException("email and token does not match any player.", 404);
    };

    $body = [];
    $body["email_verified"] = true;
    $player->data($body);
    $this->spot->mapper("App\player")->save($player);

    /* Add Last-Modified and ETag headers to response. */
    $response = $this->cache->withEtag($response, $player->etag());
    $response = $this->cache->withLastModified($response, $player->timestamp());

    $fractal = new Manager();
    $fractal->setSerializer(new DataArraySerializer);
    $resource = new Item($player, new playerTransformer);
    $data = $fractal->createData($resource)->toArray();
    $data["status"] = "ok";
    $data["message"] = "player updated";

    return $response->withStatus(200)
        ->withHeader("Content-Type", "application/json")
        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
});

$app->patch("/chessapi/players/{uid}", function ($request, $response, $arguments) {

    /* Check if token has needed scope. */
    if (false === $this->token->hasScope(["player.all", "player.update"])) {
        throw new ForbiddenException("Token not allowed to update players.", 403);
    }

    /* Load existing player using provided uid */
    if (false === $player = $this->spot->mapper("App\player")->first([
        "uid" => $arguments["uid"]
    ])) {
        throw new NotFoundException("player not found.", 404);
    };

    /* PATCH requires If-Unmodified-Since or If-Match request header to be present. */
    if (false === $this->cache->hasStateValidator($request)) {
        throw new PreconditionRequiredException("PATCH request is required to be conditional.", 428);
    }

    /* If-Unmodified-Since and If-Match request header handling. If in the meanwhile  */
    /* someone has modified the player respond with 412 Precondition Failed. */
    if (false === $this->cache->hasCurrentState($request, $player->etag(), $player->timestamp())) {
        throw new PreconditionFailedException("player has been modified.", 412);
    }

    $body = $request->getParsedBody();
    $player->data($body);
    $this->spot->mapper("App\player")->save($player);

    /* Add Last-Modified and ETag headers to response. */
    $response = $this->cache->withEtag($response, $player->etag());
    $response = $this->cache->withLastModified($response, $player->timestamp());

    $fractal = new Manager();
    $fractal->setSerializer(new DataArraySerializer);
    $resource = new Item($player, new playerTransformer);
    $data = $fractal->createData($resource)->toArray();
    $data["status"] = "ok";
    $data["message"] = "player updated";

    return $response->withStatus(200)
        ->withHeader("Content-Type", "application/json")
        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
});

$app->put("/chessapi/players/{uid}", function ($request, $response, $arguments) {

    /* Check if token has needed scope. */
    if (false === $this->token->hasScope(["player.all", "player.update"])) {
        throw new ForbiddenException("Token not allowed to update players.", 403);
    }

    /* Load existing player using provided uid */
    if (false === $player = $this->spot->mapper("App\player")->first([
        "uid" => $arguments["uid"]
    ])) {
        throw new NotFoundException("player not found.", 404);
    };

    /* PUT requires If-Unmodified-Since or If-Match request header to be present. */
    if (false === $this->cache->hasStateValidator($request)) {
        throw new PreconditionRequiredException("PUT request is required to be conditional.", 428);
    }

    /* If-Unmodified-Since and If-Match request header handling. If in the meanwhile  */
    /* someone has modified the player respond with 412 Precondition Failed. */
    if (false === $this->cache->hasCurrentState($request, $player->etag(), $player->timestamp())) {
        throw new PreconditionFailedException("player has been modified.", 412);
    }

    $body = $request->getParsedBody();

    /* PUT request assumes full representation. If any of the properties is */
    /* missing set them to default values by clearing the player object first. */
    $player->clear();
    $player->data($body);
    $this->spot->mapper("App\player")->save($player);

    /* Add Last-Modified and ETag headers to response. */
    $response = $this->cache->withEtag($response, $player->etag());
    $response = $this->cache->withLastModified($response, $player->timestamp());

    $fractal = new Manager();
    $fractal->setSerializer(new DataArraySerializer);
    $resource = new Item($player, new playerTransformer);
    $data = $fractal->createData($resource)->toArray();
    $data["status"] = "ok";
    $data["message"] = "player updated";

    return $response->withStatus(200)
        ->withHeader("Content-Type", "application/json")
        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
});

$app->delete("/chessapi/players/{uid}", function ($request, $response, $arguments) {

    /* Check if token has needed scope. */
    if (false === $this->token->hasScope(["player.all", "player.delete"])) {
        throw new ForbiddenException("Token not allowed to delete players.", 403);
    }

    /* Load existing player using provided uid */
    if (false === $player = $this->spot->mapper("App\player")->first([
        "uid" => $arguments["uid"]
    ])) {
        throw new NotFoundException("player not found.", 404);
    };

    $this->spot->mapper("App\player")->delete($player);

    $data["status"] = "ok";
    $data["message"] = "player deleted";

    return $response->withStatus(200)
        ->withHeader("Content-Type", "application/json")
        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
});
