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
use App\Infrastructure\Shared\SearchClient;
use App\Domain\Resources\ResourceService;
use App\Domain\Shared\Internationalizable;

/**
 * AdminResultsPage
 *
 */
class AdminResultsPage extends AdminBasePage
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

        $organization = $organization_id ? $this->organizationService->getOrganizationByUbrId($organization_id) : null;

        // simple auth guard
        if (!$this->authService->isSessionAuthenticated()) {
            return $response->withHeader('Location', '/admin/login')->withStatus(302);
        } elseif (!$this->isSuperAdmin && !$this->isAdmin && !$this->isSubjectSpecialist) {
            return $response->withHeader('Location', '/admin')->withStatus(302);
        }

        // standard params
        $queryParams = $request->getQueryParams();
        $language = $this->language;

        $csvOutput = array_key_exists('csvoutput', $queryParams) ? true: false;

        // search params
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

        $sort_by = RELEVANCE_SORTING;

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
            $match_all = false;
        } else {
            $q = "";
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
            $sort_by = ALPHABETICAL_SORTING;
        }

        $this->params['sort_by'] = $sort_by;

        $search_client->addSubjectFacet();
        $search_client->addKeywordFacet();
        $search_client->addCountryFacet();
        $search_client->addTypeFacet();
        $search_client->addPublisherFacet();
        $search_client->addPublicationFormFacet();

        $search_client->addGlobalFacet();
        if (!is_null($organization_id)) {
            $search_client->addLicensedFacet($organization_id);
        }
        $search_client->addUnlicensedFacet($organization_id);

        $global = (bool)$filters['availability']['free'];
        $licensed = (bool)$filters['availability']['local'];
        $unlicensed = (bool)$filters['availability']['none'];
        $search_client->addAvailability($global, $licensed, $unlicensed, $organization_id);

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

        foreach ($filters['access-labels'] as $accessLabel) {
            if ($accessLabel == "-1") {
                $search_client->addAccessLabel(null);
            } else {
                $search_client->addAccessLabel($accessLabel);
            }
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

        $from = (int) $pagination_size * ($p - 1);
        $size = (int) $pagination_size;
        $search_client->setFrom($from);
        
        $search_client->setSize($size);

        if ($sort_by == ALPHABETICAL_SORTING) {
            // TODO: 16.10.2024 Not working; leads to error
            $search_client->sortAlphabetically();
        }

        $results = $search_client->searchViaDsl();

        //strip html entities from title fields
        foreach ($results['hits']['hits'] as &$result) {
            if ($result['_source']['resource_title']) {
                $result['_source']['resource_title'] = $this->decode_safe($result['_source']['resource_title']);
            }      
        }

        $total_nr = (int) $results['hits']['total']['value'];
        $pages_nr = ceil($total_nr / $pagination_size);

        $resources = array_map(function ($hit) {
            return $hit['_source'];
        }, $results['hits']['hits']);

        $this->params['aggregations'] = $results['aggregations'];

        $this->params['organizationId'] = $organization_id;
        $this->params['additional_fields'] = $additional_fields;
        $this->params['additional_search_string'] = $additionalSearchString;
        $this->params['total_nr'] = $total_nr;
        $this->params['pages_nr'] = $pages_nr;
        $this->params['filters'] = $filters;
        $this->params['pagination_size'] = $pagination_size;
        $this->params['query_string'] = $_SERVER['QUERY_STRING'];
        $this->params['q'] = $q;
        $this->params['p'] = $p;
        $this->params['pagination_size'] = $pagination_size;
        $this->params['pages_nr'] = $pages_nr;
        $this->params['resources'] = $this->determineTrafficLights($resources);
        $this->params['pageTitle'] = $this->resourceProvider->getText(
            "h_res_manage",
            $this->authService->getAuthenticatedUser()->getLanguage()
        );
        $this->params['organization'] = $organization ? $organization->toI18nAssocArray($language) : null;
        $view = Twig::fromRequest($request);

        if ($csvOutput) {
            return $this->redirectToCsvOutput($request, $response, $this->params['resources'], $organization_id); 
        } else {
            return $view->render(
                $response,
                'admin/manage_databases.twig',
                $this->params
            );
        }
    }

    protected function determineTrafficLights(array $resources): array
    {
        $array = [];
        foreach ($resources as $resource) {
            $is_free = $resource['is_free'];
            $resource["traffic_light"] = $this->extractTrafficLight($is_free, $resource["licenses"]);

            $array[] = $resource;
        }
        return $array;
    }
}
