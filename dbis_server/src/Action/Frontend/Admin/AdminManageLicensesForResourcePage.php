<?php

declare(strict_types=1);

namespace App\Action\Frontend\Admin;

use App\Domain\Organizations\Entities\Organization;
use App\Domain\Organizations\Exceptions\OrganizationWithUbrIdNotExistingException;
use App\Domain\Organizations\OrganizationService;
use App\Domain\Resources\Entities\Resource;
use App\Infrastructure\Shared\CountryProvider;
use App\Domain\Resources\ResourceService;
use App\Domain\Shared\AuthService;
use App\Infrastructure\Shared\Exceptions\LanguageNotFoundException;
use App\Infrastructure\Shared\Exceptions\ResourceNotFoundException;
use App\Infrastructure\Shared\ResourceProvider;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;

/**
 * AdminCreateDatabasePage
 *
 * Form for creating a new organization
 */
final class AdminManageLicensesForResourcePage extends AdminBasePage
{
    protected ResourceService $resourceService;
    protected OrganizationService $organizationService;

    public function __construct(
        ResourceProvider $rp,
        AuthService $auth,
        OrganizationService $service,
        CountryProvider $countryProvider,
        ResourceService $resourceService
    ) {
        $this->resourceService = $resourceService;
        $this->organizationService = $service;
        parent::__construct($rp, $auth, $service, $countryProvider, $resourceService);
    }

    /**
     * @throws LanguageNotFoundException
     * @throws OrganizationWithUbrIdNotExistingException
     * @throws ResourceNotFoundException
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // Set organisation according to route parameter
        // Here, the parameter is not ubrId, its organizationId
        if ($request->getAttribute('organizationId')) {
            parent::setAdministratedOrganization($request->getAttribute('organizationId'));
        }
        $organization_id = $this->getSelectedOrganizationIdFromSession();

        if (!$this->authService->isSessionAuthenticated()) {
            header("Location: /admin/login");
            exit;
        } elseif ($this->organization_id && !$this->user->isAdminFor($this->organization_id) && !$this->user->isSubjectSpecialistFor($this->organization_id)) {
            header("Location: /admin");
            exit;
        }

        if ($request->getMethod() == "GET") {
            return $this->handleGetRequest($request, $response, $organization_id);
        } elseif ($request->getMethod() == "POST") {
            return $this->handlePostRequest($request, $response, $organization_id);
        } else {
            return $this->handleGetRequest($request, $response, $organization_id);
        }
    }

    private function handlePostRequest(ServerRequestInterface $request, ResponseInterface $response, $organization_id)
    {
        $resource_id = $request->getAttribute("resourceId");

        if (!empty($_POST['license_id'])) {
            $license_id = (int)$_POST['license_id'];
            $this->resourceService->reuseLicense($license_id, $organization_id);

            $this->params['reused_license'] = true;
        }

        $resource = $this->resourceService->getResourceById_NEW(
            (int)$resource_id,
            $organization_id
        );

        return $this->renderPage($request, $response, $resource, $organization_id);
    }

    private function handleGetRequest(ServerRequestInterface $request, ResponseInterface $response, $organization_id)
    {
        $resource_id = $request->getAttribute("resourceId");

        $resource = $this->resourceService->getResourceById_NEW(
            (int)$resource_id,
            $organization_id
        );

        return $this->renderPage($request, $response, $resource, $organization_id);
    }

    private function renderPage($request, $response, $resource, $organization_id)
    {
        $organization = $organization_id ? $this->organizationService->getOrganizationByUbrId($organization_id) : null;
        $language = $this->authService->getAuthenticatedUser()->getLanguage();

        $additional_licenses = [];

        if ($organization_id && $organization_id !== 'ALL') {
            $additional_licenses = $this->resourceService->getAdditionalLicenses($resource, $organization_id);
        }   

        $countryCode = $organization->getCountryCode();

        if ($countryCode != "DE") {
            $additional_licenses = array_filter($additional_licenses, function($item) {
                return (int)$item['license_type']['id'] !== 3;
            });
        }

        $resourceId = (int) $resource->getId();
        $organisationsWithLicense = $this->resourceService->getOrganisationsWithLicense($resourceId);

        $organisationsWithLicense = array_map(function ($item) use ($language) {
            $organisationWithLicense = null;

            try {
                $organisationWithLicense = $this->organizationService->getOrganizationByUbrId($item['organization']);
            }
            catch(OrganizationWithUbrIdNotExistingException $ex) {
                $organisationWithLicense = null;
            }
            
            if (!is_null($organisationWithLicense)) {
                // $item['organization'] = $organisationWithLicense->toI18nAssocArray($language);
                $item = $organisationWithLicense->toI18nAssocArray($language);
                return $item;
            }
        }, $organisationsWithLicense);

        /*
        $organisationsWithLicense = $this->groupOrganizationsByCity(array_map(function (Organization $o) use ($language) {
            return $o->toI18nAssocArray($language);
        }, $organisationsWithLicense));
        */

        $has_consortial_rights = $this->user->hasPrivilegesToCreateConsortialLicenses($this->organization_id);
        $has_national_rights = $this->user->hasPrivilegesToCreateNationalLicenses($this->organization_id);
        $is_fid = $organization->getIsFID();
        
        $view = Twig::fromRequest($request);

        $this->params['resource_id'] = $resourceId;
        $this->params['pageTitle'] = $this->resourceProvider->getText("page_title_manage_licenses", $language);
        $this->params['organization'] = $organization ? $organization->toI18nAssocArray($language) : null;
        $this->params['resource'] = $resource->toI18nAssocArray($language);
        $this->params['additional_licenses'] = $additional_licenses;

        $this->params['has_consortial_privileges'] = $has_consortial_rights || $this->isSuperAdmin;
        $this->params['has_national_privileges'] = $has_national_rights || $this->isSuperAdmin;
        $this->params['is_fid'] = $is_fid || $this->isSuperAdmin;
        $this->params['organisations_with_license'] = $organisationsWithLicense;
        $this->params['deleted_successfully'] = array_key_exists(
            "deleted_successfully",
            $request->getQueryParams()
        );

        return $view->render(
            $response,
            'admin/manage_licenses_for_resource.twig',
            $this->params
        );
    }
}
