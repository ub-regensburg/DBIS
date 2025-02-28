<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Resources;

use App\Domain\Resources\Entities\Resource;
use App\Infrastructure\Shared\SearchClient;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;

class GetResourcesGlobalAction extends ResourcesBaseAction
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $query_params = $request->getQueryParams();

        $search_client = new SearchClient(null, 'de');

        $global = true;
        $licensed = false;
        $unlicensed = false;
        $search_client->addAvailability($global, $licensed, $unlicensed);
        $search_client->showOnlyFreeResources();

        $search_client->setFrom(0);
        $search_client->setSize(10000);

        $search_client->enableSourceFiltering(['resource_id']);
        $results = $search_client->searchViaDsl();
        $data = $search_client->transformResults($results);
        $data = json_encode($data);
        $response->getBody()->write($data);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}
