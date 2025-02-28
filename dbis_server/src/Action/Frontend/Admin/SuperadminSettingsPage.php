<?php

namespace App\Action\Frontend\Admin;

use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;
use App\Domain\Organizations\Entities\Organization;
use App\Domain\Organizations\OrganizationService;
use App\Domain\Shared\AuthService;
use App\Infrastructure\Shared\CountryProvider;
use App\Infrastructure\Shared\Exceptions\LanguageNotFoundException;
use App\Infrastructure\Shared\Exceptions\ResourceNotFoundException;
use App\Infrastructure\Shared\ResourceProvider;
use App\Domain\Resources\ResourceService;

class SuperadminSettingsPage extends AdminBasePage
{
    private OrganizationService $organizationProvider;

    public function __construct(
        ResourceProvider $rp,
        AuthService $auth,
        OrganizationService $organizationService,
        CountryProvider $countryProvider,
        ResourceService $resourceService
    ) {
        parent::__construct($rp, $auth, $organizationService, $countryProvider, $resourceService);
        $this->organizationProvider = $organizationService;
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // simple auth guard
        if (!$this->authService->isSessionAuthenticated()) {
            return $response->withHeader('Location', '/admin/login')->withStatus(302);
        } elseif (!$this->authService->getAuthenticatedUser()->isSuperadmin()) {
            // IMPORTANT! Only authenticated super admins are allowed to see this page!
            return $response->withHeader('Location', '/admin')->withStatus(302);
        }

        if ($request->getMethod() == "GET") {
            return $this->handleGetRequest($request, $response);
        } elseif ($request->getMethod() == "POST" || $request->getMethod() == "PUT") {
            return $this->handlePostRequest($request, $response);
        } else {
            return $this->handleGetRequest($request, $response);
        }
    }

    private function handlePostRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        $body = $request->getParsedBody();

        $id = $body['id'];
        $translate_url = $body['translate_url'];

        $settings = array("id" => $id, "translate_url" => $translate_url);

        $this->organizationProvider->updateSettings($settings);

        $url = explode('?', $_SERVER['REQUEST_URI'])[0];
        header("Location: {$url}?updated_successfully=1", true, 303);
        exit();

        // return $response->withHeader('Location', "/superadmin/settings/", true, 200);
    }

    /**
     * @throws ResourceNotFoundException
     * @throws LanguageNotFoundException
     */
    private function handleGetRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {

        $language = $this->authService->getAuthenticatedUser()->getLanguage();

        $organizations = array_map(function (Organization $org) use ($language) {
            return $org->toI18nAssocArray($language);
        }, $this->organizationProvider->getOrganizations());
        $this->params['organizations'] = $organizations;

        $this->params['is_updated_successfully'] = array_key_exists(
            "updated_successfully",
            $request->getQueryParams()
        );

        $this->params['pageTitle'] = "DBIS - " . $this->resourceProvider->getText(
            "h_super_settings",
            $language
        );
        $view = Twig::fromRequest($request);
        return $view->render(
            $response,
            'admin/settings.twig',
            $this->params
        );
    }
}
