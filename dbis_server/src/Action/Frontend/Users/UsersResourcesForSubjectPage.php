<?php

declare(strict_types=1);

namespace App\Action\Frontend\Users;

use App\Domain\Resources\Entities\SortType;
use App\Infrastructure\Shared\SearchClient;
use Couchbase\BaseException;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;
use App\Domain\Resources\Entities\Resource;

/**
 * UsersResourcesForSubjectPage
 *
 * Subject page for users
 */
class UsersResourcesForSubjectPage extends UsersBasePage
{
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

        $language = $_SESSION["language"] ?? "de";

        $q = isset($request->getQueryParams()['q']) ?
        $request->getQueryParams()['q'] : null;

        $subjectId =  $request->getAttribute('subjectId');
        $page_subject = $this->service->getSubjectById((int) $subjectId);

        $top_databases = $this->service->getTopRessourcesForSubject((int) $subjectId, $organization_id);

        if ($top_databases){
            foreach($top_databases as &$top_db){
                $top_db = $top_db->toI18nAssocArray($this->language);
                $top_db = $this->determineTrafficLight($top_db, false);
            }
        }

        $sort_types = $this->service->getSortTypes();

        /*
         * Get and set query params
         */
        $query_params = $request->getQueryParams();

        //$subject_title = $page_subject->getTitle()[$language];

        /*if (!isset($query_params['filter-subjects'])) {
            $query_params['filter-subjects'] = [$subject_title];
        } else {
            if (!in_array($subject_title, $query_params['filter-subjects'])){
                $query_params['filter-subjects'][] = $subject_title;
            }
        }*/

        $csvOutput = array_key_exists('csvoutput', $query_params) ? true: false;

        $show_top_databases = array_key_exists('toggle-top-databases', $query_params) ? true:  false;

        $p = $query_params['p'] ?? 1;
        $pagination_size =
            isset($query_params['ps']) ?
                // This handles strings as well, which return 0 after abs(int()[STRING])
                (abs((int)$query_params['ps']) == 0 ?
                    25 :
                abs((int)$query_params['ps'])) :
            25;


        $sort_by = isset($query_params['sort_by']) ?
            (int)$query_params['sort_by'] : $page_subject->getSortBy();


        $search_client = new SearchClient($organization_id, $language);

        $filters = $this->build_filters($organization_id, $language, $query_params);

        if ($q && strlen($q) > 0) {
            $search_client->freeSearch($q);
        } else {

        $search_client->matchAll();
        }

        $search_client->addSubjectFacet();
        //$search_client->addAllSubjectFacet();
        $search_client->addKeywordFacet();
        $search_client->addCountryFacet();
        $search_client->addTypeFacet();
        //$search_client->addAllTypeFacet();
        $search_client->addPublicationFormFacet();
        $search_client->addPublisherFacet();
        //$search_client->addAllPublicationFormFacet();

        $search_client->addTopDatabaseFacet();

        $search_client->addGlobalFacet();
        if (!is_null($organization_id)) {
            $search_client->addLicensedFacet();
        }
        $search_client->addUnlicensedFacet();

        $global = (bool)$filters['availability']['free'];
        $licensed = (bool)$filters['availability']['local'];
        $unlicensed = (bool)$filters['availability']['none'];
        
        $search_client->addAvailability($global, $licensed, $unlicensed);

        $subject_title = $page_subject->getTitle()[$language];
        $search_client->addAllSubject($subject_title);
        //var_dump($filters['all_subjects']);


        foreach ($filters['all_subjects'] as $subject) {
            $search_client->addSubject($subject['title']);
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

        if ($filters['top-databases']) {
            $search_client->addTopDatabasesForSubject($subjectId);
            // Get all records
            $from = 0;
            $size = 10000;
            $search_client->setFrom($from);
            $search_client->setSize($size);
        } else {
            $from = (int) $pagination_size * ($p - 1);
            $size = (int) $pagination_size;
            $search_client->setFrom($from);
            $search_client->setSize($size);
        }

        if (!isset($sort_by)) {
                $search_client->sortAlphabetically();
            } else if ($sort_by == ALPHABETICAL_SORTING){
                $search_client->sortAlphabetically();
            } else if ($sort_by == RELEVANCE_SORTING){
                $search_client->sortByRelevance();
            } /*else if ($sort_by == 2){
                $search_client->sortByTopDatabasesOrder($subjectId);
            }*/
           

        $search_client->showOnlyVisibleResources($organization_id);

        $results = $search_client->searchViaDsl();

        // If organization set, only databases that are available
        /*
        $filters['access'] = isset($organization_id) ? [['license' => '1'], ['license' => '2'],
            ['license' => '3'], ['license' => '4'], ['license' => '5'], ['license' => '2', 'access' => 9]] : [];
        */

        $query_params = array_merge($query_params, ['p' => $p, 'ps' => $pagination_size, 'sort_by' => $sort_by]);
       // $query_params_str = http_build_query($query_params);
        /*
         * Query params are set
         */

        /*
         * Get results
         */

        $total_nr = (int) $results['hits']['total']['value'];
        $pages_nr = ceil((int)$total_nr / (int)$pagination_size);
        
        $view = Twig::fromRequest($request);

        $resources = array_map(function ($hit) {
            if (array_key_exists('highlight',$hit)){
                return $hit['_source'] + ['highlight' => $hit['highlight']];
            } else {
                return $hit['_source'];
            }
        }, $results['hits']['hits']);

        // Hack, so the new accesses id are also included in teh ES results. Because the warpto links are based on the id.
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
        }

