<?php

use Ramsey\Uuid\Uuid;
use Firebase\JWT\JWT;
use Tuupola\Base62;


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
    else if($type == "signere") {

      // first get access token
      $curl = curl_init();
      $fields = [
        "grant_type" => "client_credentials",
        "scope" => "root"
      ];
      $nameAndPw = getenv("SIGNERE_CLIENT_ID").":".getenv("SIGNERE_CLIENT_SECRET");

      curl_setopt($curl, CURLOPT_POST,count($fields));
      curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($fields));
      curl_setopt($curl, CURLOPT_URL, "https://oauth2test.signere.com/connect/token");
      curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
      curl_setopt($curl, CURLOPT_USERPWD, $nameAndPw);
      curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 0);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true );
      curl_setopt($curl, CURLINFO_HEADER_OUT , true);
      curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        "Content-Type: application/x-www-form-urlencoded"
      ));

      $firstResult = json_decode(curl_exec($curl));
      //$firstDebug = curl_getinfo($curl);
      curl_close($curl);
      $accessToken = $firstResult->access_token;


      // then get URL user can open in iframe to authenticate
      $curl = curl_init();
      $dataString = json_encode([
        "IdentityProvider"=> "NO_BANKID_WEB",
        "ReturnUrls" => [
          "Cancel" => "https://amodahl.no",
          "Abort" => "https://amodahl.no",
          "Error" => "https://amodahl.no",
          "Success" => "https://amodahl.no"
        ]
      ]);

      date_default_timezone_set('UTC');
      $timeStamp = str_replace("+00:00", "", date(DATE_ATOM));

      //curl_setopt($curl, CURLOPT_POST,count($fields));
      curl_setopt($curl, CURLOPT_POSTFIELDS, $dataString);
      curl_setopt($curl, CURLOPT_URL,
        "https://idtest.signere.no/api/identify/".getEnv("SIGNERE_ACCOUNT_ID"));
      curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 0);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($curl, CURLINFO_HEADER_OUT , true);

      curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($dataString),
        "API-ID: ".getenv("SIGNERE_ACCOUNT_ID"),
        "Authorization: Bearer $accessToken",
        "API-TIMESTAMP: ".$timeStamp
      ));

      $secondResult = json_decode(curl_exec($curl));
      //$secondDebug = curl_getinfo($curl);
      curl_close($curl);

      // return results to client
      $data = [
        "RequestId" => $secondResult->RequestId,
        "Url" => $secondResult->Url,
        "AccessToken" => $firstResult->access_token
      ];

      return $response->withStatus(201)
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
