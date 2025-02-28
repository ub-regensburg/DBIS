<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Resources;

use App\Domain\Resources\Entities\Resource;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;

class GetCollectionsAction extends ResourcesBaseAction
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $contentType = $request->getHeaderLine('Content-Type');

        $organization_id = $request->getQueryParams()['organizationId'] ?? null;
        $collection_id = $request->getQueryParams()['collection'] ?? null;
        $offset = intval($request->getQueryParams()['offset'] ?? '0');
        $explain = isset($request->getQueryParams()['explain']);
        $limit = intval($request->getQueryParams()['limit'] ?? '20');
        $language = $request->getQueryParams()['language'] ?? null;
        $sort_by = intval($request->getQueryParams()['sort_by']) ?? null;

        $options = array(
            'organizationId' => $organization_id,
            'offset' => $offset,
            'limit' => $limit,
            'for_collection' => $collection_id,
            'sort_by' => $sort_by,
            'explain' => $explain,
            'with_total_nr' => true);

        $resources_with_total_nr = $this->service->getResources($options);
        $total_nr = $resources_with_total_nr['total_nr'];
        $has_next_entry = $total_nr > $limit + $offset;
        $has_prev_entry = $offset > $limit - 1;
        $resources = array_map(function (Resource $resource) use ($language) {
            return $language ? $resource->toI18nAssocArray($language) :  $resource->toAssocArray();
        }, (array)$resources_with_total_nr['resources']);

        $protocol = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
        $base_uri = $_SERVER['SERVER_NAME'] . ":" . $_SERVER['SERVER_PORT'];

        $next_page_url = $has_next_entry ?
            "{$protocol}://{$base_uri}/api/v1/resources?organizationId={$organization_id}"
            . "&offset=" . ($offset + $limit)
            . "&limit={$limit}"
            . "&language={$language}"
            . "&sort_by={$sort_by}" : null;

        $previous_page_url = $has_prev_entry ?
            "{$protocol}://{$base_uri}/api/v1/resources?organizationId={$organization_id}"
            . "&offset=" . ($offset - $limit)
            . "&limit={$limit}"
            . "&language={$language}"
            . "&sort_by={$sort_by}" : null;

        if ($collection_id) {
            $next_page_url = $has_next_entry ?
                "{$protocol}://{$base_uri}/api/v1/resources?organizationId={$organization_id}"
                . "&collection={$collection_id}"
                . "&offset=" . ($offset + $limit)
                . "&limit={$limit}"
                . "&language={$language}"
                . "&sort_by={$sort_by}" : null;

            $previous_page_url = $has_prev_entry ?
                "{$protocol}://{$base_uri}/api/v1/resources?organizationId={$organization_id}"
                . "&collection={$collection_id}"
                . "&offset=" . ($offset - $limit)
                . "&limit={$limit}"
                . "&language={$language}"
                . "&sort_by={$sort_by}" : null;
        }

        $data = array(
            'data' => array(
                'resources' => $resources,
                'total' => (int)$total_nr,
                'pageSize' => $limit,
                'currentPageIndex' => $offset),
            'links' => array(
                'next' => $next_page_url,
                'prev' => $previous_page_url)
        );

        $data = json_encode($data);
        $response->getBody()->write($data);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}