        $resourcesProcessed = $this->determineMostValuableAccesses($resources);
        $resourcesProcessed = $this->determineTrafficLights($resourcesProcessed);
        $resourcesProcessed = $this->determineTopDatabasesForSubject($resourcesProcessed, $page_subject);

        // Is correct (as WoS Core Collection for Informatic is filtered out)
        if ($filters['top-databases']) {

            //$sort_by = isset($query_params['sort_by']) ? $sort_by : 2;

            $resourcesFiltered = array();
            foreach($resourcesProcessed as $resourceProcessed) {
                if ($resourceProcessed['is_top_database_for_subject']) {
                    $resourcesFiltered[] = $resourceProcessed;
                }
            }

            if ($sort_by == 2) {
               $this->sortBySubjectSortOrder($resourcesFiltered, $subjectId);
            }

            $this->params['resources'] = $resourcesFiltered;
            $this->params['total_nr'] = count($resourcesFiltered);

            $pagination_size = $this->params['total_nr'];
            $pages_nr = 1;
        } else {
            //$sort_by = 1;
            $this->params['total_nr'] = $total_nr;
            $this->params['resources'] = $resourcesProcessed;
        }

        $this->contextProvider->setContext(
            get_class(),
            $request->getQueryParams(),
            $this->getResourceListIds($this->params['resources'])
        );

        $this->params['has_top_databases'] = $this->service->doesSubjectHasTopresources(array("for_subject" => $subjectId), $organization_id);
        $this->params['top_databases'] = $top_databases;

        $this->params['filters'] = $filters;
        $this->params['aggregations'] = $this->buildPublisherAggregations($results['aggregations']);
        $this->params['q'] = $q;
        $this->params['pageTitle'] = "DBIS - " . $page_subject->toI18nAssocArray($language)['title'];
        $this->params['subject'] = $page_subject->toI18nAssocArray($language);
        $this->params['p'] = $p;
        $this->params['search_url'] = ".";
        
        $this->params['pages_nr'] = $pages_nr;
        $this->params['pagination_size'] = $pagination_size;
        $this->params['lang'] = $language;

        $this->params['sort_by'] = $sort_by;

        $this->params['sort_types'] = array_map(function (SortType $sort_type) use ($language) {
            return $sort_type->toI18nAssocArray($language);
        }, $sort_types);

        $this->params['query'] = $query_params;
        $this->params['toggle_top_databases'] = array_key_exists('toggle-top-databases', $query_params) ? true:  false;
        $this->params['route'] = 'subjects';

        // Needs to be done here and not in parent class, as the organizationId is at last set in this invoke function
        $this->params['doesOrganizationHasCollections'] =
            $this->getSelectedOrganizationIdFromSession() != null && $this->doesOrganizationHasCollections() == true;

        if ($csvOutput) {
            return $this->redirectToCsvOutput($request, $response, $this->params['resources'], $organization_id); 
        } else {
            return $view->render(
                $response,
                'users/resources_for_subject.twig',
                $this->params
            );
        }
    }

    private function sortBySubjectSortOrder(&$array, $subjectId) {
        usort($array, function ($a, $b) use ($subjectId) {
            // Find the 'sort_order' for the specified subject ID in both arrays
            $sortOrderA = array_column($a['subjects'], 'sort_order', 'id')[$subjectId] ?? PHP_INT_MAX;
            $sortOrderB = array_column($b['subjects'], 'sort_order', 'id')[$subjectId] ?? PHP_INT_MAX;
    
            // Compare based on the 'sort_order' values
            return $sortOrderA <=> $sortOrderB;
        });
    }
}
