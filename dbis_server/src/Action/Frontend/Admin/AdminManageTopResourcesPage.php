<?php

declare(strict_types=1);

namespace App\Action\Frontend\Admin;

use App\Domain\Organizations\OrganizationService;
use App\Domain\Resources\Entities\Resource;
use App\Infrastructure\Shared\CountryProvider;
use App\Domain\Resources\ResourceService;
use App\Domain\Shared\AuthService;
use App\Infrastructure\Shared\ResourceProvider;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;

/**
 * AdminCreateDatabasePage
 *
 * Form for creating a new organization
 */
final class AdminManageTopResourcesPage extends AdminBasePage
{
    protected ResourceService $resourceService;
    protected OrganizationService $organizationService;

    public function __construct(
        ResourceProvider $rp,
        AuthService $auth,
        OrganizationService $service,
        CountryProvider $countryProvider,
        ResourceService $resourceService
    ) {
        $this->resourceService = $resourceService;
        $this->organizationService = $service;
        parent::__construct($rp, $auth, $service, $countryProvider, $resourceService);
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // set organisation according to route parameter
        if ($request->getAttribute('ubrId')) {
            parent::setAdministratedOrganization($request->getAttribute('ubrId'));
        }
        $organization_id = $this->getSelectedOrganizationIdFromSession();

        if (!$this->authService->isSessionAuthenticated()) {
            return $response->withHeader('Location', '/admin/login')->withStatus(302);
        } elseif ($organization_id && !$this->user->isAdminFor($organization_id) && !$this->user->isSubjectSpecialistFor($organization_id)) {
            return $response->withHeader('Location', '/admin')->withStatus(302);
        }

        if ($request->getMethod() == "GET") {
            return $this->handleGetRequest($request, $response);
        } elseif ($request->getMethod() == "PUT") {
            return $this->handleUpdateRequest($request, $response);
        }
    }

    private function handleGetRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        $orgId = $request->getAttribute('ubrId');

        $subjectId = $request->getAttribute("subjectId");
        $collectionId = $request->getAttribute("collectionId");

        $updatedSuccessfully = isset($request->getQueryParams()["updated_successfully"]) ?
                $request->getQueryParams()["updated_successfully"] : false;
        $language = $this->authService->getAuthenticatedUser()->getLanguage();

        $organization = $orgId ? $this->organizationService->getOrganizationByUbrId($orgId) : null;

