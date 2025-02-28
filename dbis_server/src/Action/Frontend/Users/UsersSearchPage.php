<?php

declare(strict_types=1);

namespace App\Action\Frontend\Users;

use App\Domain\Resources\Entities\SortType;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;
use App\Domain\Organizations\OrganizationService;
use App\Infrastructure\Shared\ResourceProvider;
use App\Infrastructure\Shared\ContextProvider;
use App\Domain\Resources\ResourceService;
use App\Domain\Shared\Internationalizable;

/**
 * UsersSearchPage
 *
 */
class UsersSearchPage extends UsersBasePage
{
    protected ResourceService $service;

    public function __construct(
        OrganizationService $os,
        ResourceProvider $rp,
        ResourceService $service,
        ContextProvider $ctx
    ) {
        parent::__construct($rp, $service, $os, $ctx);
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // Set organisation according to route parameter which is done after session and ip test in parent constructor
        // But first the if the orgId exists otherwise the session gets unset.
        if ($request->getAttribute('organizationId')) {
            parent::setSelectedOrganization($request->getAttribute('organizationId'));
        }
        $organization_id = $this->getSelectedOrganizationIdFromSession();

        $includeCollections = true;
        if ($organization_id == "ALL") {
            $organization_id = null;
            $includeCollections = false;
        }

        $queryParams = $request->getQueryParams();

        $language = $this->language;

        $q = isset($request->getQueryParams()['q']) ?
                $request->getQueryParams()['q'] : null;

        $additional_fields = [];
        $bool = $request->getQueryParams()['bool'] ?? [];
        $field = $request->getQueryParams()['field'] ?? [];
        $search = $request->getQueryParams()['search'] ?? [];

        foreach ($bool as $i => $b) {
            $additional_fields[] = ["bool" => $b, "field" => $field[$i], "search" => $search[$i]];
        }

        $sort_types = $this->service->getSortTypes();
        array_pop($sort_types);  // Remove custom sorting

        $sort_by = isset($request->getQueryParams()['sort_by']) ?
            (int)$request->getQueryParams()['sort_by'] : RELEVANCE_SORTING;

        // ================================================
        // ==== BUILD FILTERS
        // ==== Filters are passed to form (e.g. for rendering tags) and to the
        // ==== repository, where they are integrated into the search queries
        // ================================================
        $filters = $this->build_filters($organization_id, $language, $queryParams);
        // ================================================
        // ==== /BUILD FILTERS
        // ================================================

        $additionalSearchString = "";
        foreach ($additional_fields as $addSearch) {
            if ($addSearch["search"] != "") {
                $additionalSearchString .= " " . $addSearch["bool"] . " " .
                        $addSearch["field"] . " = '" . $addSearch["search"] . "'";
            }
        }

        $subjects = $this->service->getResourceAggregatesHandledAsSubject(
            ['sort_language' => $language,
            'include_collections' => $includeCollections,
            'organizationId' => $organization_id,
            'without_resources' => true]
        );

        $subjectsAssoc = array_map(function ($i) use ($language) {
            return $i->toI18nAssocArray($language);
        }, $subjects);

        $countries = $this->service->getCountries();
        $countriesAssoc = array_map(function ($i) use ($language) {
            return $i->toI18nAssocArray($language);
        }, $countries);

        $publishers = $this->service->getPublishers();

        $hosts = $this->service->getHosts();

        $authors = $this->service->getAuthors();

        $resourceTypes = $this->service->getTypes();
        $resourceTypesAssoc = array_map(function ($i) use ($language) {
            return $i->toI18nAssocArray($language);
        }, $resourceTypes);

        $licenseTypes = $this->service->getLicenseTypes(["organizationId" => $organization_id]);
        $licenseTypesAssoc = array_map(function ($i) use ($language) {
            return $i->toI18nAssocArray($language);
        }, $licenseTypes);

        $licenseForms = $this->service->getLicenseForms(["organizationId" => $organization_id]);
        $licenseFormsAssoc = array_map(function ($i) use ($language) {
            return $i->toI18nAssocArray($language);
        }, $licenseForms);

        $accessForms = $this->service->getAccessForms(["organizationId" => $organization_id]);
        $accessFormsAssoc = array_map(function ($i) use ($language) {
            return $i->toI18nAssocArray($language);
        }, $accessForms);        

        $publicationForms = $this->service->getPublicationForms(["organizationId" => $organization_id]);
        $publicationFormsAssoc = array_map(function ($i) use ($language) {
            return $i->toI18nAssocArray($language);
        }, $publicationForms);

        $search_start = true;
        if (count($_GET)) {
            $search_start = false;
        }

        $view = Twig::fromRequest($request);
        $this->params['search_start'] = $search_start;
        $this->params['q'] = $q;
        $this->params['additional_fields'] = $additional_fields;
        $this->params['additional_search_string'] = $additionalSearchString;
        $this->params['pageTitle'] = $this->resourceProvider->getText("page_title_search", $this->language);
        $this->params['sort_by'] = $sort_by;
        $this->params['sort_types'] = array_map(function (SortType $sort_type) use ($language) {
            return $sort_type->toI18nAssocArray($language);
        }, $sort_types);
        $this->params['subjects'] = $subjectsAssoc;
        $this->params['countries'] = $countriesAssoc;
        $this->params['publishers'] = $publishers;
        $this->params['hosts'] = $hosts;
        $this->params['authors'] = $authors;
        $this->params['filters'] = $filters;

        $this->params['resource_types'] = $resourceTypesAssoc;
        $this->params['licenseTypes'] = $licenseTypesAssoc;
        $this->params['licenseForms'] = $licenseFormsAssoc;
        $this->params['accessForms'] = $accessFormsAssoc;
        $this->params['publicationForms'] = $publicationFormsAssoc;
        // Needs to be done here and not in parent class, as the organizationId is at last set in this invoke function
        $this->params['doesOrganizationHasCollections'] =
            $this->getSelectedOrganizationIdFromSession() != null && $this->doesOrganizationHasCollections() == true;

        return $view->render(
            $response,
            'users/search.twig',
            $this->params
        );
    }
}
