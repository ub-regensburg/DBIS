<?php

declare(strict_types=1);

namespace App\Action\Frontend\Users;

use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;
use App\Domain\Resources\Entities\Subject;
use App\Infrastructure\Shared\SearchClient;

/**
 * UsersSearchPage
 *
 * Search page for users
 */
class UsersSubjectsListPage extends UsersBasePage
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // Set organisation according to route parameter which is done after session and ip test in parent constructor
        // But first the if the orgId exists otherwise the session gets unset.
        if ($request->getAttribute('organizationId')) {
            parent::setSelectedOrganization($request->getAttribute('organizationId'));
        }
        $organization_id = $this->getSelectedOrganizationIdFromSession();

        $includeCollections = true;
        if ($organization_id == "ALL") {
            $organization_id = null;
            $includeCollections = false;
        }

        $language = $_SESSION["language"] ?? "de";
        // If organization is set, show only databases with license
        $onlyWithLicense = isset($organization_id);

        $subjects = $this->service->getResourceAggregatesHandledAsSubject(
            ['sort_language' => $language,
                'include_collections' => $includeCollections,
                'only_with_license' => $onlyWithLicense,
                'organizationId' => $organization_id,
                'without_resources' => true]
        );

        usort($subjects, fn($a, $b) => strnatcasecmp($a->getTitle()[$language], $b->getTitle()[$language]));

        $subjectsAssoc = array_map(function ($s) use ($organization_id, $language) {
            $subjectAssoc = $s->toI18nAssocArray($language);
            $subjectId = (int) $subjectAssoc['id'];
            if ($subjectAssoc['is_visible']) {
                $subjectAssoc['total_nr'] = $this->getCount($organization_id, $language, $subjectAssoc['title'], $subjectId);
            } else {
                $subjectAssoc['total_nr'] = 0;
            }
            
            return $subjectAssoc;
        }, $subjects);

        $subjectsAssoc = array_filter($subjectsAssoc, function ($s) {
            return $s['total_nr'] > 0 && $s['is_visible'] == true;
        });

        $view = Twig::fromRequest($request);
        $this->params['pageTitle'] = $this->resourceProvider->getText("page_title_browse_subjects", $language);
        $this->params['subjects'] = $subjectsAssoc;
        $this->params['organizationId'] = $organization_id;
        // Needs to be done here and not in parent class, as the organizationId is at last set in this invoke function
        $this->params['doesOrganizationHasCollections'] =
            $this->getSelectedOrganizationIdFromSession() != null && $this->doesOrganizationHasCollections() == true;

        return $view->render(
            $response,
            'users/browse_subjects.twig',
            $this->params
        );
    }

    private function getCount($organizationId, $language, $subjectTitle, $subjectId = null) {
        $search_client = new SearchClient($organizationId, $language);

        $search_client->matchAll();

        $global = true;
        $licensed = true;
        $unlicensed = false;
        
        $search_client->addAvailability($global, $licensed, $unlicensed);

        $search_client->addSubject($subjectTitle, $subjectId);

        $search_client->showOnlyVisibleResources($organizationId);

        $results = $search_client->searchViaDsl();

        $total_nr = (int) $results['hits']['total']['value'];

        return $total_nr;
    }
}
