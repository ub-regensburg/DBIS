<?php

declare(strict_types=1);

namespace App\Action\Frontend\Admin;

use App\Domain\Resources\Entities\LicenseForm;
use App\Domain\Resources\Exceptions\LicenseAlreadyExistingException;
use App\Domain\Resources\Exceptions\LicenseNotFoundException;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;
use App\Domain\Resources\ResourceService;
use App\Domain\Resources\Entities\Resource;
use App\Domain\Resources\Entities\License;
use App\Domain\Resources\Entities\LicenseType;
use App\Domain\Resources\Entities\Access;
use App\Domain\Resources\Entities\AccessType;
use App\Domain\Resources\Entities\AccessForm;
use App\Domain\Resources\Entities\Host;
use App\Domain\Resources\Entities\Enterprise;
use App\Domain\Resources\Entities\PublicationForm;
use App\Domain\Resources\Entities\ExternalID;
use App\Infrastructure\Shared\CountryProvider;
use App\Domain\Shared\AuthService;
use App\Infrastructure\Shared\ResourceProvider;
use App\Domain\Organizations\OrganizationService;
use App\Domain\Resources\Entities\LicenseLocalisation;

/**
 * AdminEditLicenseForResourcePage
 *
 */
class AdminEditLicenseForResourcePage extends AdminBasePage
{
    private ResourceService $resourceService;

    public function __construct(
        ResourceProvider $rp,
        AuthService $auth,
        CountryProvider $countryProvider,
        OrganizationService $service,
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
        // Here, the parameter is not ubrId, its organizationId
        if ($request->getAttribute('organizationId')) {
            parent::setAdministratedOrganization($request->getAttribute('organizationId'));
        }

        // simple auth guard
        if (!$this->authService->isSessionAuthenticated()) {
            header("Location: /admin/login");
            exit;
        } elseif ($this->organization_id && !$this->user->isAdminFor($this->organization_id) && !$this->user->isSubjectSpecialistFor($this->organization_id)) {
            header("Location: /admin");
            exit;
        }

        if ($request->getMethod() == "GET") {
            return $this->handleGetRequest($request, $response);
        } elseif ($request->getMethod() == "PUT") {
            return $this->handleUpdateRequest($request, $response);
        } elseif ($request->getMethod() == "POST") {
            return $this->handleCreateRequest($request, $response);
        } elseif ($request->getMethod() == "DELETE") {
            return $this->handleDeleteRequest($request, $response);
        } else {
            return $this->handleGetRequest($request, $response);
        }
    }

    private function handleGetRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        $resourceId = (int) $request->getAttribute('resourceId');
        $licenseId = $request->getAttribute('licenseId') ? (int) $request->getAttribute('licenseId') : null;

        $resource = $this->resourceService->getResourceById_NEW($resourceId, $this->organization_id);
        $license = $licenseId ? $resource->getLicenseById($licenseId) : null;

