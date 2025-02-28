<?php

declare(strict_types=1);

namespace App\Action\Frontend\Admin;

require_once __DIR__ . '/../../../Vendor/htmlpurifier/library/HTMLPurifier.auto.php';

use App\Domain\Organizations\Exceptions\OrganizationWithUbrIdNotExistingException;
use App\Infrastructure\Shared\ResourceProvider;
use App\Infrastructure\Shared\CountryProvider;
use App\Domain\Shared\AuthService;
use App\Domain\Resources\ResourceService;
use App\Domain\Shared\Entities\User;
use App\Domain\Organizations\OrganizationService;
use App\Domain\Organizations\Entities\Organization;
use App\Domain\Shared\Entities\Privilege;

/**
 * AdminBasePage
 *
 * Abstract class for all admin pages. Contains common init routines.
 */
abstract class AdminBasePage extends \App\Action\Frontend\BasePage
{
    protected \HTMLPurifier $purifier;

    /** @var ResourceProvider */
    protected ResourceProvider $resourceProvider;

    /** @var CountryProvider */
    protected CountryProvider $countryProvider;

    /** @var AuthService */
    protected AuthService $authService;

    /** @var OrganizationService */
    protected OrganizationService $organizationService;

    /** @var ResourceService */
    protected ResourceService $service;

    protected ?User $user = null;

    /** @var bool */
    protected bool $isSuperAdmin;

    /** @var bool */
    protected bool $isSubjectSpecialist;

    /** @var bool */
    protected bool $isAdmin;

    /** @var array */
    protected array $administrableOrganizations;

    /** @var Organization | null */
    protected ?Organization $administeredOrganization;

    protected ?string $organization_id;

    /** @var string */
    protected string $language;

    /**
     * Those are commonly used parameters useful for every admin page
     * @var array
     */
    protected array $params;

    public function __construct(
        ResourceProvider $rp,
        AuthService $auth,
        OrganizationService $organizationService,
        CountryProvider $countryProvider,
        ResourceService $service
    ) {
        $config = \HTMLPurifier_Config::createDefault();
        $this->purifier = new \HTMLPurifier($config);

        $this->resourceProvider = $rp;
        $this->service = $service;
        $this->authService = $auth;
        $user = $this->authService->getAuthenticatedUser();

        $this->countryProvider = $countryProvider;
        $this->organizationService = $organizationService;

        $this->user = $user;

        $defaultLanguage = "de";
        $this->language = $user ? $user->getLanguage() : $defaultLanguage;
        $this->administrableOrganizations = $user ?
            $this->getAdministrableOrganizations($user, $organizationService) : [];

        // Get ubrId from session.
        $this->administeredOrganization =
            $this->getAdministratedOrganization($this->getSelectedOrganizationIdFromSession()) ?? null;

        $this->organization_id = $this->administeredOrganization ?
            $this->administeredOrganization->getUbrId() : null;

        if ($this->organization_id == "ALL") {
            if (count($this->administrableOrganizations) > 0) {
                $this->organization_id = $this->administrableOrganizations[0]->getUbrId();
                $this->administeredOrganization = $this->getAdministratedOrganization($this->organization_id);
            } else {
                $this->organization_id = null;
                $this->administeredOrganization = null;
            }
        }

        // Set selected org.
        $this->params['selectedOrganization'] = $this->administeredOrganization ?
            $this->administeredOrganization->toI18nAssocArray($this->language) : null;


        // Set session accordingly.
        $_SESSION["ubrId"] = $this->organization_id;

        $this->isSuperAdmin = $user && $user->isSuperadmin();
        $this->isAdmin = $user && $user->isAdmin($this->organization_id);
        $this->isSubjectSpecialist = $user && $user->isSubjectSpecialist($this->organization_id);

        $isFid = $this->administeredOrganization ? $this->administeredOrganization->getIsFID() || $this->isSuperAdmin: false;

        $this->params['settings'] = null;
        $settings = $this->organizationService->getSettings();
        if (count($settings) > 0) {
            $settings = $settings[0];
        }

        // admin-wide params are bound here
        // please bind all parameters used in base.twig here
        $this->params = [
            'lang' => $this->language,
            'settings' => $settings,
            'pageTitle' => "No title, please change",
            'i18n' => $rp->getAssocArrayForLanguage($this->language),
            'user' => $user ? $user->toAssocArray() : null,
            'isSuperAdmin' => $this->isSuperAdmin,
            'isAdmin' => $this->isAdmin,
            'isFid' => $isFid,
            'isSubjectSpecialist' => $this->isSubjectSpecialist,
            'language' => $this->language,
            'countries' => $countryProvider->getTranslatedCountryAssocArray($this->language),
            'flags' => $this->countryProvider->getUTF8FlagCountryAssocArray(),
            'administrableOrganizations' => array_map(function (Organization $o) {
                return $o->toI18nAssocArray($this->language);
            }, $this->administrableOrganizations),
            'selectedOrganization' => $this->administeredOrganization ?
                $this->administeredOrganization->toI18nAssocArray($this->language) : null,
            'domain' => $_SERVER['SERVER_NAME']
        ];
    }

