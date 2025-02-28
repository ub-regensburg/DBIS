<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Auth;

use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use App\Infrastructure\Shared\ResourceProvider;
use App\Domain\Shared\AuthService;

/**
 * Logout
 *
 * Action for currently simulating admin and superadmin logouts.
 *
 * TODO: fix this, when proper auth is set up
 */
class SetLanguageAction
{
    /** @var AuthService */
    private $authService;

    public function __construct(
        AuthService $auth
    ) {
        $this->authService = $auth;
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $body = $request->getParsedBody();
        $language = $body['language'];
        $user = $this->authService->getAuthenticatedUser();

        if ($user) {
            $user->setLanguage($language);
            $this->authService->setAuthenticatedUser($user);

            $referer = $request->getServerParams()['HTTP_REFERER'] ?? '/';

            return $response->withHeader('Location', $referer)->withStatus(302);
        } else {
            return $response->withHeader('Location', '/admin/login');
        }
        
    }
}
