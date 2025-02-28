<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Auth;

use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;
use App\Infrastructure\Shared\ResourceProvider;
use App\Domain\Shared\AuthService;

/**
 * DummyAuthAction
 *
 * Action for simulating admin and superadmin logins.
 *
 * TODO: remove this, when "proper" auth is setup
 */
class DummyAuthAction
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
        $type = $request->getAttribute("type");
        if ($type == "superadmin") {
            $this->authService->login("Silvia Super", "test1234");
        } elseif ($type == "admin") {
            $this->authService->login("Alfred Admin", "test1234");
        }
        header("Location:/admin");
    }
}
