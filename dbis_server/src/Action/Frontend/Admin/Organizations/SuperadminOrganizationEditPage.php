<?php

declare(strict_types=1);

namespace App\Action\Frontend\Admin\Organizations;

use App\Domain\Organizations\Entities\DbisSettings;
use App\Domain\Organizations\Entities\Link;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;
use App\Domain\Organizations\Entities\Organization;
use App\Domain\Organizations\Entities\ExternalOrganizationIdentifier;
use App\Domain\Organizations\Entities\ExternalOrganizationIdentifierNamespace;
use App\Domain\Organizations\Entities\DbisView;

/**
 * AdminCreateOrganisationPage
 *
 * Form for creating a new organization
 */
class SuperadminOrganizationEditPage extends AdminOrganizationBasePage
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // simple auth guard
        if (!$this->authService->isSessionAuthenticated()) {
            $this->redirectToLogin($response);
        } elseif (!($this->user->isSuperadmin() || $this->user->isAdmin())) {
            $this->redirectToAdminStart($response);
        }

        // Set organisation according to route parameter
        if ($request->getAttribute('ubrId')) {
            parent::setAdministratedOrganization($request->getAttribute('ubrId'));
        }

        if ($request->getMethod() == "GET") {
            return $this->handleGetRequest($request, $response);
        } elseif ($request->getMethod() == "PUT") {
            return $this->handleUpdateRequest($request, $response);
        } elseif ($request->getMethod() == "POST") {
            return $this->handleCreateRequest($request, $response);
        } elseif ($request->getMethod() == "DELETE") {
            return $this->handleDeleteRequest($request, $response);
        }
    }

    private function redirectToLogin(ResponseInterface $response)
    {
        return $response->withHeader('Location', '/admin/login');
    }

    private function redirectToAdminStart(ResponseInterface $response)
    {
        return $response->withHeader('Location', '/admin');
    }


    private function handleGetRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        $ubrId = $request->getAttribute('ubrId');
        if ($ubrId) {
            $organization = $this->orgService->getOrganizationByUbrId($ubrId);
            $this->params['organization'] = $organization->toAssocArray();
            $this->params['organizationI18N'] = $organization->
                    toI18nAssocArray($this->language);
        } else {
            // Redirect to startpage, if admin tries to create a organization
            if (!$this->user->isSuperadmin()) {
                return $this->redirectToAdminStart($response);
            }
        }

        // bind special params to template ("params" is initialized in AdminBasePage)
        $this->params['pageTitle'] = $this->resourceProvider->
                getText("page_title_create_organization", $this->language);
        $this->params['is_superadmin'] = $this->user ? $this->user->isSuperadmin(): false;
        $this->params['is_created_successfully'] = array_key_exists(
            "created_successfully",
            $request->getQueryParams()
        );
        $this->params['is_updated_successfully'] = array_key_exists(
            "updated_successfully",
            $request->getQueryParams()
        );
        $this->params['externalIdentifierNamespaces'] = array_map(function ($a) {
                    return $a->toI18nAssocArray($this->language);
        }, $this->externalIdentifierNamespaces);

        $view = Twig::fromRequest($request);
        return $view->render(
            $response,
            'admin/edit_organization_superadmin.twig',
            $this->params
        );
    }

    private function handleUpdateRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        $body = $request->getParsedBody();
        $organization = $this->mapRequestBodyToOrganization($body);
        // the form does not contain an input for the view
        // the updated model thus should contain the view of the organization
        $oldOrganization = $this->orgService->getOrganizationByUbrId($organization->getUbrId());
        $view = $oldOrganization->getDbisView();

        if ($view != null) {
            $organization->setDbisView($view);
        }

        $this->orgService->updateOrganization(
            $organization,
            $_FILES['org-icon']
        );
        $this->redirectToPageWithFlag("updated_successfully");
    }

    private function redirectToPageWithFlag(string $queryParam)
    {
        $url = explode('?', $_SERVER['REQUEST_URI'])[0];
        header("Location: {$url}?{$queryParam}=1", true, 303);
        exit();
    }

    private function handleCreateRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        if (!$this->user->isSuperadmin()) {
            return $this->redirectToAdminStart($response);
        }
        $body = $request->getParsedBody();
        $organization = $this->mapRequestBodyToOrganization($body);
        // if checkbox is set, append an empty DBIS view
        if ($body['is-creating-dbis-view'] == "on") {
            $organization->setDbisView(
                new DbisView([])
            );
        }
        $this->orgService->createOrganization(
            $organization,
            $_FILES['org-icon']
        );

        $this->redirectToNewOrganization("created_successfully", $organization->getUbrId());
    }

    private function redirectToNewOrganization(string $queryParam, string $ubrId)
    {
        $url = explode('?', $_SERVER['REQUEST_URI'])[0];
        $url = str_replace("new", $ubrId, $url);
        header("Location: {$url}?{$queryParam}=1", true, 303);
        exit();
    }

    private function handleDeleteRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        if (!$this->user->isSuperadmin()) {
            return $this->redirectToAdminStart($response);
        }
        $ubrId = $request->getAttribute('ubrId');
        $body = $request->getParsedBody();
        $this->orgService->deleteOrganizationByUbrId($ubrId);

        return $this->redirectToManageOrganizationsWithFlag("deleted_successfully");
    }

    private function redirectToManageOrganizationsWithFlag(string $queryParam)
    {
        $url = explode('?', $_SERVER['REQUEST_URI'])[0];
        header("Location: ../?{$queryParam}=1", true, 303);
        exit();
    }

    //
    //
    // Parsing functions

    protected function mapRequestBodyToOrganization(array $body): Organization
    {
        $errs = [];

        $org = new Organization(
            $this->getValueSafely($body, "ubr_id"),
            [
            "de" => $this->getValueSafely($body, "name_de"),
            "en" => $this->getValueSafely($body, "name_en")
                ],
            $this->getValueSafely($body, "country")
        );
        $org->setCity([
            "de" => $this->getValueSafely($body, "city_de"),
            "en" => $this->getValueSafely($body, "city_en")
        ]);
        $org->setRegion([
            "de" => $this->getValueSafely($body, "region_de"),
            "en" => $this->getValueSafely($body, "region_en")
        ]);
        $org->setAdress([
            "de" => $this->getValueSafely($body, "adress_de"),
            "en" => $this->getValueSafely($body, "adress_en")
        ]);
        $org->setHomepage([
            "de" => $this->getValueSafely($body, "homepage_de"),
            "en" => $this->getValueSafely($body, "homepage_en")
        ]);
        $org->setZipcode($this->getValueSafely($body, "zipcode"));
        $org->setContact($this->getValueSafely($body, "contact_mail"));
        $org->setDbisId($this->getValueSafely($body, "dbis_id"));

        $org->setColor($this->getValueSafely($body, "color"));

        $org->setIconPath($this->getValueSafely($body, "organization-icon-filepath"));

        $org->setExternalIds($this->parseOrganizationIds($body));

        $org->setLinks($this->parseLinks($body));

        $is_fid = isset($body['is_fid']);
        $org->setIsFID($is_fid);

        $is_consortium = isset($body['is_consortium']);
        $org->setIsConsortium($is_consortium);

        $is_kfl = isset($body['is_kfl']);
        $org->setIsKfL($is_kfl);

        $dbisSettings = new DbisSettings();
        $autoaddflag = isset($body['autoaddflag']) ? $body['autoaddflag'] : null;
        if ($autoaddflag && $autoaddflag == "1") {
            $autoaddflag = true;
        } else {
            $autoaddflag = false;
        }
        $dbisSettings->setAutoAddFlag($autoaddflag);
        $org->setDbisSettings($dbisSettings);

        return $org;
    }

    private function getValueSafely(array $assocArray, string $key): ?string
    {
        return $this->purifier->purify($assocArray[$key]) ?? null;
    }

    private function parseOrganizationIds(array $body): array
    {
        $results = [];

        if (array_key_exists('external_id_ns', $body) && $body['external_id_ns']) {
            // drop first entry, since it currently is the template input
            for ($i = 1; $i < count($body['external_id_ns']); $i++) {
                $namespace = $body['external_id_ns'][$i];
                $identifierId = $body['external_id_key'][$i];
                $ns = new ExternalOrganizationIdentifierNamespace($namespace);
                $id = new ExternalOrganizationIdentifier($identifierId, $ns);
                array_push($results, $id);
            }
        }

        return $results;
    }

    private function parseLinks(array $body): array
    {
        $results = [];

        $count = count($body['url_de']) >= count($body['url_en']) ? count($body['url_de']): count($body['url_en']);

        // drop first entry, since it currently is the template input
        for ($i = 0; $i < $count; $i++) {

            $url_de = $body['url_de'][$i];
            $url_en = $body['url_en'][$i];

   
                $text_de = $body['text_de'][$i];
                $text_en = $body['text_en'][$i];

                $link = new Link(
                    [
                        "de" => $url_de,
                        "en" => $url_en,
                    ],
                    [
                        "de" => $text_de,
                        "en" => $text_en
                    ]
                );
                $results[] = $link;
            
        }

        return $results;
    }
}
