<?php

declare(strict_types=1);

namespace App\Action\Frontend\Users;

use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use App\Infrastructure\Shared\SearchClient;
use Slim\Views\Twig;

/**
 * UsersSearchPage
 *
 * Search page for users
 */
class UsersCollectionsListPage extends UsersBasePage
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

        $language = $_SESSION["language"] ?? "de";

        $collections = $this->service->getCollections(
            ['sort_language' => $language,
                'organizationId' => $organization_id,
                'only_visibles' => true,
                'only_subjects' => false]
        );

        usort($collections, fn($a, $b) => strnatcasecmp($a->getTitle()[$language], $b->getTitle()[$language]));

        $collectionsAssoc = array_map(function ($s) use ($organization_id, $language) {
            $subjectAssoc = $s->toI18nAssocArray($language);
            $subjectId = (int) $subjectAssoc['id'];
            $subjectAssoc['total_nr'] = $this->getCount($organization_id, $language, $subjectAssoc['title'], $subjectId);

            return $subjectAssoc;
        }, $collections);

        $collectionsAssoc = array_filter($collectionsAssoc, function ($c) {
            return $c['total_nr'] > 0;
        });

        $view = Twig::fromRequest($request);
        $this->params['pageTitle'] = $this->resourceProvider->getText("page_title_browse_subjects", $language);
        $this->params['collections'] = $collectionsAssoc;
        $this->params['organizationId'] = $organization_id;
        // Needs to be done here and not in parent class, as the organizationId is at last set in this invoke function
        $this->params['doesOrganizationHasCollections'] =
            $this->getSelectedOrganizationIdFromSession() != null && $this->doesOrganizationHasCollections() == true;

        return $view->render(
            $response,
            'users/browse_collections.twig',
            $this->params
        );
    }

    private function getCount($organizationId, $language, $collectionTitle, $collectionId) {
        $search_client = new SearchClient($organizationId, $language);

        $search_client->matchAll();

        $global = true;
        $licensed = true;
        $unlicensed = false;
        
        $search_client->addAvailability($global, $licensed, $unlicensed);

        $search_client->addCollection($collectionTitle, $collectionId);

        $search_client->showOnlyVisibleResources($organizationId);

        $results = $search_client->searchViaDsl();

        $total_nr = (int) $results['hits']['total']['value'];

        return $total_nr;
    }
}
