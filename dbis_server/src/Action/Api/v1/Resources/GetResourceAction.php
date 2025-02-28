<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Resources;

use App\Infrastructure\Resources\ResourceRepository;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use App\Infrastructure\Shared\XMLGenerator;

class GetResourceAction extends ResourcesBaseAction
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $contentType = $request->getHeaderLine('Content-Type') ?? 'application/json';

        $format = $request->getQueryParams()['format'] ?? null;

        $language = $request->getQueryParams()['language'] ?? 'de';

        $organization_id = $request->getQueryParams()['organizationId'] ?? null;
        $id = $request->getAttribute("resourceId") ? (int) $request->getAttribute("resourceId") : null;

        if ($id) {
            $resource = $this->service->getResourceById_NEW($id, $organization_id)->toI18nAssocArray($language);

            unset($resource['overwrite']);
            unset($resource['shelfmark']);
            unset($resource['shelfmark_group']);
            unset($resource['shelfmark_numbers']);
            unset($resource['shelfmark_description']);
            unset($resource['created_by']);
            foreach ($resource['subjects'] as &$subject) {
                unset($subject["type"]);
                unset($subject["is_visible"]);
                unset($subject["resource_ids"]);
            }

            if (str_contains($contentType, 'application/xml') || $format == "xml") {
                $xml_generator = new XMLGenerator();

                $organization = null;
                if ($organization_id) {
                    $organization = $this->orgService->getOrganizationByUbrId($organization_id);
                    $organization = $organization->toI18nAssocArray($language);
                }

                $data = $xml_generator->generateResource($resource, $organization);
    
                $response->getBody()->write($data);
                return $response->withHeader('Content-Type', 'application/xml');
            } else {
                $data = json_encode($resource);

                $response->getBody()->write($data);
                return $response->withHeader('Content-Type', 'application/json');
            }
        } else {
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }
}