        if ($subjectId || $collectionId) {
            $this->params['is_updated_successfully'] = $updatedSuccessfully;

            if ($subjectId) {
                $selectedSubject = $this->resourceService->getSubjectById((int) $subjectId);
                $this->params['selectedSubject'] = $selectedSubject ?
                    $selectedSubject->toI18nAssocArray($language) :
                    null;

                // Get Resources and separate selected topresources and available resources
                $resources = $this->resourceService->getTopResources([
                    "for_subject" => $subjectId
                ], $orgId);

                $availableResources = array_filter(
                    $resources,
                    function (Resource $resource) use ($selectedSubject) {
                        return !$resource->isTopEntryFor($selectedSubject);
                    }
                );
                $this->params['resources'] = array_map(function ($item) use ($language) {
                    return $item->toI18nAssocArray($language);
                }, $availableResources);

                $topResources = array_filter(
                    $resources,
                    function (Resource $resource) use ($selectedSubject) {
                        return $resource->isTopEntryFor($selectedSubject);
                    }
                );

                // sort by sort_order
                $this->params['topResources'] = array_map(function (Resource $resource)
 use ($language, $selectedSubject) {
                    $entry = $resource->toI18nAssocArray($language);
                    $entry['sortOrder'] = $resource->getTopResourceEntryForSubject($selectedSubject)->getOrder();
                    return $entry;
                }, $topResources);
            } else {
                $subjectId = $collectionId;

                $selectedSubject = $this->resourceService->getCollectionById((int) $subjectId, $orgId);
                $this->params['selectedSubject'] = $selectedSubject ?
                    $selectedSubject->toI18nAssocArray($language) :
                    null;

                // $resources = $selectedSubject->getResources();
                $resources = $this->resourceService->getTopResources([
                    "for_collection" => $subjectId
                ], $orgId);

                $availableResources = array_filter(
                    $resources,
                    function (Resource $resource) use ($selectedSubject) {
                        return !$resource->isTopEntryFor($selectedSubject);
                    }
                );

                $this->params['resources'] = array_map(function ($item) use ($language) {
                    return $item->toI18nAssocArray($language);
                }, $availableResources);

                $topResources = array_filter(
                    $resources,
                    function (Resource $resource) use ($selectedSubject) {
                        return $resource->isTopEntryFor($selectedSubject);
                    }
                );

                $this->params['topResources'] =
                    array_map(function (Resource $resource) use ($language, $selectedSubject) {
                        $entry = $resource->toI18nAssocArray($language);
                        $entry['sortOrder'] = $resource->getTopResourceEntryForSubject($selectedSubject)->getOrder();
                        return $entry;
                    }, $topResources);
            }
        } else {
            $subjects = $this->resourceService->getResourceAggregatesHandledAsSubject(
                ['include_collections' => true,
                'organizationId' => $orgId,
                'without_resources' => true]
            );

            usort($subjects, fn($a, $b) => strnatcasecmp($a->getTitle()[$language], $b->getTitle()[$language]));

            $this->params['subjects'] = array_map(function ($entry) use ($language) {
                return $entry->toI18nAssocArray($language);
            }, $subjects);
        }

        $view = Twig::fromRequest($request);
        // TODO: get user language for translation

        $this->params['pageTitle'] = $this->resourceProvider->getText("tab_label_topdb", $language);
        $this->params['organization'] = $organization ? $organization->toI18nAssocArray($language) : null;

        return $view->render(
            $response,
            'admin/manage_topdbs.twig',
            $this->params
        );
    }

    private function handleUpdateRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        $orgId = $request->getAttribute('ubrId');
        $subjectId = $request->getAttribute("subjectId");
        $collectionId = $request->getAttribute("collectionId");

        $body = $request->getParsedBody();
        $resourceIds = $body["resources"] ?? [];

        if ($orgId) {
            if ($subjectId) {
                $subject = $this->resourceService->getSubjectById((int) $subjectId);
                /*
                $resources = array_map(function ($resourceId) use ($orgId) {
                    return $this->resourceService->getResourceById_NEW((int) $resourceId, $orgId);
                }, $resourceIds);
                */
                $this->resourceService->clearTopResourceEntriesForSubject($subject, $orgId);
                /* @var $resource Resource */
                foreach ($resourceIds as $idx => $resourceId) {
                    $this->resourceService->setTopEntryForSubject((int) $resourceId, $subjectId, $idx, $orgId);
                    // $resource->setTopEntryFor($subject, $idx, $orgId);
                    // $this->resourceService->updateResource($resource, $orgId);
                }
            } else {
                $collection = $this->resourceService->getCollectionById((int) $collectionId, $orgId);
                /*
                $resources = array_map(function ($resourceId) use ($orgId) {
                    return $this->resourceService->getResourceById_NEW((int) $resourceId, $orgId);
                }, $resourceIds);
                */

                // TODO
                $this->resourceService->clearTopResourceEntriesForCollection($collection, $orgId);
                /* @var $resource Resource */
                foreach ($resourceIds as $idx => $resourceId) {
                    $this->resourceService->setTopEntryForCollection((int) $resourceId, $collectionId, $idx, $orgId);
                    // $resource->setTopEntryForCollection($collection, $idx, $orgId);
                    // $this->resourceService->updateResource($resource, $orgId);
                }
            }
        }
            
        $url = $_SERVER['REQUEST_URI'];
        $url = explode('?', $url)[0];
        header("Location: {$url}?updated_successfully=1", true, 303);
        exit();
    }
}
