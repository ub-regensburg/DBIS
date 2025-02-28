<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Resources;

use App\Domain\Resources\Entities\Subject;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use App\Infrastructure\Shared\XMLGenerator;

class GetSubjectsAction extends ResourcesBaseAction
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $contentType = $request->getHeaderLine('Content-Type') ?? 'application/json';
        
        $organization_id = $request->getQueryParams()['organization-id'] ?? null;

        $format = $request->getQueryParams()['format'] ?? null;

        $lang = $request->getQueryParams()['language'] ?? 'de';

        $options = ['sort_language' => $lang,
            'organizationId' => $organization_id,
            'without_resources' => true];

        $subjects = array_map(function (Subject $subject) use ($lang) {
            return $subject->toI18nAssocArray($lang);
        }, $this->service->getSubjects($options));
        $subjects = array_map(function ($subject) {
            unset($subject["type"]);
            unset($subject["is_visible"]);
            unset($subject["resource_ids"]);
            return $subject;
        }, $subjects);

        

        if (str_contains($contentType, 'application/xml') || $format == "xml") {
            $xml_generator = new XMLGenerator();

            $organization = null;
            if ($organization_id) {
                $organization = $this->orgService->getOrganizationByUbrId($organization_id);
                $organization = $organization->toI18nAssocArray($lang);
            }

            $data = $xml_generator->generateSubjects($subjects, $organization);

            $response->getBody()->write($data);
            return $response->withHeader('Content-Type', 'application/xml')->withStatus(200);
        } else {
            $data = json_encode($subjects);

            $response->getBody()->write($data);
            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
        }
    }
}
