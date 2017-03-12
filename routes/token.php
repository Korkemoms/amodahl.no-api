<?php

use Ramsey\Uuid\Uuid;
use Firebase\JWT\JWT;
use Tuupola\Base62;

$app->post("/token", function ($request, $response, $arguments) {
    $body = $request->getParsedBody();

    // check that user is logged in to facebook
    // and get facebook id and email to store with token

    try{
      $facebook = new Facebook\Facebook(array(
        "app_id"  => getenv("FB_APP_ID"),
        "app_secret" => getenv("FB_APP_SECRET")
      ));

      if(!array_key_exists("fb_access_token",$body)){
        throw new Exception('No fb_access_token found in body >:(');
      }

      $res = $facebook->get("/me?fields=name,email",
        $body["fb_access_token"])->getDecodedBody();

      $facebookId = $res["id"];
      $email = $res["email"];
      $name = $res["name"];
    }catch(Exception $e){
      // invalid facebook token

      // if developer mode allow test users
      $email = $body["email"];
      $validTestUserEmails = [
        "guldan@hotmail.com",
        "krosus@google.com",
        "elisande@amazon.com"
      ];
      $developerMode = getenv("MODE") === "DEVELOPER";
      $validTestUser = in_array($email, $validTestUserEmails);

      $data = [
        "status" => "error",
        "message" => "Could not verify facebook access token"
      ];

      if($developerMode && $validTestUser){
        $facebookId = $body["mock_facebook_id"];
        $name = $body["name"];

      } else {
        return $response->withStatus(403)
            ->withHeader("Content-Type", "application/json")
            ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
      }
    }

    // if not already in db, create new user
    $pdo = $this->spot->config()->defaultConnection();
    // (1) and (2) must be in same transaction to avoid race conditions
    $pdo->beginTransaction();
    $updateIndex = App\ActionCounter::getActionId($pdo); // (1)
    $user = [
      "name" => $name,
      "email" => $email,
      "facebook_id" => $facebookId,
      "update_index" => $updateIndex
    ];
    $user = new App\User($user);
    $this->spot->mapper("App\User")->save($user); // (2)
    $pdo->commit();


    // scopes for the token
    $requested_scopes = json_decode($body["requested_scopes"]);
    $valid_scopes = [
      "user.all",
      "user.list",
      "chess-game.all",
      "chess-game.list",
      "chess-game.create",
      "chess-move.all",
      "chess-move.list",
      "chess-move.create",
      "update.all",
      "update.list"
    ];
    $scopes = array_filter($requested_scopes, function ($needle) use ($valid_scopes) {
        return in_array($needle, $valid_scopes);
    });

    // create and return a token
    $now = new DateTime();
    $future = new DateTime("now +48 hours");
    $server = $request->getServerParams();

    $jti = Base62::encode(random_bytes(16));

    $payload = [
        "iat" => $now->getTimeStamp(),
        "exp" => $future->getTimeStamp(),
        "jti" => $jti,
        "fb_id" => $facebookId,
        "email" => $email,
        "scope" => $scopes
    ];

    $secret = getenv("JWT_SECRET");
    $token = JWT::encode($payload, $secret, "HS256");
    $data = [
      "status" => "ok",
      "token" => $token,
      "user" => $user
    ];

    return $response->withStatus(201)
        ->withHeader("Content-Type", "application/json")
        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
});

/* This is just for debugging, not usefull in real life. */
$app->get("/dump", function ($request, $response, $arguments) {
    print_r($this->token);
});

$app->post("/dump", function ($request, $response, $arguments) {
    print_r($this->token);
});

/* This is just for debugging, not usefull in real life. */
$app->get("/info", function ($request, $response, $arguments) {
    phpinfo();
});
