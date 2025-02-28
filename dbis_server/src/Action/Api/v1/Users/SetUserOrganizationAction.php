<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Users;

use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;

/**
 * SetUserOrganization
 *
 * Action for setting the organization in the DBIS frontend.
 */
class SetUserOrganizationAction
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $body = $request->getParsedBody();
        $ubrId = $body['ubrId'];
        $previousUbrId = $body['previousUbrId'];
        $url = $_SERVER['HTTP_REFERER'];

        // if the http_referrer already contains an org id, simply replace it
        if ($ubrId) {
            if ($previousUbrId) {
                $url = str_replace($previousUbrId, $ubrId, $url);
            } else {
                $parsed_url = parse_url($url);
                $url = "/" . $ubrId . $parsed_url['path'];
            }

            $_SESSION["ubrId"] = $ubrId;
        } else {
            if ($previousUbrId) {
                if ($_SERVER['QUERY_STRING']) {
                    $url = $url . "?" . $_SERVER['QUERY_STRING'];
                }

                $url = str_replace("/" . $previousUbrId, "", $url);
            }

            // If no organisation is selected, the session needs to be reset.
            unset($_SESSION['ubrId']);
        }

        header("Location: " . $url);
    }
}
