<?php

declare(strict_types=1);

namespace App\Action\Frontend\Admin;

use App\Domain\Organizations\OrganizationService;
use App\Domain\Resources\ResourceService;
use App\Domain\Shared\AuthService;
use App\Infrastructure\Shared\CountryProvider;
use App\Infrastructure\Shared\ResourceProvider;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;

class AdminCollectionManagePage extends AdminBasePage
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
        $organization_id = $this->getSelectedOrganizationIdFromSession();

        // simple auth guard
        if (!$this->authService->isSessionAuthenticated()) {
            return $response->withHeader('Location', '/admin/login');
        } elseif (!$this->isSuperAdmin && !$this->isAdmin) {
            return $response->withHeader('Location', '/admin');
        }

        $options = array("is_superadmin" => $this->isSuperAdmin,
            "organizationId" => $this->administeredOrganization->getUbrId() ?: null);
        $collections = $this->resourceService->getCollections($options);

        $this->params['collections'] = $collections;
        $this->params['pageTitle'] = $this->resourceProvider->getText(
            "h_collection_manage",
            $this->authService->getAuthenticatedUser()->getLanguage()
        );

        $this->params['is_deleted_successfully'] = array_key_exists(
            "deleted_successfully",
            $request->getQueryParams()
        );

        $view = Twig::fromRequest($request);

        return $view->render(
            $response,
            'admin/manage_collections.twig',
            $this->params
        );
    }
}
