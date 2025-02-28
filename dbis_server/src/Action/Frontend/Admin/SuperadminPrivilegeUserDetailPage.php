<?php

namespace App\Action\Frontend\Admin;

use App\Domain\Shared\Exceptions\AuthenticationFailedException;
use App\Infrastructure\Shared\Exceptions\LanguageNotFoundException;
use App\Infrastructure\Shared\Exceptions\ResourceNotFoundException;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;
use App\Infrastructure\Shared\ResourceProvider;
use App\Infrastructure\Shared\CountryProvider;
use App\Domain\Organizations\OrganizationService;
use App\Domain\Resources\ResourceService;
use App\Domain\Shared\AuthService;
use App\Action\Frontend\Admin\AdminBasePage;
use App\Domain\Shared\Entities\PrivilegeType;
use App\Domain\Shared\Entities\PrivilegeAddon;
use App\Domain\Shared\Entities\Privilege;
use App\Domain\Shared\Entities\User;
use App\Domain\Organizations\Entities\Organization;

/**
 * Description of AdminLoginPage
 *
 */
class SuperadminPrivilegeUserDetailPage extends AdminBasePage
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
            return $this->handlePostRequest($request, $response);
        } elseif ($request->getMethod() == "DELETE") {
            return $this->handleDeleteRequest($request, $response);
        } else {
            return $this->handleGetRequest($request, $response);
        }
    }

    private function handleDeleteRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        $userId = $request->getAttribute('userId');
        $privilegeId = $request->getAttribute('privilegeId');

        $user = $this->authService->getUserById($userId);
        $user->removePrivilegeById($privilegeId);
        $this->authService->persistUser($user);

        header("Location: /superadmin/privileges/users/$userId/");
        exit;
    }

    private function handlePostRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        $userId = $request->getAttribute('userId');
        $body = $request->getParsedBody();
        $privilege = $this->parsePrivilege($body);
        $user = $this->authService->getUserById($userId);
        $user->addPrivilege($privilege);
        $this->authService->persistUser($user);

        header("Location: /superadmin/privileges/users/$userId/");
        exit;
    }

    private function parsePrivilege(array $body): Privilege
    {
        $privilegeType = $this->authService->getPrivilegeTypeById((int)$body['privilege_type']);

        $privilegeAddons = [];
        if (array_key_exists('privilge_addons', $body)) {
            foreach ($body['privilge_addons'] as $key => $privilge_addon){ 
                $checked_addon = $this->authService->getPrivilegeAddonById((int)$privilge_addon);
                array_push($privilegeAddons, $checked_addon);
            }
        }
        
        $privilege = new Privilege("", $body['organization-id']);
        $privilege->setType($privilegeType);
        $privilege->setAddons($privilegeAddons);
        return $privilege;
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
        $userId = $request->getAttribute('userId');
        /** @var User $user */
        $user = $this->authService
                ->getUserById($userId)->toI18nAssocArray($language);
        $this->params['selectedUser'] = $user;
        $organizations = array_map(function (Organization $org) use ($language) {
            return $org->toI18nAssocArray($language);
        }, $this->organizationProvider->getOrganizations());
        $this->params['organizations'] = $organizations;
        $this->params['privilegeTypes'] = array_map(function (PrivilegeType $cPrivilegeType) use ($language) {
            return $cPrivilegeType->toI18nAssocArray($language);
        }, $this->authService->getPrivilegeTypes());
        // sort privilegetypes, so that "Admin" is on top
        usort($this->params['privilegeTypes'], function ($a, $b) {
            if ($b['id'] == 1) {
                return 1;
            } else {
                return 0;
            }
        });

        $this->params['privilegeAddons'] = array_map(function (PrivilegeAddon $cPrivilegeAddon) use ($language) {
            return $cPrivilegeAddon->toI18nAssocArray($language);
        }, $this->authService->getPrivilegeAddons());

        // add organizations to privileges
        $this->params['privileges'] = array_map(function ($privilege) use ($organizations) {
            $organizationIdx = array_search(
                $privilege['organizationId'],
                array_column($organizations, "ubrId")
            );
            $organization = $organizations[$organizationIdx];
            $privilege['organization'] = $organization;
            return $privilege;
        }, $user['privileges']);


        $users = $this->authService->getUsers();
        $this->params['users'] = array_map(function ($i) {
            return $i->toAssocArray();
        }, $users);

        $this->params['addPrivilege'] = $request->getQueryParams()['add-privilege'] ?? false;

        $this->params['pageTitle'] = "DBIS - " . $this->resourceProvider->getText(
            "h_privileges_select_users",
            $language
        );
        $view = Twig::fromRequest($request);
        return $view->render(
            $response,
            'admin/privileges/privileges_user_detail.twig',
            $this->params
        );
    }
}
