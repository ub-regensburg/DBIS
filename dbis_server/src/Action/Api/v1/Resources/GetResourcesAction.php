<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Resources;

use App\Domain\Resources\Entities\Resource;
use App\Infrastructure\Shared\SearchClient;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;

class GetResourcesAction extends ResourcesBaseAction
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $contentType = $request->getHeaderLine('Content-Type');

        $query_params = $request->getQueryParams();

        $organization_id = $query_params['organization-id'] ?? null;
        $all = array_key_exists('all', $query_params) ? filter_var($query_params['all'], FILTER_VALIDATE_BOOLEAN) : false;
        $limit = array_key_exists('limit', $query_params) ? (int)$query_params['limit'] : 20;
        $offset = array_key_exists('offset', $query_params) ? (int)$query_params['offset'] : 0;
        $q = isset($query_params['q']) && strlen($query_params['q']) > 0 ?
            $query_params['q'] : null;
        $language = $query_params['language'] ?? "de";
        $subjects = array_key_exists('subjects', $query_params) ? $query_params['subjects'] : [];
        $collections = array_key_exists('collections', $query_params) ? $query_params['collections'] : [];
        $sort_by = array_key_exists('sort-by', $query_params) ? (int)$query_params['sort-by'] : null;

        $subjects = array_map(function ($subject_id) use ($language) {
            $subject_id = (int)$subject_id;
            $subject = $this->service->getSubjectById($subject_id);
            $subject = $subject->toI18nAssocArray($language);
            return $subject['title'];
        }, $subjects);

        $collections = array_map(function ($collection_id) use ($language, $organization_id) {
            $collection_id = (int)$collection_id;
            $collection = $this->service->getCollectionById($collection_id, $organization_id);
            $collection = $collection->toI18nAssocArray($language);
            return $collection['title'];
        }, $collections);

        $search_client = new SearchClient($organization_id);
        $match_all = true;

        if ($q && strlen($q) > 0) {
            $search_client->freeSearch($q);
            $match_all = false;
        }

        if ($match_all) {
            $search_client->matchAll();
        }

        if (!$all) {
            $global = true;
            $licensed = (bool)$organization_id;
            $unlicensed = false;
            $search_client->addAvailability($global, $licensed, $unlicensed, $organization_id);
        }

        foreach ($subjects as $subject) {
            $search_client->addSubject($subject);
        }

        foreach ($collections as $collection) {
            $search_client->addSubject($collection);
        }

        $from = $offset;
        $size = $limit;
        $search_client->setFrom($from);
        $search_client->setSize($size);

        if ($sort_by == ALPHABETICAL_SORTING) {
            $search_client->sortAlphabetically();
        }

        $results = $search_client->searchViaDsl();

        $total_nr = (int)$results['hits']['total']['value'];
        $has_next_entry = $total_nr > $limit + $offset;
        $has_prev_entry = $offset > $limit - 1;

        $protocol = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
        $base_uri = $_SERVER['SERVER_NAME'] . ":" . $_SERVER['SERVER_PORT'];

        $allParam = $all == true ? "&all=$all" : "";

        $next_page_url = $has_next_entry ?
            "{$protocol}://{$base_uri}/api/v1/resources?organization-id={$organization_id}"
            . "&offset=" . ($offset + $limit)
            . "&limit={$limit}"
            . "&q={$q}"
            . "&language={$language}"
            . "&sort-by={$sort_by}" 
            . "$allParam" : null;

        if ($next_page_url && array_key_exists('subjects', $query_params)) {
            $subjects_param = http_build_query(array('subjects' => $query_params['subjects']));
            $next_page_url .= "&$subjects_param";
        }

        if ($next_page_url && array_key_exists('collections', $query_params)) {
            $collections_param = http_build_query(array('collections' => $query_params['collections']));
            $next_page_url .= "&$collections_param";
        }

        $previous_page_url = $has_prev_entry ?
            "{$protocol}://{$base_uri}/api/v1/resources?organization-id={$organization_id}"
            . "&offset=" . ($offset - $limit)
            . "&limit={$limit}"
            . "&q={$q}"
            . "&language={$language}"
            . "&sort-by={$sort_by}" 
            . "$allParam" : null;

        if ($previous_page_url && array_key_exists('subjects', $query_params)) {
            $subjects_param = http_build_query(array('subjects' => $query_params['subjects']));
            $previous_page_url .= "&$subjects_param";
        }

        if ($previous_page_url && array_key_exists('collections', $query_params)) {
            $collections_param = http_build_query(array('collections' => $query_params['collections']));
            $previous_page_url .= "&$collections_param";
        }

        $data = array(
            'data' => array(
                'resources' => $search_client->transformResults($results),
                'total' => $total_nr,
                'pageSize' => $limit,
                'currentPageIndex' => $offset,
            ),
            'links' => array(
                'next' => $next_page_url,
                'prev' => $previous_page_url)
        );

        $data = json_encode($data);
        $response->getBody()->write($data);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}
