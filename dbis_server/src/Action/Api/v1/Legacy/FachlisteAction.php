<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Legacy;

use App\Action\Api\v1\Resources\ResourcesBaseAction;
use App\Domain\Organizations\Exceptions\OrganizationWithDbisIdNotExistingException;
use App\Domain\Organizations\Exceptions\OrganizationWithUbrIdNotExistingException;
use App\Domain\Resources\Entities\Subject;
use App\Infrastructure\Shared\SearchClient;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use App\Infrastructure\Shared\XMLGenerator;

class FachlisteAction extends ResourcesBaseAction
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $xmloutput = $request->getQueryParams()['xmloutput'] ?? false;
        $lang = $request->getQueryParams()['lang'] ?? 'de';

        // i have no idea why ub_r is default here if anything fails...
        // (see DetailAction.php for some rudimentary error response (return HTTP 404 with message))

        $FALLBACK_UBR_ID = 'UBR';

        $ubr_id = $this->extractUbrIdFromQueryParams($request);
        if (!$ubr_id) {
            $ubr_id = $FALLBACK_UBR_ID;
        }

        if (!$xmloutput) {
            // LEGACY ROUTE:
            // redirect regular queries (i.e. for non-XML) to the current way of accessing an organization entry
            return $response->withHeader('Location', '/'.$ubr_id.'/browse/subjects/')->withStatus(301);
        } // else: xmloutput=1

        try {
            $organization = $this->orgService->getOrganizationByUbrId($ubr_id);
        } catch (OrganizationWithUbrIdNotExistingException $e) {
            $organization = $this->orgService->getOrganizationByUbrId($FALLBACK_UBR_ID);
        }

        $options = ['sort_language' => $lang,
            'organizationId' => $ubr_id,
            'include_collections' => true,
            //'only_with_license' => $onlyWithLicense,
            'without_resources' => true];
        $subjects = $this->service->getResourceAggregatesHandledAsSubject($options);

        $resourcesBySubject = [];
        foreach ($subjects as $subject) {
            $resourcesBySubject[$subject->getId()] = $this->getResourceCount($ubr_id, $lang, $subject->getId());
        }

        $xml_generator = new XMLGenerator();
        $data = $xml_generator->generateSubjectsForLegacyFachlistePage($subjects, $resourcesBySubject, $organization);

        $response->getBody()->write($data);
        return $response->withHeader('Content-Type', 'application/xml')->withStatus(200);
    }
    private function getResourceCount($organizationId, $language, $subjectId) {
        $search_client = new SearchClient($organizationId, $language);
        $search_client->matchAll();
        $search_client->addAvailability(true, true, false);
        $search_client->addSubject('', $subjectId);
        $search_client->showOnlyVisibleResources($organizationId);

        $results = $search_client->searchViaDsl();
        return (int) $results['hits']['total']['value'];
    }
}
