<?php

declare(strict_types=1);

namespace App\Action\Frontend\Admin;

use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;

/**
 * AdminStartPage
 *
 * Start Page for logged in users.
 *
 * Currently in stub mode
 *
 */
class AdminStartPage extends AdminBasePage
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // Set organisation according to route parameter
        if ($request->getAttribute('ubrId')) {
            parent::setAdministratedOrganization($request->getAttribute('ubrId'));
        }

        // simple auth guard
        if (!$this->authService->isSessionAuthenticated()) {
            return $response->withHeader('Location', '/admin/login')->withStatus(302);
        }

        $this->params['pageTitle'] = 'DBIS - Administration';

        $view = Twig::fromRequest($request);
        return $view->render(
            $response,
            'admin/start.twig',
            $this->params
        );
    }
}
