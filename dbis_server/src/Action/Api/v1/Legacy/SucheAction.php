<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Legacy;

use App\Action\Api\v1\Resources\ResourcesBaseAction;
use App\Domain\Resources\Entities\Resource;
use App\Domain\Resources\Entities\Subject;
use App\Infrastructure\Shared\SearchClient;
use App\Infrastructure\Shared\XMLGenerator;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;

class SucheAction extends ResourcesBaseAction
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $dbis_id = $request->getQueryParams()['bib_id'] ?? null;
        $ubr_id = $dbis_id ? $this->orgService->getUbrIdForDbisId($dbis_id) : null;
        unset($dbis_id);

        $lang = $query_params['lang'] ?? "de";
        $options = ['sort_language' => $lang,
            'organizationId' => $ubr_id,
            'without_resources' => true];

        $subjects = array_map(function (Subject $subject) use ($lang) {
            return $subject->toI18nAssocArray($lang);
        }, $this->service->getSubjects($options));

        $dbTypes = $this->service->getTypes();
        $licenseTypes = $this->service->getLicenseTypes();
        $publicationForms = $this->service->getPublicationForms();
        $countries = $this->service->getCountries();

        $organization = null;
        if ($ubr_id) {
            $organization = $this->orgService->getOrganizationByUbrId($ubr_id);
            $organization = $organization->toI18nAssocArray($lang);
        }

        $xml_generator = new XMLGenerator();
        $data = $xml_generator->generateSubjectsForLegacySuchePage($subjects, $dbTypes, $licenseTypes, $publicationForms, $countries, $organization, $lang);

        $response->getBody()->write($data);
        return $response->withHeader('Content-Type', 'application/xml')->withStatus(200);
    }
}
