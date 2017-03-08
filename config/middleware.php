<?php

use App\Token;

use Slim\Middleware\JwtAuthentication;
use Slim\Middleware\HttpBasicAuthentication;
use Tuupola\Middleware\Cors;
use Gofabian\Negotiation\NegotiationMiddleware;
use Micheh\Cache\CacheUtil;

use \Slim\Middleware\HttpBasicAuthentication\PdoAuthenticator;


$container = $app->getContainer();

/*$container["HttpBasicAuthentication"] = function ($container) {

    // is this in the wrong place?
    $dsn = 'mysql:dbname='.getenv("DB_NAME").';host='.getenv("DB_HOST");
    $pdo = new PDO($dsn, getenv("DB_USER"), getenv("DB_PASSWORD"));

    return new HttpBasicAuthentication([
        "path" => "/",
        "relaxed" => ["localhost"],
        "passthrough" => ["/chessapi/players/new", "/token", "/chessapi/players/verify-email", "/info"],
        "authenticator" => new PdoAuthenticator([
                "pdo" => $pdo,
                "table" => "players",
                "user" => "name",
                "hash" => "hash"
        ])
    ]);
};*/

$container["token"] = function ($container) {
    return new Token;
};

$container["JwtAuthentication"] = function ($container) {
    return new JwtAuthentication([
        "path" => "/",
        "passthrough" => ["/token", "/info"],
        "secret" => getenv("JWT_SECRET"),
        "logger" => $container["logger"],
        "relaxed" => ["localhost", "local.amodahl.no", "amodahl.no"],
        "error" => function ($request, $response, $arguments) {
            $data["status"] = "error";
            $data["message"] = $arguments["message"];
            return $response
                ->withHeader("Content-Type", "application/json")
                ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        },
        "callback" => function ($request, $response, $arguments) use ($container) {
            $container["token"]->hydrate($arguments["decoded"]);
        }
    ]);
};

$container["Cors"] = function ($container) {
    return new Cors([
        "logger" => $container["logger"],
        "origin" => ["*"],
        "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE"],
        "headers.allow" => ["Authorization", "If-Match", "If-Unmodified-Since"],
        "headers.expose" => ["Authorization", "Etag"],
        "credentials" => true,
        "cache" => 60,
        "error" => function ($request, $response, $arguments) {
            $data["status"] = "error";
            $data["message"] = $arguments["message"];
            return $response
                ->withHeader("Content-Type", "application/json")
                ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }
    ]);
};

$container["Negotiation"] = function ($container) {
    return new NegotiationMiddleware([
        "accept" => ["application/json"]
    ]);
};

//$app->add("HttpBasicAuthentication");
$app->add("JwtAuthentication");
$app->add("Cors");
$app->add("Negotiation");

$container["cache"] = function ($container) {
    return new CacheUtil;
};
