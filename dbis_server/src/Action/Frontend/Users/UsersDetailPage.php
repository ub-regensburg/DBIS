<?php

declare(strict_types=1);

namespace App\Action\Frontend\Users;

use App\Domain\Organizations\Entities\Organization;
use App\Domain\Organizations\Exceptions\OrganizationWithUbrIdNotExistingException;
use App\Infrastructure\Shared\Exceptions\LanguageNotFoundException;
use App\Infrastructure\Shared\Exceptions\ResourceNotFoundException;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;

/**
 * UsersSearchPage
 *
 */
class UsersDetailPage extends UsersBasePage
{
    /**
     * @throws ResourceNotFoundException
     * @throws LanguageNotFoundException
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $queryParams = $request->getQueryParams();
        $contextUrl = $this->contextProvider->getUrl();
        $resultIds = $this->contextProvider->getResultIds();

        // Set organisation according to route parameter which is done after session and ip test in parent constructor
        // But first the if the orgId exists otherwise the session gets unset.
        if ($request->getAttribute('organizationId')) {
            parent::setSelectedOrganization($request->getAttribute('organizationId'));
        }

        $organization_id = $this->getSelectedOrganizationIdFromSession();

        if ($organization_id == "ALL") {
            $organization_id = null;
        }

        // Handle both, legacy access (via query params) and current access (via route param)
        $resourceId = (int)($request->getAttribute('resourceId')
                ?? $queryParams['titel_id'] ?? null);

        $resourceGlobal = $this->service->getResourceById_NEW($resourceId, null);
        if (!$resourceGlobal) {
            $response->getBody()->write('No resource found with ID '.$resourceId.'.');
            return $response->withHeader('Content-Type', 'text/plain')->withStatus(404);
        }
        $resourceAssocGlobal = $resourceGlobal->toI18nAssocArray($this->language);

        $resource = null;
        $resourceAssocLocal = null;

        if (!is_null($organization_id) && $organization_id !== 'ALL') {
            $resource = $this->service->getResourceById_NEW($resourceId, $organization_id);

            // TODO: Filter license accesses according to organization

            $resourceAssoc = $resource->toI18nAssocArray($this->language);
            $resourceAssocLocal = $resourceAssoc;
        } else {
            $resource = $resourceGlobal;

            // TODO: Filter license accesses according to organization

            $resourceAssoc = $resourceAssocGlobal;
        }

        $relations = $this->service->getRelationships($resourceId);
        $related_resources = array('is-related' => array(), 'is-child' => array(), 'is-parent' => array());

        foreach ($relations as $relation) {
            $resource_id = $relation['resource'];
            $related_resource_id = $relation['related_to_resource'];
            $relationship_type = $relation['relationship_type'];
            
            if (!array_key_exists($resource_id, $related_resources[$relationship_type])) {
                if ($resource_id != $resourceId) {
                    $new_relationship_type = $relationship_type;
                    if ($relationship_type == 'is-parent') {
                        $new_relationship_type = 'is-child';
                    } elseif ($relationship_type == 'is-child') {
                        $new_relationship_type = 'is-parent';
                    } else {
                        $new_relationship_type = 'is-related';
                    }
                    $related_resources[$new_relationship_type][$resource_id] = $this->determineTrafficLight($this->service->getResourceById_NEW($resource_id, $organization_id)->toI18nAssocArray($this->language), false);
                }
            }

            if (!array_key_exists($related_resource_id, $related_resources[$relationship_type])) {
                if ($related_resource_id != $resourceId) {
                    $related_resources[$relationship_type][$related_resource_id] = $this->determineTrafficLight($this->service->getResourceById_NEW($related_resource_id, $organization_id)->toI18nAssocArray($this->language), false);
                }
            }
        }

        $is_requested_from_direct_link = isset($queryParams['direct-link']) && (bool)$queryParams['direct-link'];

        $view = Twig::fromRequest($request);

        $licenses = $resourceAssoc['licenses'];

        $is_free = $resourceAssocGlobal['is_free'];
        // If is free and org has no license, get one.
        if ($is_free && count($licenses) < 1) {
            $freeResource = $this->service->getFreeResourceWithLicenseOnly($resourceId);
            if ($freeResource) {
                $freeResourceAssoc = $freeResource->toI18nAssocArray($this->language);
                if (count($freeResourceAssoc['licenses']) > 0) {
                    $licenses = $freeResourceAssoc['licenses'];
                }
            }
        }

        $licenses = array_filter($licenses, function($item) {
            return isset($item['isActive']) && $item['isActive'] == true;
        });

        // Filter sorted accesses
        $sortedAccesses = $this->sortAccessesByEstimatedValue($licenses);
        $sortedAccesses = array_filter($sortedAccesses, function($access) {
            return isset($access['is_visible']) && $access['is_visible'] === true;
        });
        $this->params['accesses'] = $sortedAccesses;

        // Filter accesses
        foreach($licenses as &$license) {
            if ($license['fid']) {
                $fidOrg = $this->organizationService->getOrganizationByUbrId($license['fid']);
                $license['fidName'] = $fidOrg->getName()[$this->language];
            } else {
                $license['fidName'] = null;
            }

            $filteredAccesses = array_filter($license['accesses'], function($access) {
                return isset($access['is_visible']) && $access['is_visible'] === true;
            });

            $license['accesses'] = $filteredAccesses;
        }

        $organisationsWithLicense = $this->service->getOrganisationsWithLicense($resourceId);

        $language = $this->language;
        $organisationsWithLicense = array_map(function ($item) use ($language) {
            $organisationWithLicense = null;

            try {
                $organisationWithLicense = $this->organizationService->getOrganizationByUbrId($item['organization']);
            }
            catch(OrganizationWithUbrIdNotExistingException $ex) {
                $organisationWithLicense = null;
            }
            
            if (!is_null($organisationWithLicense)) {
                // $item['organization'] = $organisationWithLicense->toI18nAssocArray($language);
                return $organisationWithLicense;
            }
        }, $organisationsWithLicense);

        $organisationsWithLicense = array_filter($organisationsWithLicense);

        $organisationsWithLicense = $this->groupOrganizationsByCity(array_map(function (Organization $o) use ($language) {
            return $o->toI18nAssocArray($language);
        }, $organisationsWithLicense));

        $this->params['licenses'] = $licenses;

        $this->params['organizationId'] = $organization_id;

        $this->params['related_resources'] = $related_resources;
        $this->params['relations'] = $relations;

        // clean and escape titles
        $resourceAssocGlobal['title'] = $this->decode_safe($resourceAssocGlobal['title']); 
        if ($resourceAssocLocal) {
            $resourceAssocLocal['title'] = $this->decode_safe($resourceAssocLocal['title']); 
        }
        $resourceAssoc['title'] = $this->decode_safe($resourceAssoc['title']); 
        
        // clean and escape alternative titles
        $resourceAssocGlobal['alternative_titles'] = $this->escape_alternative_titles($resourceAssocGlobal['alternative_titles']);

        $this->params['resource'] = $this->determineTrafficLight($resourceAssoc, false);
        $this->params['resourceLocal'] = $resourceAssocLocal;
        $this->params['resourceGlobal'] = $resourceAssocGlobal;

        $this->params['contextUrl'] = $contextUrl;
        $this->params['resultIds'] = $resultIds;
        $this->params['organisations_with_license'] = $organisationsWithLicense;
        $this->params['isRequestedFromDirectLink'] = $is_requested_from_direct_link;
        $this->params['pageTitle'] =  $this->resourceProvider->getText("title_users_detail_page", $this->language) . $resourceAssoc['title'];
        // Needs to be done here and not in parent class, as the organizationId is at last set in this invoke function
        $this->params['doesOrganizationHasCollections'] =
            $this->getSelectedOrganizationIdFromSession() != null && $this->doesOrganizationHasCollections() == true;

        return $view->render(
            $response,
            'users/detail.twig',
            $this->params
        );
    }

    private function escape_alternative_titles(array $titles) {
        return array_map(function ($item) {
            // Ensure it's an array and has a 'title' field
            if (is_array($item) && isset($item['title'])) {
                $item['title'] = $this->decode_safe($item['title']);
            }
            return $item;
        }, $titles);
    }

}
