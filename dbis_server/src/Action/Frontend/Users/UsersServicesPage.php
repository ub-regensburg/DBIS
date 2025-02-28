<?php

declare(strict_types=1);

namespace App\Action\Frontend\Users;

use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;

/**
 * UsersSearchPage
 *
 * Search page for users
 */
class UsersServicesPage extends UsersBasePage
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        // Set organisation according to route parameter which is done after session and ip test in parent constructor
        // But first the if the orgId exists otherwise the session gets unset.
        if ($request->getAttribute('organizationId')) {
            parent::setSelectedOrganization($request->getAttribute('organizationId'));
        }
        $organization_id = $this->getSelectedOrganizationIdFromSession();

        $language = $_SESSION["language"] ?? "de";

        $view = Twig::fromRequest($request);
        $this->params['pageTitle'] = $this->resourceProvider->getText("page_title_dbis_services", $language);
        $this->params['organizationId'] = $organization_id;

        return $view->render(
            $response,
            'users/services.twig',
            $this->params
        );
    }
}
