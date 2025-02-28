<?php

namespace App\Action\Frontend\Admin;

use App\Domain\Organizations\OrganizationService;
use App\Domain\Resources\Entities\Access;
use App\Domain\Resources\ResourceService;
use App\Domain\Shared\AuthService;
use App\Infrastructure\Shared\CountryProvider;
use App\Infrastructure\Shared\ResourceProvider;
use App\Domain\Resources\Entities\InvalidUrls;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;

class AdminManageLabelsPage extends AdminBasePage
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

        $body = $request->getParsedBody();

        // simple auth guard
        if (!$this->authService->isSessionAuthenticated()) {
            return $response->withHeader('Location', '/admin/login');
        } elseif (!$this->isSuperAdmin && !$this->isAdmin) {
            return $response->withHeader('Location', '/admin');
        }

        if ($request->getMethod() == "GET") {
            return $this->handleGetRequest($request, $response);
        } elseif ($request->getMethod() == "POST" || $request->getMethod() == "PUT") {
            if (isset($body['delete'])) {
                return $this->handleDeleteRequest($request, $response);
            } elseif (isset($body['merge'])) {
                return $this->handleMergeRequest($request, $response);
            } else {
                return $this->handleUpdateRequest($request, $response);
            }
        }

        // simple auth guard
        if (!$this->authService->isSessionAuthenticated()) {
            return $response->withHeader('Location', '/admin/login');
        } elseif (!$this->isSuperAdmin && !$this->isAdmin && !$this->isSubjectSpecialist) {
            return $response->withHeader('Location', '/admin');
        }        
    }

    private function handleMergeRequest(ServerRequestInterface $request, ResponseInterface $response) {
        $organization_id = $this->getSelectedOrganizationIdFromSession();

        $body = $request->getParsedBody();

        $label_id_of_label_to_merge_from = (int) $body['merge'];
   
        $label_id_of_label_to_merge_into = (int) $body['merge-to'][$label_id_of_label_to_merge_from]; 

        if ($label_id_of_label_to_merge_from !== $label_id_of_label_to_merge_into) {
            // echo("Merge $label_id_of_label_to_merge_from  into $label_id_of_label_to_merge_into");
            $this->service->mergeLabels($label_id_of_label_to_merge_from, $label_id_of_label_to_merge_into);
        } else {
            // echo("Do nothing ...");
        }

        $mergeSuccessfully = true;

        return $this->renderPage($request, $response, null, null, $mergeSuccessfully);
    }

    private function handleDeleteRequest(ServerRequestInterface $request, ResponseInterface $response) {
        $organization_id = $this->getSelectedOrganizationIdFromSession();

        $body = $request->getParsedBody();

        $label_id = (int) $body['delete'];

        $this->service->deleteLabel($label_id);

        $deletionSuccessfully = true;

        return $this->renderPage($request, $response, null, $deletionSuccessfully);
    }

    private function handleUpdateRequest(ServerRequestInterface $request, ResponseInterface $response)
    {
        $organization_id = $this->getSelectedOrganizationIdFromSession();

        $body = $request->getParsedBody();

        $labels = array();

        for ($i = 0; $i < count($body['label_de']); $i++) {
            $label_id = null;
            $label_id_html = $body['label_id'][$i];
            if (strlen($label_id_html) > 0) {
                $label_id = (int) $label_id_html;
            }

            $label = [
                "de" => $body['label_de'][$i],
                "en" => $body['label_en'][$i]
            ];
            
            $label_long = null;
            if (($body['label_long_de'][$i] && strlen($body["label_long_de"][$i]) > 0) || ($body['label_long_en'][$i] && strlen($body["label_long_en"][$i]) > 0)) {
                $label_long = [
                    "de" => $body['label_long_de'][$i],
                    "en" => $body['label_long_en'][$i]
                ];
            }

            $label_longest = null;
            if (($body['label_longest_de'][$i] && strlen($body["label_longest_de"][$i]) > 0) || ($body['label_longest_en'][$i] && strlen($body["label_longest_en"][$i]) > 0)) {
                $label_longest = [
                    "de" => $body['label_longest_de'][$i],
                    "en" => $body['label_longest_en'][$i]
                ];
            }

            $is_for_license_type = null;
            if ($body['is_for_license_type'][$i] && strlen($body["is_for_license_type"][$i]) > 0) {
                $is_for_license_type = (int) $body['is_for_license_type'][$i];
            }

            if (($label["de"] && strlen($label["de"]) > 0) || ($label["en"] && strlen($label["en"]) > 0)) {
                $labels[] = array('id' => $label_id, 'label' => $label, 'label_long' => $label_long, 'label_longest' => $label_longest, 'is_for_license_type' => $is_for_license_type);
            }
        }

        if (count($labels) > 0) {
            $this->service->saveLabels($labels, $organization_id);
        }
        
        $updateSuccessfully = true;

        return $this->renderPage($request, $response, $updateSuccessfully, null);
    }

    private function handleGetRequest(ServerRequestInterface $request, ResponseInterface $response)
    {
        return $this->renderPage($request, $response);
    }

    private function renderPage(
        ServerRequestInterface $request,
        ResponseInterface $response,
        $updateSuccessfully = null,
        $deletionSuccessfully = null,
        $mergeSuccessfully = null
    ) {
        $view = Twig::fromRequest($request);
        $organization_id = $this->getSelectedOrganizationIdFromSession();

        $this->params['pageTitle'] = $this->resourceProvider->getText(
            "h_labels",
            $this->authService->getAuthenticatedUser()->getLanguage()
        );

        $language = $this->language;

        if ($updateSuccessfully) {
            $this->params['updated_successfully'] = True;
        } else {
            $this->params['updated_successfully'] = False;
        }

        if ($deletionSuccessfully) {
            $this->params['deleted_successfully'] = True;
        } else {
            $this->params['deleted_successfully'] = False;
        }

        if ($mergeSuccessfully) {
            $this->params['merge_successfully'] = True;
        } else {
            $this->params['merge_successfully'] = False;
        }

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

        $accessTypes = $this->resourceService->getAccessTypes();
        $accessTypesAssoc = array_map(function ($item) use ($language) {
            return $item->toI18nAssocArray($language);
        }, $accessTypes);

        $accessForms = $this->resourceService->getAccessForms();
        $accessFormsAssoc = array_map(function ($item) use ($language) {
            return $item->toI18nAssocArray($language);
        }, $accessForms);

        $labels = $this->resourceService->getLabels($organization_id);
        
        $labels_global = $this->resourceService->getLabels(null);

        // $privileges = $this->resourceService->getPrivilegesOfOrganisation($organization_id);
        $organization =
            $this->organization_id ? $this->organizationService->getOrganizationByUbrId($this->organization_id) : null;
        $has_consortial_rights = $this->user->hasPrivilegesToCreateConsortialLicenses($this->organization_id) || $this->isSuperAdmin;
        $has_national_rights = $this->user->hasPrivilegesToCreateNationalLicenses($this->organization_id) || $this->isSuperAdmin;
        $is_fid = is_null($organization) ? false : $organization->getIsFID() || $this->isSuperAdmin;

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
        $this->params['organizationId'] = $organization_id;

        $this->params['licenseTypes'] = $licenseTypesAssoc;
        $this->params['licenseForms'] = $licenseFormsAssoc;
        $this->params['accessTypes'] = $accessTypesAssoc;
        $this->params['accessForms'] = $accessFormsAssoc;
        $this->params['labels'] = $labels;
        // $this->params['labels_global'] = $filtered_labels;
        $this->params['labels_global'] = [];
        $this->params['has_consortial_privileges'] = $has_consortial_rights;
        $this->params['has_national_privileges'] = $has_national_rights;
        $this->params['is_fid'] = $is_fid;
        // $this->params['privileges'] = $privileges;

        $view = Twig::fromRequest($request);

        return $view->render(
            $response,
            'admin/manage_labels.twig',
            $this->params
        );
    }
}
