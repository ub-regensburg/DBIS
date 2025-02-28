<?php

declare(strict_types=1);

namespace App\Action\Frontend\Admin;

use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;
use App\Infrastructure\Shared\ResourceProvider;
use App\Domain\Shared\AuthService;
use App\Action\Frontend\Admin\AdminBasePage;

/**
 * AdminStartPage
 *
 * Start Page for logged in users.
 *
 * Currently in stub mode
 *
 */
class DummyLoginPage extends AdminBasePage
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // simple auth guard
        if ($this->authService->isSessionAuthenticated()) {
            return $response->withHeader('Location', '/admin');
        }


        $view = Twig::fromRequest($request);
        return $view->render(
            $response,
            'admin/dummy_loginpage.twig',
            [
            'pageTitle' => 'DBIS - Login Debug',
                'i18n' => $this->resourceProvider->getAssocArrayForLanguage("de")
            ]
        );
    }
}
