<?php

use Ramsey\Uuid\Uuid;
use Firebase\JWT\JWT;
use Tuupola\Base62;

// Method: POST, PUT, GET etc
// Data: array("param" => "value") ==> index.php?param=value

function callAPI($method, $url, $data = false) {
  $curl = curl_init();

  switch ($method) {
    case "POST":
        curl_setopt($curl, CURLOPT_POST, 1);
        if ($data)
          curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        break;
      case "PUT":
        curl_setopt($curl, CURLOPT_PUT, 1);
        break;
      default:
        if ($data)
          $url = sprintf("%s?%s", $url, http_build_query($data));
  }

  // Optional Authentication:
  //curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
  //curl_setopt($curl, CURLOPT_USERPWD, "username:password");

  curl_setopt($curl, CURLOPT_URL, $url);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

  $result = curl_exec($curl);

  curl_close($curl);

  return $result;
}

$app->post("/token", function ($request, $response, $arguments) {
    $body = $request->getParsedBody();

    $allowedTypes = [
      "facebook",
      "test",
      "signere",
      "google"
    ];

    $type = array_key_exists("type", $body) ? $body["type"] : false;

    // ensure we got type
    if($type == false) {
      $data = [
        "status" => "error",
        "message" => "Missing argument: type"
      ];

      return $response->withStatus(403)
          ->withHeader("Content-Type", "application/json")
          ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    // ensure type is allowed
    else if(!in_array($type,$allowedTypes)) {
      $data = [
        "status" => "error",
        "message" => "Invalid value of argument 'type'",
        "allowed_values" => $allowedTypes
      ];

      return $response->withStatus(403)
          ->withHeader("Content-Type", "application/json")
          ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    //
    else if($type == "google") {
      $googleIdToken = $body["google_id_token"];

      $googleClient = new Google_Client(["client_id" => getenv("GOOGLE_CLIENT_ID")]);
      $payload = $googleClient->verifyIdToken($googleIdToken);

      if($payload) {
        // google token has been verified
        $name = $payload["name"];
        $email = $payload["email"];

      }else{
        $data = [
          "status" => "error",
          "message" => "invalid google id token"
        ];

        return $response->withStatus(201)
            ->withHeader("Content-Type", "application/json")
            ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
      }


    }

    //
    else if($type == "signere") {
      $data = [
        "status" => "error",
        "message" => "signere authorization not supported yet"
      ];

      return $response->withStatus(201)
          ->withHeader("Content-Type", "application/json")
          ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    //
    else if($type == "facebook"){
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

        // facebook token has been verified
        $email = $res["email"];
        $name = $res["name"];
      }catch(Exception $e){
        return $response->withStatus(403)
            ->withHeader("Content-Type", "application/json")
            ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
      }
    }

    //
    else if($type == "test"){
      // if developer mode allow test users
      $email = $body["email"];
      $name = $body["name"];

      $validTestUserEmails = [
        "guldan@hotmail.com",
        "krosus@google.com",
        "elisande@amazon.com"
      ];

      $developerMode = getenv("MODE") === "DEVELOPER";
      $validTestUser = in_array($email, $validTestUserEmails);

      if(!$developerMode || !$validTestUser){

        $message = '';
        if(!$developerMode){
          $message = 'Test users not allowed, reason: Not in developer mode. ';
        }
        if(!$validTestUser){
          $message .= 'Test users not allowed, reason: Not in developer mode. ';
        }

        $data = [
          "status" => "error",
          "message" => $message
        ];

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