    private function getAdministrableOrganizations(User $user, OrganizationService $os): array
    {
        // Suparadmins can manipulate all organizations
        if ($user->isSuperadmin()) {
            return $os->getOrganizations();
        } elseif ($user->isAdmin() || $user->isSubjectSpecialist()) {
        // Admins only can manipulate privileged organizations
            $ids = array_map(
                function (Privilege $p) {
                    return $p->getOrganizationId();
                },
                $user->getPrivileges()
            );
            $orgs = $os->getOrganizations(['ids' => $ids]);
            return $orgs ?? [];
        } else {
            return [];
        }
    }

    /**
     * @param string|null $orgId
     * @return Organization|null
     */
    private function getAdministratedOrganization(?string $orgId): ?Organization
    {
        if (is_null($orgId)) {
            return null;
        } else {
            try {
                return $this->organizationService->getOrganizationByUbrId($orgId);
            } catch (OrganizationWithUbrIdNotExistingException $e) {
                return null;
            }
        }
    }

    protected function getOrganizations(array $options = []) {
        return $this->organizationService->getOrganizations($options);
    }

    /**
     * @param string|null $orgId
     * @return void
     */
    protected function setAdministratedOrganization(?string $orgId): void
    {
        if ($orgId && strlen($orgId) > 0) {
            $this->administeredOrganization = $this->getAdministratedOrganization($orgId);

            // Set selected org.
            $this->params['selectedOrganization'] = $this->administeredOrganization ?
                $this->administeredOrganization->toI18nAssocArray($this->language) : null;

            // Set session accordingly.
            $_SESSION["ubrId"] = $this->administeredOrganization ?
                $this->administeredOrganization->getUbrId() : null;

            $this->organization_id = $_SESSION["ubrId"];

            $this->isSuperAdmin = $this->user && $this->user->isSuperadmin();
            $this->isAdmin = $this->user && $this->user->isAdmin($this->organization_id);
            $this->isSubjectSpecialist = $this->user && $this->user->isSubjectSpecialist($this->organization_id);

            $this->params['isSuperAdmin'] = $this->isSuperAdmin;
            $this->params['isAdmin'] = $this->isAdmin;
            $this->params['isSubjectSpecialist'] = $this->isSubjectSpecialist;
        }
    }

    /**
     * @return string|null
     */
    protected function getSelectedOrganizationIdFromSession(): ?string
    {
        return $_SESSION["ubrId"] ?? null;
    }

    protected function clearSelectedOrganization(): void
    {
        unset($_SESSION['ubrId']);
        $this->administeredOrganization = null;
        $this->params['selectedOrganization'] = null;
    }
}
