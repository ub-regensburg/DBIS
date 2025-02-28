<?php

declare(strict_types=1);

namespace App\Action\Frontend\Test;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\Twig;

/**
 * GetTestpageAction
 *
 * Test for frontend rendering in twig
 */
class GetTestpageAction
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $view = Twig::fromRequest($request);
        return $view->render(
            $response,
            'test/test.twig'
        );
    }
}
