<?php

namespace App\Action\Frontend\Admin;

use App\Domain\Organizations\OrganizationService;
use App\Domain\Resources\ResourceService;
use App\Domain\Resources\Entities\Resource;
use App\Domain\Shared\AuthService;
use App\Infrastructure\Shared\CountryProvider;
use App\Infrastructure\Shared\ResourceProvider;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;

class AdminManageResourceDraftsPage extends AdminBasePage
{
    protected ResourceService $resourceService;

    public function __construct(
        ResourceProvider $rp,
        AuthService $auth,
        OrganizationService $service,
        CountryProvider $countryProvider,
        ResourceService $resourceService
    ) {
        parent::__construct($rp, $auth, $service, $countryProvider, $resourceService);
        $this->resourceService = $resourceService;
    }

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
            return $response->withHeader('Location', '/admin/login');
        } elseif (!$this->isSuperAdmin && !$this->isAdmin) {
            return $response->withHeader('Location', '/admin');
        }

        if ($request->getMethod() == "GET") {
            return $this->handleGetRequest($request, $response);
        } 
    }

    private function handleGetRequest($request, $response) {
        $language = $this->language;
        $resources = array_map(function (Resource $r) use ($language) {
            return $r->toI18nAssocArray($language);
        }, $this->resourceService->getResourceDrafts());

        return $this->renderPage($request, $response, $resources);
    }

    private function handleUpdateRequest($request, $response) {

    }

    private function renderPage($request, $response, $resources) {
        $localOrganizationId = $this->administeredOrganization ? $this->administeredOrganization->getUbrId() : null;

        $this->params['resources'] = $resources;
        $this->params['organization'] = $localOrganizationId;

        $this->params['pageTitle'] = $this->resourceProvider->getText(
            "h_drafts_manage",
            $this->authService->getAuthenticatedUser()->getLanguage()
        );

        $view = Twig::fromRequest($request);

        return $view->render(
            $response,
            'admin/manage_drafts.twig',
            $this->params
        );
    }
}
