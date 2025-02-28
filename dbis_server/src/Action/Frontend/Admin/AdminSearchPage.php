<?php

declare(strict_types=1);

namespace App\Action\Frontend\Admin;

use App\Domain\Resources\Entities\SortType;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;
use App\Domain\Shared\AuthService;
use App\Infrastructure\Shared\CountryProvider;
use App\Domain\Organizations\OrganizationService;
use App\Infrastructure\Shared\ResourceProvider;
use App\Domain\Resources\ResourceService;
use App\Domain\Shared\Internationalizable;

/**
 * AdminSearchPage
 *
 */
class AdminSearchPage extends AdminBasePage
{
    private ResourceService $resourceService;

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
            return $response->withHeader('Location', '/admin/login')->withStatus(302);
        } elseif (!$this->isSuperAdmin && !$this->isAdmin && !$this->isSubjectSpecialist) {
            return $response->withHeader('Location', '/admin')->withStatus(302);
        }

        // standard params
        $queryParams = $request->getQueryParams();
        $language = $this->language;

        // search params
        $q = isset($request->getQueryParams()['q']) ?
                $request->getQueryParams()['q'] : null;
        $additional_fields = [];
        $bool = $request->getQueryParams()['bool'] ?? [];
        $field = $request->getQueryParams()['field'] ?? [];
        $search = $request->getQueryParams()['search'] ?? [];
        $limit = 25;
        $offset = 0;

        foreach ($bool as $i => $b) {
            $additional_fields[] = ["bool" => $b, "field" => $field[$i], "search" => $search[$i]];
        }
        $sort_types = $this->resourceService->getSortTypes();
        array_pop($sort_types);  // Remove custom sorting

        $sort_by = isset($request->getQueryParams()['sort_by']) ?
            (int)$request->getQueryParams()['sort_by'] : RELEVANCE_SORTING;

        // ================================================
        // ==== BUILD FILTERS
        // ==== Filters are passed to form (e.g. for rendering tags) and to the
        // ==== repository, where they are integrated into the search queries
        // ================================================
        $filters = $this->build_filters($organization_id, $language, $queryParams);

        $additionalSearchString = "";
        foreach ($additional_fields as $addSearch) {
            if ($addSearch["search"] != "") {
                $additionalSearchString .= " " . $addSearch["bool"] . " " .
                        $addSearch["field"] . " = '" . $addSearch["search"] . "'";
            }
        }

        $subjects = $this->resourceService->getResourceAggregatesHandledAsSubject(
            ['sort_language' => $language,
            'include_collections' => true,
            'organizationId' => $organization_id,
            'without_resources' => true]
        );
        // $subjects = $this->resourceService->getResourceAggregatesHandledAsSubject(["organizationId" => $organization_id]);
        $subjectsAssoc = array_map(function ($i) use ($language) {
            return $i->toI18nAssocArray($language);
        }, $subjects);

        $publishers = $this->service->getPublishers();

        $hosts = $this->service->getHosts();

        $authors = $this->service->getAuthors();

        $resourceTypes = $this->resourceService->getTypes();
        $resourceTypesAssoc = array_map(function ($i) use ($language) {
            return $i->toI18nAssocArray($language);
        }, $resourceTypes);

        $countries = $this->resourceService->getCountries();
        $countriesAssoc = array_map(function ($i) use ($language) {
            return $i->toI18nAssocArray($language);
        }, $countries);     

        $licenseTypes = $this->resourceService->getLicenseTypes(["organizationId" => $organization_id]);
        $licenseTypesAssoc = array_map(function ($i) use ($language) {
            return $i->toI18nAssocArray($language);
        }, $licenseTypes);

        $licenseForms = $this->resourceService->getLicenseForms(["organizationId" => $organization_id]);
        $licenseFormsAssoc = array_map(function ($i) use ($language) {
            return $i->toI18nAssocArray($language);
        }, $licenseForms);

        $accessForms = $this->resourceService->getAccessForms(["organizationId" => $organization_id]);
        $accessFormsAssoc = array_map(function ($i) use ($language) {
            return $i->toI18nAssocArray($language);
        }, $accessForms);

        $publicationForms = $this->resourceService->getPublicationForms(["organizationId" => $organization_id]);
        $publicationFormsAssoc = array_map(function ($i) use ($language) {
            return $i->toI18nAssocArray($language);
        }, $publicationForms);

        // TODO: Make them classes and implement toI18nAssocArray
        $accessLabels = $this->resourceService->getLabels($organization_id);
        $accessLabels = array_map(function ($i) use ($language) {
            $i['label'] = !is_null($i['label']) ? $i['label'][$language] : null;
            $i['label_long'] = !is_null($i['label_long']) ? $i['label_long'][$language] : null;
            $i['label_longest'] = !is_null($i['label_longest']) ? $i['label_longest'][$language] : null;

            return $i;
        }, $accessLabels);

        $view = Twig::fromRequest($request);
        $this->params['pageTitle'] = $this->resourceProvider->getText("page_title_search", $this->language);
        $this->params['q'] = $q;
        $this->params['additional_search_string'] = $additionalSearchString;
        $this->params['limit'] = $limit;
        $this->params['offset'] = $offset;
        $this->params['organization'] = $organization_id;
        $this->params['sort_by'] = $sort_by;
        $this->params['sort_types'] = array_map(function (SortType $sort_type) use ($language) {
            return $sort_type->toI18nAssocArray($language);
        }, $sort_types);
        $this->params['subjects'] = $subjectsAssoc;
        $this->params['countries'] = $countriesAssoc;
        $this->params['publishers'] = $publishers;
        $this->params['hosts'] = $hosts;
        $this->params['authors'] = $authors;        
        $this->params['licenseTypes'] = $licenseTypesAssoc;
        $this->params['licenseForms'] = $licenseFormsAssoc;
        $this->params['accessForms'] = $accessFormsAssoc;
        $this->params['publicationForms'] = $publicationFormsAssoc;
        $this->params['filters'] = $filters;
        $this->params['resource_types'] = $resourceTypesAssoc;
        $this->params['accessLabels'] = $accessLabels;
        
        return $view->render(
            $response,
            'admin/search_databases.twig',
            $this->params
        );
    }
}