        return $this->renderPage($request, $response, $resource, $license);
    }

    /**
     * @throws LicenseAlreadyExistingException
     */
    private function handleCreateRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        if ($this->isAdmin || $this->isSuperAdmin) {
            $params = $request->getQueryParams();

            $resourceId = (int) $request->getAttribute('resourceId');
            $resource = $this->resourceService->getResourceById_NEW($resourceId, $this->organization_id);

            $license = $this->parseLicenseFromRequest($request);

            $errors = $license->validate();

            if (count($errors) === 0) {
                $organizations = $this->getOrganizations(["autoaddflag" => true]);

                $newLicense = $this->resourceService->addLicenseToResource($resource, $license, $this->organization_id, $organizations);

                $newLicenseId = $newLicense->getId();

                $url = str_replace("/new/", "/{$newLicenseId}/", $_SERVER['REQUEST_URI']);
                $url = explode('?', $url)[0];
                return $response->withHeader('Location', $url . '?created_successfully=1')->withStatus(303);
                exit();
            } else {
                unset($params['updated_successfully']);
                unset($params['created_successfully']);
                $request = $request->withQueryParams($params);
                return $this->renderPage($request, $response, $resource, $license, $errors);
            }
        }
    }

    /**
     * @throws LicenseNotFoundException
     */
    private function handleUpdateRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        if ($this->isAdmin || $this->isSuperAdmin) {
            $params = $request->getQueryParams();

            $resourceId = (int) $request->getAttribute('resourceId');
            $oldLicenseId = $request->getAttribute('licenseId') ? (int) $request->getAttribute('licenseId') : null;

            $resource = $this->resourceService->getResourceById_NEW($resourceId, $this->organization_id);
            $oldLicense = $oldLicenseId ? $resource->getLicenseById($oldLicenseId) : null;

            $license = $this->parseLicenseFromRequest($request);
            $license->setResourceId($resourceId);

            $errors = $license->validate();

            if (count($errors) === 0) {
                if (array_key_exists('only-access', $request->getParsedBody())) {
                    $this->resourceService->updateAccesses($license, $this->organization_id);
                } else {
                    $organizations = $this->getOrganizations(["autoaddflag" => true]);
                    $this->resourceService->updateLicense($license, $oldLicense, $this->organization_id, $organizations);
                }
    
                $url = explode('?', $_SERVER['REQUEST_URI'])[0];
                return $response->withHeader('Location', $url . '?updated_successfully=1')->withStatus(303);
                exit();
            } else {
                unset($params['updated_successfully']);
                unset($params['created_successfully']);
                $request = $request->withQueryParams($params);
                return $this->renderPage($request, $response, $resource, $license, $errors);
            }
        }
    }

    private function handleDeleteRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        if ($this->isAdmin || $this->isSuperAdmin) {
            $resourceId = (int) $request->getAttribute('resourceId');
            $licenseId = (int) $request->getAttribute('licenseId');

            $resource = $this->resourceService->getResourceById_NEW($resourceId, $this->organization_id);
            $license = $resource->getLicenseById($licenseId);

            $deleteWhat = $request->getParsedBody()['deleteWhat'];

            $this->resourceService->removeLicenseFromResource($license, $this->organization_id, $deleteWhat);

            // header("Location: ../?deleted_successfully=1", true, 303);

            return $response->withHeader('Location', '../?deleted_successfully=1')->withStatus(303);
        }
    }

    private function parseLicenseFromRequest(ServerRequestInterface $request): ?License
    {
        $id = (int) $request->getAttribute('licenseId') ? (int) $request->getAttribute('licenseId') : null;

        $body = $request->getParsedBody();

        // leave title and description empty, since they are not necessary here
        $type = new LicenseType((int) $body['licenseType'], [], []);

        $form = null;
        if (isset($body['licenseForm'])) {
            $form = new LicenseForm((int) $body['licenseForm'], [], []);
        }

        $publicationForm = null;
        if (isset($body['publication_form'])) {
            $publicationForm = new PublicationForm((int) $body['publication_form']);
        }

        $numActiveUsers = isset($body['parallel_users']) ?
                (int)$body['parallel_users'] : null;

        $isActive = isset($body['isActive']) && $body['isActive'] == "on";

        $textMiningAllowed = isset($body['is_allowing_data_mining']) && $body['is_allowing_data_mining'] == "on";

        $isOA = isset($body['is_oa']) && $body['is_oa'] == "on";
        $isAllowingWalking = isset($body['is_allowing_walking']) && $body['is_allowing_walking'] == "on";

        $lastCheck = $body['last_check'] ? date("Y-m-d", strtotime($body['last_check'])) : null;

        $cancelled = $body['cancelled'] ? date("Y-m-d", strtotime($body['cancelled'])) : null;

        $aquired = $body['aquired'] ? date("Y-m-d", strtotime($body['aquired'])) : null;

        /*
        TODO: Save
        */
        $vendorId = $body['vendor'] ?? null;
        $publisherId = $body['publisher'] ?? null;

        $license = new License($type, $id);
        $license->setActive($isActive);
        $license->setTextMiningAllowed($textMiningAllowed);
        $license->setOA($isOA);
        $license->setAllowingWalking($isAllowingWalking);
        $license->setInternalNotes([
            'de' => $this->purifier->purify($body['internal_notes_de']),
            'en' => $this->purifier->purify($body['internal_notes_en'])
        ]);
        $license->setExternalNotes([
            'de' => $this->purifier->purify($body['external_notes_de']),
            'en' => $this->purifier->purify($body['external_notes_en'])
        ]);
        $numActiveUsers ? $license->setNumberOfConcurrentUsers($numActiveUsers) : null;
        $form ? $license->setForm($form) : null;
        $accesses = $this->parseAccessesFromRequest($request);
        $license->setAccesses($accesses);

        $license->setVendor($this->parseVendorFromRequest($request));
        $license->setPublisher($this->parsePublisherFromRequest($request));

        $license->setPublicationForm($publicationForm);

        /*
        * Only at the local notes when an global license type was selected.
        */
        if ($type->getId() !== 2 && (array_key_exists('external_notes_for_org_de', $body) || array_key_exists('external_notes_for_org_en', $body) || array_key_exists('internal_notes_for_org_de', $body) || array_key_exists('internal_notes_for_org_en', $body) || array_key_exists('cancelled_by_organisation', $body) || array_key_exists('aquired_by_organisation', $body) || array_key_exists('last_check_by_organisation', $body))) {
            $license_localisation = new LicenseLocalisation($this->organization_id, $id);

            $license_localisation->setExternalNotes([
                'de' => $body['external_notes_for_org_de'],
                'en' => $body['external_notes_for_org_en']
            ]);

            $license_localisation->setInternalNotes([
                'de' => $body['internal_notes_for_org_de'],
                'en' => $body['internal_notes_for_org_en']
            ]);

            $license_localisation->setCancelled($body['cancelled_by_organisation']);
            $license_localisation->setAquired($body['aquired_by_organisation']);
            $license_localisation->setLastCheck($body['last_check_by_organisation']);
            $license->setLicenseLocalisation($license_localisation);
        }

        /*
        Check if fid license
        */
        if ($type->getId() == 4) {
            $fidId = $body['fid-institution'] ?? null;
            if ($fidId) {
                $license->setFID($fidId);

                $fidHostingPrivilege = isset($body['fid-hosting-privilege']) && $body['fid-hosting-privilege'] == "on";
                $license->setHostingPrivilege($fidHostingPrivilege);
            }
        }

        if (!is_null($lastCheck)) {
            $license->setLastCheck($lastCheck);
        }
        if (!is_null($cancelled)) {
            $license->setCancelled($cancelled);
        }
        if (!is_null($aquired)) {
            $license->setAquired($aquired);
        }

        $external_ids = array();
        if (array_key_exists('external_license_id', $_POST)) {
            foreach ($_POST['external_license_id'] as $index => $external_id_post) {
                $external_id = $_POST['external_license_id'][$index];
                if (strlen($external_id) > 0) {
                    $namespace = $_POST['external_id_namespace'][$index];
                    $external_id_name = $_POST['external_id_name'][$index];
                    $external_id_obj = new ExternalID($namespace, $external_id, $external_id_name);
                    $external_ids[] = $external_id_obj;
                }
            }
        }

        $license->setExternalIDs($external_ids);

        return $license;
    }

    /**
     *
     * @param ServerRequestInterface $request
     * @return Access[]
     */
    private function parseAccessesFromRequest(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();
        $resultArray = [];
        // Note: the first entry is always the template entry!
        for ($i = 1; $i < count($body['access_id']); $i++) {
            $accessType = null;
            if ($body["accesstype"][$i] && !empty($body["accesstype"][$i])) {
                $accessType = new AccessType((int) $body["accesstype"][$i], []);
            }
            $access = new Access(
                $accessType,
                isset($body["access_id"][$i]) ? (int)$body["access_id"][$i] : null
            );
            $access->setAccessUrl($body['accesslink'][$i]);
            $access->setManualUrl($body['manuallink'][$i]);
            // $access->set404Url($body['404link'][$i]);
            $access->setDescription([
            "de" => $body['description_de'][$i],
            "en" => $body['description_en'][$i]
            ]);
            $access->setRequirements([
            "de" => $body['requirements_de'][$i],
            "en" => $body['requirements_en'][$i]
            ]);

            if ($body['label_id'][$i] && strlen($body['label_id'][$i]) > 0) {
                $labelId = (int) $body['label_id'][$i];
                $access->setLabelId($labelId);
            } else {
                $access->setLabelId(null);
            }  
            
            $access->setLabel([
                "de" => $body['label_de'][$i],
                "en" => $body['label_en'][$i]
            ]);

            if ($body['label_long_de'][$i] && strlen($body['label_long_de'][$i]) > 0 && $body['label_long_en'][$i] && strlen($body['label_long_en'][$i]) > 0) {
                $access->setLongLabel([
                    "de" => $body['label_long_de'][$i],
                    "en" => $body['label_long_en'][$i]
                ]);
            }
            
            if ($body['label_longest_de'][$i] && strlen($body['label_longest_de'][$i]) > 0 && $body['label_longest_en'][$i] && strlen($body['label_longest_en'][$i]) > 0) {
                $access->setLongestLabel([
                    "de" => $body['label_longest_de'][$i],
                    "en" => $body['label_longest_en'][$i]
                ]);
            }

            if ($body["accessform"][$i] && !empty($body["accessform"][$i])) {
                $accessFormId = (int) $body["accessform"][$i];
                $accessForm = new AccessForm($accessFormId);
                $access->setForm($accessForm);
            }

            if ($body["shelfmark"][$i] && strlen($body["shelfmark"][$i]) > 0) {
                $access->setShelfmark($body["shelfmark"][$i]);
            }

            $host = $body["host"][$i];
            $newHost = $body["host-new"][$i];
            if ($newHost) {
                $host = new Host();
                $host->setTitle($newHost);
                $access->setHost($host);
            } elseif ($host && $host != "") {
                $host = $this->resourceService->getHostById((int)$host);
                $host ? $access->setHost($host) : null;
            }

            $access_is_global = isset($body['is_global'][$i]) && $body['is_global'][$i] == "1" ? true : false;

            $access_is_visible = isset($body['is_hidden'][$i]) && $body['is_hidden'][$i] == "1" ? false : true;

            if (!$access_is_global && $this->organization_id) {
                $access->setOrganizationId($this->organization_id);
            }

            if (!$access_is_visible) {
                $access->setVisibility(false);
            }

            $is_main_access = isset($body['is_main'][$i]) && $body['is_main'][$i] == "1" ? true : false;
            if ($is_main_access) {
                $access->setMainAccess(true);
            }

            $resultArray[] = $access;
        }
        return $resultArray;
    }

    private function parseVendorFromRequest(ServerRequestInterface $request): ?Enterprise
    {
        $body = $request->getParsedBody();
        $vendor = $body["vendor"] ?? null;
        $newVendor = $body["vendor-new"] ?? null;
        if ($newVendor) {
            $vendor = new Enterprise();
            $vendor->setTitle($this->purifier->purify($newVendor));
        } elseif ($vendor && $vendor != "") {
            $vendor = $this->resourceService->getEnterpriseById((int) $vendor);
        } else {
            $vendor = null;
        }
        return $vendor;
    }

    private function parsePublisherFromRequest(ServerRequestInterface $request): ?Enterprise
    {
        $body = $request->getParsedBody();
        $publisher = $body["publisher"];
        $newPublisher = $body["publisher-new"];
        if ($newPublisher) {
            $publisher = new Enterprise();
            $publisher->setTitle($this->purifier->purify($newPublisher));
        } elseif ($publisher && $publisher != "") {
            $publisher = $this->resourceService->getEnterpriseById((int) $publisher);
        } else {
            $publisher = null;
        }
        return $publisher;
    }

    private function renderPage(
        ServerRequestInterface $request,
        ResponseInterface $response,
        Resource $resource,
        License $license = null,
        $errors = []
    ) {
        $this->resourceService->getHosts();
        $language = $this->authService->getAuthenticatedUser()->getLanguage();
        $organization =
            $this->organization_id ? $this->organizationService->getOrganizationByUbrId($this->organization_id) : null;

        $organization_id = $this->organization_id;
        $privileges = array_filter($this->user->getPrivileges(), function ($privilege) use ($organization_id) {
            return $privilege->getOrganizationId() == $organization_id;
        });

        $fids = $this->organizationService->getFIDs();
        $fids = array_map(function ($item) use ($language) {
            return $item->toI18nAssocArray($language);
        }, $fids);

        $has_consortial_rights = $this->user->hasPrivilegesToCreateConsortialLicenses($this->organization_id) || $this->isSuperAdmin;
        $has_national_rights = $this->user->hasPrivilegesToCreateNationalLicenses($this->organization_id) || $this->isSuperAdmin;
        $is_fid = $organization->getIsFID() || $this->isSuperAdmin;

        $isZbmed = $organization_id === "ZBMED" ? true: false;

        $options = array('isFID' => $is_fid || $this->isSuperAdmin, 'isConsortium' => $organization->getIsConsortium() || $has_consortial_rights || $this->isSuperAdmin, 'allowedForNL' => $has_national_rights || $this->isSuperAdmin);

        $licenseTypes = $this->organization_id ?
                $this->resourceService->getLicenseTypes() :
                $this->resourceService->getGlobalLicenseTypes();
        $licenseTypesAssoc = array_map(function ($item) use ($language) {
            return $item->toI18nAssocArray($language);
        }, $licenseTypes);

        $licenseForms = $this->resourceService->getLicenseForms();
        $licenseFormsAssoc = array_map(function ($item) use ($language) {
            return $item->toI18nAssocArray($language);
        }, $licenseForms);

        if ($resource->isFree()) {
            $removeLicenseTypeIds = [2, 3, 4, 5, 6];
            $licenseTypesAssoc = array_filter($licenseTypesAssoc, function ($item) use ($removeLicenseTypeIds) {
                return !in_array($item['id'], $removeLicenseTypeIds);
            });

            $removeLicenseFormIds = [1, 22, 24, 31, 41, 42, 43, 61];
            $licenseFormsAssoc = array_filter($licenseFormsAssoc, function ($item) use ($removeLicenseFormIds) {
                return !in_array($item['id'], $removeLicenseFormIds);
            });
        } else {
            $removeLicenseTypeIds = [1];
            $licenseTypesAssoc = array_filter($licenseTypesAssoc, function ($item) use ($removeLicenseTypeIds) {
                return !in_array($item['id'], $removeLicenseTypeIds);
            });

            /*
            $removeLicenseFormIds = [];
            $licenseFormsAssoc = array_filter($licenseFormsAssoc, function ($item) use ($removeLicenseFormIds) {
                return !in_array($item['id'], $removeLicenseFormIds);
            });
            */
        }

        $accessTypes = $this->resourceService->getAccessTypes();
        $accessTypesAssoc = array_map(function ($item) use ($language) {
            return $item->toI18nAssocArray($language);
        }, $accessTypes);

        $accessForms = $this->resourceService->getAccessForms();
        $accessFormsAssoc = array_map(function ($item) use ($language) {
            return $item->toI18nAssocArray($language);
        }, $accessForms);

        $hosts = $this->resourceService->getHosts();
        $hostsAssoc = array_map(function ($item) use ($language) {
            return $item->toAssocArray();
        }, $hosts);

        $enterprises = $this->resourceService->getEnterprises();
        $enterprisesAssoc = array_map(function ($item) use ($language) {
            return $item->toAssocArray();
        }, $enterprises);

        $publication_forms = array_map(function ($publication_form) use ($language) {
            return $publication_form->toI18nAssocArray($language);
        }, $this->resourceService->getPublicationForms());

        $selectedExternalIds = $license ? array_map(function ($externalId) {
            return $externalId->toAssocArray();
        }, $license->getExternalIDs()) : null;

        $labels = $this->resourceService->getLabels($organization_id);

        $labels_global = $this->resourceService->getLabels(null);
        $filtered_labels = array_filter($labels_global, function ($label) use ($has_national_rights, $has_consortial_rights, $is_fid) {
            $licenseType = $label['is_for_license_type'] ? (int)$label['is_for_license_type'] : null;
            if (is_null($licenseType)) {
                return false;
            }
            if ($licenseType == 3) {
                if ($has_national_rights || $this->isSuperAdmin) {
                    return true;
                }
            } elseif ($licenseType == 5) {
                if ($has_consortial_rights || $this->isSuperAdmin) {
                    return true;
                }
            } elseif ($licenseType == 4) {
                if ($is_fid || $this->isSuperAdmin) {
                    return true;
                }
            } 
            
            return false;
        });

        $view = Twig::fromRequest($request);

        $this->params['pageTitle'] = $this->resourceProvider->getText("page_title_edit_license", $language);
        $this->params['queryParams'] = $request->getQueryParams();
        $this->params['resource'] = $resource->toI18nAssocArray($language);
        $this->params['organization'] = $organization ? $organization->toI18nAssocArray($language) : null;
        $this->params['license'] = $license ? $license->toAssocArray() : null;
        $this->params['country_code'] = $organization->getCountryCode();
        $this->params['licenseTypes'] = $licenseTypesAssoc;
        $this->params['licenseForms'] = $licenseFormsAssoc;
        $this->params['accessTypes'] = $accessTypesAssoc;
        $this->params['accessForms'] = $accessFormsAssoc;
        $this->params['hosts'] = $hostsAssoc;
        $this->params['enterprises'] = $enterprisesAssoc;
        $this->params['publication_forms'] = $publication_forms;
        $this->params['privilge_addons'] = $options;

        $this->params['selectedExternalIds'] = $selectedExternalIds;

        $this->params['labels'] = $labels;
        // $this->params['labels_global'] = $filtered_labels;
        $this->params['labels_global'] = [];

        $this->params['has_consortial_privileges'] = $has_consortial_rights;
        $this->params['has_national_privileges'] = $has_national_rights;
        $this->params['is_fid'] = $is_fid;
        $this->params['fids'] = $fids;
        $this->params['isZbmed'] = $isZbmed;

        $this->params['errors'] = $errors;

        $this->params['is_updated_successfully'] = array_key_exists(
            "updated_successfully",
            $request->getQueryParams()
        );
        $this->params['is_created_successfully'] = array_key_exists(
            "created_successfully",
            $request->getQueryParams()
        );
        return $view->render(
            $response,
            'admin/licenses/edit_license_page.twig',
            $this->params
        );
    }
}
