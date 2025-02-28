<?php

declare(strict_types=1);

namespace App\Action\Frontend\Admin;

use App\Domain\Organizations\OrganizationService;
use App\Domain\Resources\Entities\Collection;
use App\Domain\Resources\Entities\Resource;
use App\Domain\Resources\Entities\SortType;
use App\Domain\Resources\Entities\Type;
use App\Domain\Resources\ResourceService;
use App\Infrastructure\Shared\ResourceProvider;
use App\Domain\Shared\AuthService;
use App\Infrastructure\Shared\CountryProvider;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;

class AdminCollectionEditPage extends AdminBasePage
{
    protected ResourceService $resourceService;

    // TODO: OrganizationService should not be in the base class
    public function __construct(
        ResourceProvider $rp,
        AuthService $auth,
        OrganizationService $service,
        CountryProvider $countryProvider,
        ResourceService $resourceService
    ) {
        parent::__construct($rp, $auth, $service, $countryProvider, $resourceService);

        $this->resourceService = $resourceService;
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // Set organisation according to route parameter
        if ($request->getAttribute('ubrId')) {
            parent::setAdministratedOrganization($request->getAttribute('ubrId'));
        }

        // simple auth guard
        if (!$this->authService->isSessionAuthenticated()) {
            return $response->withHeader('Location', '/admin/login');
        } elseif (!$this->isSuperAdmin && !$this->isAdmin) {
            return $response->withHeader('Location', '/admin');
        }

        if ($request->getMethod() == "GET") {
            return $this->handleGetRequest($request, $response);
        } elseif ($request->getMethod() == "PUT") {
            return $this->handleUpdateRequest($request, $response);
        } elseif ($request->getMethod() == "POST") {
            return $this->handleCreateRequest($request, $response);
        }
    }

    private function handleGetRequest(ServerRequestInterface $request, ResponseInterface $response)
    {
        $localOrganizationId = $this->administeredOrganization->getUbrId() ?: null;

        $id = $request->getAttribute('id') ? (int)$request->getAttribute('id') : null;

        $collection = $id ? $this->resourceService->getCollectionById($id, $localOrganizationId) : null;

        return $this->renderPage($request, $response, $collection);
    }

    private function handleUpdateRequest(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getQueryParams();
        
        $localOrganizationId = $this->administeredOrganization->getUbrId() ?
            $this->administeredOrganization->getUbrId() : null;
        $id = (int)$request->getAttribute('id');

        $parsed_body = $request->getParsedBody();
        $collection = $this->parseRequestBody($parsed_body);
        $collection->setId($id);

        $resourceIds = array_map(function ($item) {
            return (int)($item);
        }, $parsed_body['resources']);
        $collection->setResourceIds($resourceIds);

        if (isset($parsed_body['delete'])) {
            $this->resourceService->deleteCollection($collection, $localOrganizationId);
            $url = "/admin/manage/{$localOrganizationId}/collections/";
            // header("Location: {$url}?deleted_successfully=1", true, 303);
            header("Location: {$url}?deleted_successfully=1", true, 303);
            exit();
        }

        $errors = $collection->validate();
        $this->params['errors'] = $errors;
        if (count($errors) == 0) {
            $this->resourceService->updateCollection($collection, $localOrganizationId);
            $url = str_replace("/new/", "/{$id}/", $_SERVER['REQUEST_URI']);
            $url = explode('?', $url)[0];
            header("Location: {$url}?updated_successfully=1", true, 303);
            exit();
        } else {
            unset($params['updated_successfully']);
            unset($params['created_successfully']);
            $request = $request->withQueryParams($params);

            return $this->renderPage($request, $response, $collection, $errors);
        }
    }

    private function handleCreateRequest(ServerRequestInterface $request, ResponseInterface $response)
    {
        $localOrganizationId = $this->administeredOrganization->getUbrId() ?: null;
        $collection = $this->parseRequestBody($request->getParsedBody());
        $errors = $collection->validate();
        $this->params['errors'] = $errors;
        if (count($errors) == 0) {
            $id = $this->resourceService->createCollection($collection, $localOrganizationId);

            $url = str_replace("/new/", "/{$id}/", $_SERVER['REQUEST_URI']);
            $url = explode('?', $url)[0];
            header("Location: {$url}?created_successfully=1", true, 303);
            exit();
        } else {
            return $this->renderPage($request, $response, $collection, $errors);
        }
    }

    private function renderPage(
        ServerRequestInterface $request,
        ResponseInterface $response,
        ?Collection $collection = null,
        array $errors = []
    ) {
        $view = Twig::fromRequest($request);
        $organization = $this->administeredOrganization;
        $organization_id = $request->getAttribute('ubrId');

        $language = $this->authService->getAuthenticatedUser()->getLanguage();

        $this->params['pageTitle'] = $this->resourceProvider->getText(
            $collection ? "h_collection_edit" : "h_collection_create",
            $language
        );

        $this->params['organization'] = $organization ? $organization->toI18nAssocArray($language) : null;

        $sort_types = array_map(function (SortType $sort_type) use ($language) {
            return $sort_type->toI18nAssocArray($language);
        }, $this->resourceService->getSortTypes());
        array_shift($sort_types);  // Remove sort by relevance for now, since we implement this feature later on
        array_pop($sort_types);  // Remove custom sorting
        
        $this->params['sort_types'] = $sort_types;

        $this->params['is_created_successfully'] = array_key_exists(
            "created_successfully",
            $request->getQueryParams()
        );
        $this->params['is_updated_successfully'] = array_key_exists(
            "updated_successfully",
            $request->getQueryParams()
        );

        $this->params['errors'] = $errors;
        $this->params['is_created_successfully'] = array_key_exists(
            "created_successfully",
            $request->getQueryParams()
        );
        $this->params['is_updated_successfully'] = array_key_exists(
            "updated_successfully",
            $request->getQueryParams()
        );

        $this->params['collection'] = $collection ? $collection->toAssocArray() : null;

        if ($this->params['collection']) {
            $this->params['collection']['resources'] = array_map(function ($resource_id) use ($organization_id, $language) {
                $resource = $this->resourceService->getResourceById_NEW($resource_id, $organization_id);
                return $resource->toI18nAssocArray($language);
            }, $this->params['collection']['resource_ids']);
        } else {
            $this->params['collection']['resources'] = [];
        }
        

        return $view->render(
            $response,
            'admin/edit_collection.twig',
            $this->params
        );
    }

    private function parseRequestBody($body): Collection
    {
        $title = array('de' => $this->purifier->purify($body['title_de']), 'en' => $this->purifier->purify($body['title_en']));
        $sort_by_obj = $body['sort_by'] ?
            new SortType((int)$body['sort_by']) : null;
        $is_visible = isset($body['is_visible']);
        $is_subject = isset($body['is_subject']);

        $resourceIds = array_map(function (string $entry) {
            return (int) $entry;
        }, $body['resources'] ?? []);

        $collection = new Collection($title);
        $collection->setIsVisible($is_visible);
        $collection->setIsSubject($is_subject);
        $collection->setSortBy($sort_by_obj);
        $collection->setResourceIds($resourceIds);


        return $collection;
    }
}
