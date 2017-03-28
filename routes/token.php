<?php

use Ramsey\Uuid\Uuid;
use Firebase\JWT\JWT;
use Tuupola\Base62;


function returnError($response, $status, $data, $result){
  $data["status"] = "error";
  if($result !== null && isset($result->message)){
    $data["externalMessage"] = $result->message;
  }
  if($result != null && is_array($result) && array_key_exists("message", $result)){
    $data["externalMessage"] = $result["message"];
  }

  return $response->withStatus($status)
      ->withHeader("Content-Type", "application/json")
      ->withHeader("Cache-control", "no-cache")
      ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

function returnOk($response, $status, $data){
  $data = array_merge(["status" => "ok"], $data);
  return $response->withStatus($status)
      ->withHeader("Content-Type", "application/json")
      ->withHeader("Cache-control", "no-cache")
      ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

/* Generate a JSON web token that gives acces to amodahl.no-api for some time. */
function generateToken($userUid, $scopes, $request){
  $now = new DateTime();
  $future = new DateTime("now +48 hours");
  $server = $request->getServerParams();

  $jti = Base62::encode(random_bytes(16));

  $payload = [
      "iat" => $now->getTimeStamp(),
      "exp" => $future->getTimeStamp(),
      "jti" => $jti,
      "uid" => $userUid,
      "scope" => $scopes
  ];

  $secret = getenv("JWT_SECRET");
  $token = JWT::encode($payload, $secret, "HS256");
  return $token;
}

$app->post("/token", function ($request, $response, $arguments) {
    $body = json_decode($request->getBody(), true);

    $allowedTypes = [
      "facebook",
      "test",
      "signere",
      "google"
    ];

    $type = array_key_exists("type", $body) ? $body["type"] : false;

    // ensure we got type
    if($type == false) {
      return returnError($response, 403, [
        "message" => "Missing argument: type"
      ]);
    }

    // ensure type is allowed
    else if(!in_array($type,$allowedTypes)) {
      return returnError($response, 403, [
        "message" => "Invalid value of argument 'type'",
        "allowed_values" => $allowedTypes
      ]);
    }


    else if($type == "signere") {

      if(!array_key_exists("signereAccessToken", $body)){
        // signere stage 1
        // get signere OAuth2 access token

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

        $result = json_decode(curl_exec($curl));
        curl_close($curl);

        $accessToken = isset($result->access_token) ? $result->access_token : null;
      }else{
        $accessToken = $body["signereAccessToken"];
      }

      // verify stage 1
      if(!isset($accessToken) || $accessToken == null){
        return returnError($response, 403, [
          "message" => "Missing OAuth2 access token from signere"
        ], $result);
      }


      if(!array_key_exists("signereRequestId", $body)) {
        // signere stage 2
        // get URL user can open in iframe to authenticate
        $curl = curl_init();

        $url = getenv("PAGE_URL");
        $dataString = json_encode([
          "IdentityProvider" => "NO_BANKID_WEB",
          "ReturnUrls" => [
            "Cancel" => "$url/signere-login?signereStatus=[0]",
            "Error" => "$url/signere-login?signereStatus=[0]",
            "Abort" => "$url/signere-login?signereRequestId=[1]&signereExternalId=[2]",
            "Success" => "$url/signere-login?signereRequestId=[1]&signereExternalId=[2]"
          ],
          "ExternalReference" => "TestExternalReference",
          "IFrame" => [
            "Domain" => getenv("PAGE_URL"),
            "WebMessaging" => true
          ],
          "AddonServices" => [
            "no.personal.info" => null
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
          "API-ID: " . getenv("SIGNERE_ACCOUNT_ID"),
          "API-TIMESTAMP: $timeStamp",
          "Authorization: Bearer $accessToken"
        ));

        $result = json_decode(curl_exec($curl));
        curl_close($curl);

        // verify stage 2
        if(!isset($result->Url) || $result->Url == null){
          return returnError($response, 403, [
            "message" => "Missing URL from signere"
          ], $result);
        }

        // return results to client
        $data = [
          "RequestId" => $result->RequestId,
          "Url" => $result->Url
        ];

        return returnOk($response, 201, $data);

      } else {
        // signere stage 3
        // verify request id and personal info

        $signereRequestId = $body["signereRequestId"];
        $signereAccountId = getEnv("SIGNERE_ACCOUNT_ID");
        $curl = curl_init();
        date_default_timezone_set('UTC');
        $timeStamp = str_replace("+00:00", "", date(DATE_ATOM));

        curl_setopt($curl, CURLOPT_URL,
          "https://idtest.signere.no/api/identify/"
          ."$signereAccountId?requestId=$signereRequestId");
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLINFO_HEADER_OUT , true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
          "API-ID: " . getenv("SIGNERE_ACCOUNT_ID"),
          "Authorization: Bearer $accessToken",
          "API-TIMESTAMP: $timeStamp"
        ));

        $result = json_decode(curl_exec($curl), true);
        $debug = curl_getinfo($curl);
        curl_close($curl);

        $name = $result["FirstName"]." ".$result["LastName"];
        $email = null;
        $signereUid = $result["IdentityProvider"].$result["IdentityProviderUniqueId"];

        // success
      }
    }

    else if($type == "google") {
      $googleIdToken = $body["googleIdToken"];

      $googleClient = new Google_Client(["client_id" => getenv("GOOGLE_CLIENT_ID")]);
      $payload = $googleClient->verifyIdToken($googleIdToken);

      if($payload) {
        // google token has been verified
        $name = $payload["name"];
        $email = $payload["email"];
        $signereUid = null;
      }else{
        return returnError($response, 403,[
          "status" => "error",
          "message" => "Could not log in with google (probably invalid id token)"
        ], $payload);
      }
    }

    //
    else if($type == "facebook"){
      try{
        $facebook = new Facebook\Facebook(array(
          "app_id"  => getenv("FB_APP_ID"),
          "app_secret" => getenv("FB_APP_SECRET")
        ));

        if(!array_key_exists("fbAccessToken",$body)){
          throw new Exception('No fb_access_token found in body >:(');
        }

        $res = $facebook->get("/me?fields=name,email",
          $body["fbAccessToken"])->getDecodedBody();

        // facebook token has been verified
        $email = $res["email"];
        $name = $res["name"];
        $signereUid = null;
      }catch(Exception $e){
        return returnError($response, 403,[
          "status" => "error",
          "message" => "Could not log in with facebook (probably invalid access token)"
        ], $e);
      }
    }

    //
    else if($type == "test"){
      // if developer mode allow test users
      $email = $body["email"];
      $name = $body["name"];
      $signereUid = null;

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

        return returnError($response, 403,[
          "status" => "error",
          "message" => $message
        ], $e);
      }
    }

    // if not already in db, create new user
    $pdo = $this->spot->config()->defaultConnection();
    // (1) and (2) must be in same transaction to avoid race conditions
    $pdo->beginTransaction();
    $updateIndex = App\ActionCounter::getActionId($pdo); // (1)

    // see if user is already registered
    $user = null;
    if($email != null){
      $user = $this->spot->mapper("App\User")
          ->where(["email" => $email])
          ->first();
    }else if($signereUid != null){
      $user = $this->spot->mapper("App\User")
          ->where(["signere_uid" => $signereUid])
          ->first();
    }else{
      return returnError($response, 403,[
        "status" => "error",
        "message" => "Could not get email and could not get signere uid"
      ]);
    }

    if($user == null){
      // register user
      $user = [
        "name" => $name,
        "email" => $email,
        "signere_uid" => $signereUid,
        "update_index" => $updateIndex
      ];
      $user = new App\User($user);
      $this->spot->mapper("App\User")->save($user); // (2)
      $pdo->commit();
    }

    // scopes for the token
    $requested_scopes = json_decode($body["requestedScopes"]);
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

    // return a token
    $token = generateToken($user->uid, $scopes, $request);
    $data = [
      "status" => "ok",
      "token" => $token,
      "user" => $user
    ];

    return returnOk($response, 201, $data);
});

/* This is just for debugging, not usefull in real life. */
$app->get("/dump", function ($request, $response, $arguments) {
    print_r($this->token);
});

/* This is just for debugging, not usefull in real life. */
$app->get("/info", function ($request, $response, $arguments) {
    phpinfo();
});
