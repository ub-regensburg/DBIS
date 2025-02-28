<?php

namespace App\Action\Frontend\Admin;

use App\Domain\Organizations\OrganizationService;
use App\Domain\Resources\ResourceService;
use App\Domain\Shared\AuthService;
use App\Infrastructure\Shared\CountryProvider;
use App\Infrastructure\Shared\ResourceProvider;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;

class AdminManageRelationshipsPage extends AdminBasePage
{
    protected ResourceService $resourceService;

    // TODO: OrganizationService should not be in the base class
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
        } elseif ($request->getMethod() == "PUT") {
            return $this->handleUpdateRequest($request, $response);
        } elseif ($request->getMethod() == "POST") {
            return $this->handleUpdateRequest($request, $response);
        }
    }

    private function handleGetRequest(ServerRequestInterface $request, ResponseInterface $response)
    {
        return $this->renderPage($request, $response);
    }

    private function handleUpdateRequest(ServerRequestInterface $request, ResponseInterface $response)
    {
        $parsed_body = $request->getParsedBody();

        $database_id = $parsed_body['database-id'];

        $top_databases = [];
        if (array_key_exists('top-databases', $parsed_body)) {
            $top_databases = $parsed_body['top-databases'];
            $relation_type = "resource-is-child";
        }
        
        $sub_databases  = [];
        if (array_key_exists('sub-databases', $parsed_body)) {
            $sub_databases = $parsed_body['sub-databases'];
            $relation_type = "resource-is-parent";
        }

        $related_databases = [];
        if (array_key_exists('related-databases', $parsed_body)) {
            $related_databases = $parsed_body['related-databases'];
            $relation_type = "resource-is-related";
        }

        $this->resourceService->updateRelationships($database_id , $related_databases, $top_databases, $sub_databases);

        header("Location: {$_SERVER['REQUEST_URI']}?updated_successfully=1", true, 303);
        exit();
        // return $this->renderPage($request, $response);
    }

    private function renderPage(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        $organization = $this->administeredOrganization;
        $language = $this->authService->getAuthenticatedUser()->getLanguage();

        $this->params['pageTitle'] = $this->resourceProvider->getText(
            "h_keywords_manage",
            $this->authService->getAuthenticatedUser()->getLanguage()
        );

        $this->params['is_updated_successfully'] = array_key_exists(
            "updated_successfully",
            $request->getQueryParams()
        );

        $view = Twig::fromRequest($request);

        return $view->render(
            $response,
            'admin/manage_relationships.twig',
            $this->params
        );
    }
}
