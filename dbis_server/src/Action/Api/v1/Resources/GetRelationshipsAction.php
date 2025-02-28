<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Resources;

use App\Domain\Resources\Entities\Host;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;

class GetRelationshipsAction extends ResourcesBaseAction
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $contentType = $request->getHeaderLine('Content-Type');

        $id = $request->getAttribute("resourceId") ? (int) $request->getAttribute("resourceId") : null;
        $language = $request->getQueryParams()['language'] ?? 'de';

        $relations = $this->service->getRelationships($id);

        $resources = array();

        foreach ($relations as $relation) {
            $resource_id = $relation['resource'];
            $related_resource_id = $relation['related_to_resource'];
            
            if (!array_key_exists($resource_id, $resources)) {
                $resources[$resource_id] = $this->service->getResourceById_NEW($resource_id)->toI18nAssocArray($language);
            }

            if (!array_key_exists($related_resource_id, $resources)) {
                $resources[$related_resource_id] = $this->service->getResourceById_NEW($related_resource_id)->toI18nAssocArray($language);
            }
        }

        $data = array(
            'relations' => $relations,
            'resources' => $resources,
            'language' => $language,
        );
        $data = json_encode($data);

        $response->getBody()->write($data);
        return $response->withHeader('Content-Type', 'application/json');
    }
}
