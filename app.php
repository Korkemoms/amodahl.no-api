<?php

 error_reporting(E_ALL);
 ini_set('display_errors', 'on');

date_default_timezone_set("UTC");

require __DIR__ . "/vendor/autoload.php";

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

$app = new \Slim\App([
    "settings" => [
        "displayErrorDetails" => true
    ]
]);


require __DIR__ . "/config/dependencies.php";
require __DIR__ . "/config/handlers.php";
require __DIR__ . "/config/middleware.php";

$app->get("/", function ($request, $response, $arguments) {
    print "Here be dragons";
});

require __DIR__ . "/routes/token.php";
require __DIR__ . "/routes/todos.php";
require __DIR__ . "/routes/users.php";
require __DIR__ . "/routes/chessGames.php";
require __DIR__ . "/routes/chessMoves.php";
require __DIR__ . "/routes/updates.php";

require __DIR__ . "/src/App/VerificationEmail.php";


$app->run();
