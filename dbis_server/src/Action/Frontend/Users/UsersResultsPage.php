<?php

declare(strict_types=1);

namespace App\Action\Frontend\Users;

use App\Domain\Resources\Entities\SortType;
use App\Infrastructure\Shared\SearchQuery;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;
use App\Domain\Organizations\OrganizationService;
use App\Infrastructure\Shared\ResourceProvider;
use App\Infrastructure\Shared\ContextProvider;
use App\Infrastructure\Shared\SearchClient;
use App\Domain\Resources\ResourceService;
use App\Domain\Shared\Internationalizable;

/**
 * UsersResultsPage
 *
 */
class UsersResultsPage extends UsersBasePage
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

        if ($organization_id == "ALL") {
            $organization_id = null;
        }

        $queryParams = $request->getQueryParams();

        $csvOutput = array_key_exists('csvoutput', $queryParams) ? true: false;

        $language = $this->language;

        $q = isset($request->getQueryParams()['q']) ?
            $request->getQueryParams()['q'] : null;

        /*
         * Needs to reset the bool operators from advanced search when simple search is executed
         */
        if (isset($queryParams['simple-search'])) {
            unset($queryParams['bool']);
            unset($queryParams['field']);
            unset($queryParams['search']);
        }

        $additional_fields = [];
        $bool = $queryParams['bool'] ?? [];
        $field = $queryParams['field'] ?? [];
        $search = $queryParams['search'] ?? [];

        $p = isset($queryParams['p']) ?
            $request->getQueryParams()['p'] : 1;

        $pagination_size =
            isset($queryParams['ps']) ?
                // This handles strings as well, which return 0 after abs(int()[STRING])
                (abs((int)$queryParams['ps']) == 0 ?
                    25 :
                    abs((int)$queryParams['ps'])) :
                25;

        $sort_types = $this->service->getSortTypes();
        //array_pop($sort_types);  // Remove custom sorting

        $sort_by = isset($queryParams['sort_by']) ?
            (int)$queryParams['sort_by'] : RELEVANCE_SORTING;

        $this->params['sort_by'] = $sort_by;
        $this->params['sort_types'] = array_map(function (SortType $sort_type) use ($language) {
            return $sort_type->toI18nAssocArray($language);
        }, $sort_types);

        foreach ($bool as $i => $b) {
            $additional_fields[] = ["bool" => $b, "field" => $field[$i], "search" => $search[$i]];
        }

        /*
         * String that will be displayed below the simple search input
         */
        $additionalSearchString = "";
        foreach ($additional_fields as $addSearch) {
            if ($addSearch["search"] != "") {
                $additionalSearchString .= " " . $addSearch["bool"] . " " .
                    $addSearch["field"] . " = '" . $addSearch["search"] . "'";
            }
        }

        $filters = $this->build_filters($organization_id, $language, $queryParams);

        $view = Twig::fromRequest($request);
        $this->params['pageTitle'] = $this->resourceProvider->getText("page_title_search", $this->language);
        $this->params['useElastic'] = true;

        $search_client = new SearchClient($organization_id, $language);

        $match_all = true;

        if ($q && strlen($q) > 0) {
            $search_client->freeSearch($q);
            $sort_by = isset($queryParams['sort_by']) ? $sort_by : RELEVANCE_SORTING;
            $match_all = false;
        }

        foreach ($bool as $i => $b) {
            if ($b) {
                switch ($field[$i]) {
                    case "description":
                        if (strlen($search[$i]) > 0) {
                            $search_client->addDescription($search[$i], $b);
                            $match_all = false;
                        }
                        break;
                    case "title":
                        if (strlen($search[$i]) > 0) {
                            $search_client->addTitle($search[$i], $b);
                            $match_all = false;
                        }
                        break;
                }
            }
        }

        if ($match_all) {
            $search_client->matchAll();
            $sort_by = isset($queryParams['sort_by']) ? $sort_by : RELEVANCE_SORTING;
        }

        //$search_client->addSubjectFacet();
        $search_client->addAllSubjectFacet();
        $search_client->addKeywordFacet();
        $search_client->addCountryFacet();
        $search_client->addLicenseTypeFacet();
        $search_client->addAllTypeFacet();
        //$search_client->addPublicationFormFacet();
        $search_client->addPublisherFacet();
        $search_client->addAllPublicationFormFacet();

        $search_client->addTopDatabaseFacet();

        $search_client->addGlobalFacet();
        if (!is_null($organization_id)) {
            $search_client->addLicensedFacet($organization_id);
        }
        $search_client->addUnlicensedFacet($organization_id);

        $global = (bool)$filters['availability']['free'];
        $licensed = (bool)$filters['availability']['local'];
        $unlicensed = (bool)$filters['availability']['none'];
        
        $search_client->addAvailability($global, $licensed, $unlicensed);

        /*
        foreach ($filters['subjects'] as $subject) {
            $search_client->addSubject($subject);
        }*/


        foreach ($filters['all_subjects'] as $subject) {
            $search_client->addAllSubject($subject['title']);
        }        

        foreach ($filters['keywords'] as $keyword) {
            $search_client->addKeyword($keyword['title']);
        }

        foreach ($filters['countries'] as $country) {
            $search_client->addCountry($country['title']);
        }

        foreach ($filters['resource-types'] as $resource_type) {
            $search_client->addType($resource_type['title']);
        }

        foreach ($filters['publication-forms'] as $publicationForm) {
            $search_client->addPublicationForm($publicationForm['title']);
        }

        foreach ($filters['publishers'] as $publisher) {
            $search_client->addPublisher($publisher['id']);
        }    
        
        foreach ($filters['authors'] as $author){
            $search_client->addAuthor($author);
        }

        if ($filters['top-databases']) {
            $search_client->addTopDatabases();
        }

        if ($filters['entry-date']['start']) {
            $search_client->addEntryDate($filters['entry-date']['start']);
        }

        if ($filters['publication-time']['start']) {
            $search_client->addPublicationTimeStart($filters['publication-time']['start']);
        }

        if ($filters['publication-time']['end']) {
            $search_client->addPublicationTimeEnd($filters['publication-time']['end']);
        }

        if ($filters['report-time']['start']) {
            $search_client->addReportTimeStart($filters['report-time']['start']);
        }

        if ($filters['report-time']['end']) {
            $search_client->addReportTimeEnd($filters['report-time']['end']);
        }

        foreach ($filters['license-types'] as $licenseType) {
            $search_client->addLicenseType($licenseType);
        }

        foreach ($filters['license-forms'] as $licenseForm) {
            $search_client->addLicenseForm($licenseForm);
        }

        foreach ($filters['access-forms'] as $accessForm) {
            $search_client->addAccessForm($accessForm);
        }

        $from = (int) $pagination_size * ($p - 1);
        $size = (int) $pagination_size;
        $search_client->setFrom($from);
        $search_client->setSize($size);

        if ($sort_by == ALPHABETICAL_SORTING) {
           // TODO: 16.10.2024 Not working; leads to error
            $search_client->sortAlphabetically();
        }

        $search_client->showOnlyVisibleResources($organization_id);

        $results = $search_client->searchViaDsl();

        //strip html entities from description and title fields
        foreach ($results['hits']['hits'] as &$result) {
            $result['_source'] = $this->resolve_html_entities_in_fields($result['_source']);
        }

        $resources = array_map(function ($hit) {
            if (array_key_exists('highlight',$hit)){
                return $hit['_source'] + ['highlight' => $hit['highlight']];
            } else {
                return $hit['_source'];
            }
        }, $results['hits']['hits']);

        $publicationForms = $this->service->getPublicationForms();
        $publicationFormsAssoc = array_map(function ($i) use ($language) {
            return $i->toI18nAssocArray($language);
        }, $publicationForms);

        
        // Hack, so the new accesses id are also included in the ES results. Because the warpto links are based on the id.
        foreach($resources as &$resource) {
            if ($resource['licenses']) {
                foreach($resource['licenses'] as &$license) {
                    if ($license['accesses']) {
                        foreach($license['accesses'] as &$access) {
                            $access['id'] = $this->service->getNewAccessIdForElasticSearch($license, $access);
                        }
                    }
                }
            } 
            if ($resource['resource_localisations']){
               /* if ('title' in $resource['resource_localisations']){

                }*/
            }  
        }

        $this->params['resources'] = $this->determineMostValuableAccesses($resources);
        $this->params['resources'] = $this->determineTrafficLights($this->params['resources']);

        $this->contextProvider->setContext(
            get_class(),
            $request->getQueryParams(),
            $this->getResourceListIds($this->params['resources'])
        );

        $this->params['aggregations'] = $this->buildPublisherAggregations($results['aggregations']);

        $this->params['sort_by'] = $sort_by;
        $this->params['lang'] = $language;
        $this->params['sort_types'] = array_map(function (SortType $sort_type) use ($language) {
            return $sort_type->toI18nAssocArray($language);
        }, $sort_types);

        $total_nr = (int) $results['hits']['total']['value'];
        $pages_nr = ceil($total_nr / $pagination_size);
        $this->params['organizationId'] = $organization_id;
        $this->params['q'] = $q;
        $this->params['additional_fields'] = $additional_fields;
        $this->params['additional_search_string'] = $additionalSearchString;
        $this->params['p'] = $p;
        $this->params['total_nr'] = $total_nr;
        $this->params['pages_nr'] = $pages_nr;
        $this->params['filters'] = $filters;
        $this->params['pagination_size'] = $pagination_size;
        $this->params['query_string'] = $_SERVER['QUERY_STRING'];
        $this->params['available_publication_forms'] = $publicationFormsAssoc;

        $this->params['doesOrganizationHasCollections'] =
            $this->getSelectedOrganizationIdFromSession() != null && $this->doesOrganizationHasCollections() == true;

        if ($csvOutput) {
            return $this->redirectToCsvOutput($request, $response, $this->params['resources'], $organization_id); 
        } else {
            return $view->render(
                $response,
                'users/results_elastic_search.twig',
                $this->params
            );
        }
    }

    private function resolve_html_entities_in_fields($result){
        if ($result['resource_title']) {
                $result['resource_title'] = $this->decode_safe($result['resource_title']);
        }        
        if ($result['description']) {
            if (array_key_exists('de', $result['description'])) {
                $result['description']['de'] = $this->decode_safe($result['description']['de']);
            }
            if (array_key_exists('en', $result['description'])) {
                $result['description']['en'] = $this->decode_safe($result['description']['en']);
            }
        }
        if ($result['description_short']) {
            if (array_key_exists('de', $result['description_short'])) {
                $result['description_short']['de'] = $this->decode_safe($result['description_short']['de']);
            }
            if (array_key_exists('en', $result['description_short'])) {
                $result['description_short']['en'] = $this->decode_safe($result['description_short']['en']);
            }
        } 
        return $result;        

    }

}
