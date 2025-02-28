<?php

namespace App\Action\Api\v1;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

class ApiAction
{
    public function __construct()
    {
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $view = Twig::fromRequest($request);
        return $view->render($response, 'api/openapi.twig');
    }
}
