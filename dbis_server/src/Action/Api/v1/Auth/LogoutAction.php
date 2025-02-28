<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Auth;

use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;
use App\Infrastructure\Shared\ResourceProvider;
use App\Domain\Shared\AuthService;

/**
 * Logout
 *
 * Action for currently simulating admin and superadmin logouts.
 *
 * TODO: fix this, when proper auth is set up
 */
class LogoutAction
{
    /** @var AuthService */
    private $authService;

    public function __construct(
        ResourceProvider $rp,
        AuthService $auth
    ) {
        $this->resourceProvider = $rp;
        $this->authService = $auth;
    }


    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // Clear session if logged out.
        unset($_SESSION['ubrId']);

        $this->authService->logout();
        // header("Location:/admin/login");
        return $response->withHeader('Location', '/admin/login')->withStatus(302);
    }
}
