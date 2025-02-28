<?php

namespace App\Infrastructure\Shared;

/**
 * RZAuthProvider
 *
 * Class with useful functions for authenticating at the ur-rz ldap
 *
 * Prerequisites: Download the ca-cert for the ldap.service and place it in
 * the same folder as the script - or in another folder, in this case
 * you have to pass the location in the constructor
 *
 */
class RZAuthProvider
{
    private string $ldapHost = "";
    private string $dnOrganization = "ubr";
    private string $dnOrganizationUnit = "dbusers";
    private string $caCertLocation = "";
    private $ldap;

    public function __construct(string $caCertLocation = null)
    {
        require_once __DIR__ . '/../../../config/DotEnv.php';

        loadDotEnv("/var/www/.env");

        $this->ldapHost = getenv('LDAP_SERVER');

        $this->caCertLocation = $caCertLocation ?? $this->caCertLocation;
        $this->caCertLocation = getenv('LDAP_CERT');

        putenv('LDAPTLS_CACERT=' . $this->caCertLocation);
        $this->ldap = ldap_connect("ldap://" . $this->ldapHost);
        ldap_set_option($this->ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($this->ldap, LDAP_OPT_REFERRALS, 0);
        ldap_start_tls($this->ldap);
    }

    /**
     * @param string $login mail-address or id of user
     * @param string $password password of user
     * @return bool true if login is successful, fals if login failed
     */
    public function login(string $login, string $password): bool
    {
        if ($this->isLoginEmail($login)) {
            $login = $this->getCnForEmail($login);
        }

        $bindResult = @ldap_bind(
            $this->ldap,
            "cn={$login},ou={$this->dnOrganizationUnit},o={$this->dnOrganization}",
            $password
        );
        if ($bindResult) {
            $_SESSION["rzuser"] = $this->getUser($login)->toAssocArray();
        }

        return $bindResult;
    }

    private function isLoginEmail(string $login)
    {
        return filter_var($login, FILTER_VALIDATE_EMAIL);
    }

    private function getCnForEmail(string $mail)
    {
        $dn = "ou={$this->dnOrganizationUnit},o={$this->dnOrganization}";
        $filter = "(mail={$mail})";
        $sr = ldap_search($this->ldap, $dn, $filter);
        $entry = ldap_get_entries($this->ldap, $sr);
        return $entry[0]["cn"][0];
    }

    public function logout(): void
    {
        unset($_SESSION);
        session_unset();
    }

    public function getUser(string $login): RZAuthUser
    {
        $dn = "cn={$login},ou={$this->dnOrganizationUnit},o={$this->dnOrganization}";
        $filter = "(cn={$login})";
        $sr = ldap_read($this->ldap, $dn, $filter);
        $entry = ldap_get_entries($this->ldap, $sr);
        return $this->mapLdapEntryToRZAuthUser($entry[0]);
    }

    /**
     * Warning - this function can be critical in terms of privacy!
     *
     * ALWAYS PROTECT ITS OUTPUT, SO THAT IT CAN BE ONLY SEEN BY TRUSTED AND
     * LEGITIMATE USERS (e.g. superadmins)
     *
     * @param array $query
     * - q: query in usernames
     * @return RZAuthUser[]
     */
    public function getUsers(array $query = []): array
    {
        $dn = "ou={$this->dnOrganizationUnit},o={$this->dnOrganization}";
        $sr = ldap_search($this->ldap, $dn, '(mail=*)');
        $entries = ldap_get_entries($this->ldap, $sr);

        unset($entries['count']);
        return array_map(function ($cEntry) {
            return $this->mapLdapEntryToRZAuthUser($cEntry);
        }, $entries);
    }

    private function mapLdapEntryToRZAuthUser(array $input): RZAuthUser
    {
        $groupMembership = $input['groupmembership'][0] ?? null;
        $userGroup = $groupMembership ? $this->getUserGroup($groupMembership) : null;
        $user = new RZAuthUser($input['cn'][0]);
        $user->setEmail($input['mail'][0]);
        $user->setPrename($input['givenname'][0]);
        $user->setSurname($input['sn'][0]);
        if ($userGroup) {
            $user->setRole($userGroup);
        }
        return $user;
    }

    private function getUserGroup(string $group): string
    {
        $groups = [];
        preg_match('/cn=([^,]*)/', $group, $groups);
        return $groups[1];
    }

    /**
     * @return RZAuthUser|null
     */
    public function getAuthenticatedUser(): ?RZAuthUser
    {
        if (!$this->hasAuthenticatedUser()) {
            return null;
        }
        return RZAuthUser::fromAssocArray($_SESSION["rzuser"]);
    }

    /**
     * @return bool
     */
    public function hasAuthenticatedUser(): bool
    {
        return isset($_SESSION["rzuser"]);
    }

    public function setAuthenticatedUser(RZAuthUser $user): void
    {
        $_SESSION["rzuser"] = $user->toAssocArray();
    }
}


// @codingStandardsIgnoreStart
class RZAuthUser {

    private string $id;
    private string $email;
    private string $prename;
    private string $surname;

    private string $language = "de";

    private bool $isAdmin = false;
    private bool $isSuperadmin = false;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setPrename(string $prename): void
    {
        $this->prename = $prename;
    }

    public function getPrename(): string
    {
        return $this->prename;
    }

    public function setSurname(string $surname): void
    {
        $this->surname = $surname;
    }

    public function getSurname(): string
    {
        return $this->surname;
    }

    public function setRole(string $roleStr)
    {
        if($roleStr == "admin")
        {
            $this->isAdmin = true;
        }
        if($roleStr == "superadmin")
        {
            $this->isSuperadmin = true;
        }
    }

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function isSuperadmin(): bool
    {
        return $this->isSuperadmin;
    }

    public function setLanguage(string $language)
    {
        $this->language = $language;
    }

    public function getLanguage(): string {
        return $this->language;
    }

    public function toAssocArray(): array
    {
        return [
            "id" => $this->getId(),
            "surname" => $this->getSurname(),
            "prename" => $this->getPrename(),
            "email" => $this->getEmail(),
            "isAdmin" => $this->isAdmin(),
            "isSuperadmin" => $this->isSuperadmin(),
            "language" => $this->getLanguage()
        ];
    }

    public static function fromAssocArray(array $input): RZAuthUser
    {
        $user = new RZAuthUser($input["id"]);
        $user->setEmail($input['email']);
        $user->setPrename($input['prename']);
        $user->setSurname($input['surname']);
        $user->setLanguage($input['language']);
        if($input['isAdmin'])
        {
            $user->setRole('admin');
        }
        if($input['isSuperadmin'])
        {
            $user->setRole('superadmin');
        }
        return $user;
    }
}
// @codingStandardsIgnoreEnd
