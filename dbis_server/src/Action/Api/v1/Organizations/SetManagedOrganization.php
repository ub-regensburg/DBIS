<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Organizations;

use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use App\Action\Api\v1\Organizations\OrganizationsBaseAction;
use App\Domain\Organizations\Exceptions\OrganizationWithUbrIdNotExistingException;

/**
 * SetManagedOrganization
 *
 */
class SetManagedOrganization extends OrganizationsBaseAction
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $ubrId = $request->getAttribute("ubrId");

        if (!$this->authService->isSessionAuthenticated()) {
            // not logged in
            $response->withStatus(401);
            return $response;
        } elseif (!($this->authService->hasAuthenticatedUserRole("superadmin") ||
                $this->authService->getAuthenticatedUser()->isAdminFor($ubrId))
        ) {
            // logged in, but wrong user role
            $response->withStatus(403);
            return $response;
        }

        if (!isset($ubrId)) {
            header("Location: " .  $_SERVER['HTTP_REFERER']);
            exit;
        }

        /*
         * Why is this here? Just to check if it is a valid ubrId?
         */
        try {
            $this->service->getOrganizationByUbrId($ubrId);
        } catch (OrganizationWithUbrIdNotExistingException $exception) {
            header("Location: " .  $_SERVER['HTTP_REFERER']);
            exit;
        }

        $ref = array_key_exists('HTTP_REFERER', $_SERVER) ?
                $_SERVER['HTTP_REFERER'] : '/';
        $url = "";
        if (str_contains($ref, "/admin/manage")) {
            // Matches pattern /admin/manage/UBR_ID/.* and replaces id
            $url = preg_replace(
                "/(\/admin\/manage\/)[a-zA-Z0-9]*(.*)/m",
                "$1" . $ubrId . "$2",
                $ref
            );
        } else {
            // remove admin prefix
            $splitRef = explode("/admin", $ref);
            $url = "/admin/manage/" . $ubrId;
            if (count($splitRef) > 1) {
                $url .= $splitRef[1];
            }
        }

        header("Location: " .  $url);
        exit;
    }
}
