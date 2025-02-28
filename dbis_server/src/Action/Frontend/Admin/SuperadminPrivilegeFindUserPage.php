<?php

namespace App\Action\Frontend\Admin;

use App\Domain\Shared\Exceptions\AuthenticationFailedException;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;
use App\Infrastructure\Shared\ResourceProvider;
use App\Domain\Shared\AuthService;
use App\Action\Frontend\Admin\AdminBasePage;
use App\Domain\Shared\Entities\User;
use App\Domain\Organizations\Entities\Organization;
use App\Infrastructure\Shared\CountryProvider;
use App\Domain\Resources\ResourceService;
use App\Domain\Organizations\OrganizationService;

/**
 * Description of AdminLoginPage
 *
 */
class SuperadminPrivilegeFindUserPage extends AdminBasePage
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
        } elseif ($request->getMethod() == "POST") {
            return $this->handleLoginRequest($request, $response);
        }
    }

    private function handleGetRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        $language = $this->language;
        $users = $this->authService->getUsers();
        $organizations = $this->organizationProvider->getOrganizations();
        $dictOrganizations = array_reduce($organizations, function ($carry, Organization $item) use ($language) {
            $carry[$item->getUbrId()] = $item->toI18nAssocArray($language);
            return $carry;
        }, []);

        $this->params['users'] = array_map(function (User $user) use ($dictOrganizations) {
            $assocUser = $user->toAssocArray();
            $assocUser['privileges'] = array_map(function (array $dictPrivilege) use ($dictOrganizations) {
                if ($dictPrivilege['organizationId'] && array_key_exists($dictPrivilege['organizationId'], $dictOrganizations)) {
                    $dictPrivilege['organization'] = $dictOrganizations[$dictPrivilege['organizationId']];
                } else {
                    return null;
                }
                return $dictPrivilege;
            }, $assocUser['privileges']);

            // Remove null values, as users associated with deleted organisations have null privileges
            $assocUser['privileges'] = array_filter($assocUser['privileges'], function ($value) {
                return $value !== null;
            });

            return $assocUser;
        }, $users);

        $this->params['pageTitle'] = "DBIS - " . $this->resourceProvider->getText(
            "h_privileges_select_users",
            $this->authService->getAuthenticatedUser()->getLanguage()
        );
        $view = Twig::fromRequest($request);
        return $view->render(
            $response,
            'admin/privileges/privileges_list_users.twig',
            $this->params
        );
    }
}
