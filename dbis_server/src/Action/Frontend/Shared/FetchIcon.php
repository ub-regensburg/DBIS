<?php

declare(strict_types=1);

namespace App\Action\Frontend\Shared;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * FetchIcon
 *
 * Send organization icon.
 *
 * (this may be a workaround to be solved later on)
 */
class FetchIcon
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $file = $request->getAttribute('icon');
        $path = "/var/www/public/icons/" . $file;
        if (!file_exists($path)) {
            $response->withStatus(404);
            return $response;
        }
        $splitPath = explode('.', $path);
        $extension = end($splitPath);
        header("Content-Type: image/" . $extension);
        $response->getBody()->write(file_get_contents($path));
        return $response;
    }
}
