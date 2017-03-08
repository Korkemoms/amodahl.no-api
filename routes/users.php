<?php

use App\User;
use App\UserTransformer;

use Exception\NotFoundException;
use Exception\ForbiddenException;
use Exception\PreconditionFailedException;
use Exception\PreconditionRequiredException;

use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use League\Fractal\Resource\Collection;
use League\Fractal\Serializer\DataArraySerializer;

$app->get("/users", function ($request, $response, $arguments) {

    /* Check if token has needed scope. */
    if (false === $this->token->hasScope(["user.all", "user.list"])) {
        throw new ForbiddenException("Token not allowed to list users.", 403);
    }

    if ($request->hasHeader('update-index')) {
      $updateIndex = $request->getHeader('update-index')[0];

      $users = $this->spot->mapper("App\user")
          ->where(['update_index >' => $updateIndex])
          ->order(["updated_at" => "DESC"]);
    }else{
      $users = $this->spot->mapper("App\user")
          ->all()
          ->order(["updated_at" => "DESC"]);
    }

    /* Serialize the response data. */
    $fractal = new Manager();
    $fractal->setSerializer(new DataArraySerializer);
    $resource = new Collection($users, new UserTransformer);
    $data = $fractal->createData($resource)->toArray();

    return $response->withStatus(200)
        ->withHeader("Content-Type", "application/json")
        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
});
