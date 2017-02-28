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
        "app_secret" => getenv("FB_APP_SECRET"),
      ));
      $res = $facebook->get("/me?fields=name,email",
        $body["fb_access_token"])->getDecodedBody();

      $facebook_id = $res["id"];
      $email = $res["email"];
      $name = $res["name"];
    }catch(Exception $e){
      // invalid facebook token
      $data = [
        "status" => "error",
        "message" => "Could not verify facebook access token",
        "exception" => $e
      ];
      return $response->withStatus(403)
          ->withHeader("Content-Type", "application/json")
          ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }


    // if not already in db, create new user
    // TODO
    $user = [
      "name" => $name,
      "email" => $email,
      "facebook_id" => $facebook_id
    ];
    $user = new App\User($user);
    $this->spot->mapper("App\user")->save($user);


    // scopes for the token
    $requested_scopes = $body["requested_scopes"];
    $valid_scopes = [
        "user.list",
        "user.all"
    ];
    $scopes = array_filter($requested_scopes, function ($needle) use ($valid_scopes) {
        return in_array($needle, $valid_scopes);
    });


    // store and return the token
    $now = new DateTime();
    $future = new DateTime("now +1 hours");
    $server = $request->getServerParams();

    $jti = Base62::encode(random_bytes(16));

    $payload = [
        "iat" => $now->getTimeStamp(),
        "exp" => $future->getTimeStamp(),
        "jti" => $jti,
        "fb_id" => $facebook_id,
        "email" => $email,
        "scope" => $scopes
    ];

    $secret = getenv("JWT_SECRET");
    $token = JWT::encode($payload, $secret, "HS256");
    $data["status"] = "ok";
    $data["token"] = $token;

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
