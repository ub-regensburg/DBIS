<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Resources;

use App\Domain\Resources\Entities\Resource;
use App\Infrastructure\Shared\SearchClient;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;

class GetResourcesBySubjectAction extends ResourcesBaseAction
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $query_params = $request->getQueryParams();
        $subject_id = $request->getAttribute("subjectId") ? (int) $request->getAttribute("subjectId") : null;
        $subject = $this->service->getSubjectById($subject_id);

        if (!$subject) {
            $response->getBody()->write('No subject with id '.$subject_id.' found.');
            return $response->withHeader('Content-Type', 'text/plain')->withStatus(404);
        }

        $organization_id = $query_params['organization-id'] ?? null;
        $search_client = new SearchClient($organization_id, 'de');
        $search_client->addSubject($subject->getTitle()['de']);
        $search_client->enableSourceFiltering(['resource_id']);
        $results = $search_client->searchViaDsl();
        $data = $search_client->transformResults($results);
        $data = json_encode($data);
        $response->getBody()->write($data);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}
