<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Users;

use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;

/**
 * SetUserLanguage
 *
 * Action for setting the language in the DBIS frontend.
 */
class SetUserLanguageAction
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $body = $request->getParsedBody();
        $language = $body['language'];
        $_SESSION["language"] = $language;

        $new_route = str_replace('lang=de', '', $_SERVER['HTTP_REFERER']); 
        $new_route = str_replace('lang=en', '', $new_route); 

        header("Location: " . $new_route);
    }
}
