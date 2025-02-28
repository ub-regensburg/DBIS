<?php

namespace App\Infrastructure\Shared;

use App\Domain\Shared\AuthService;
use App\Domain\Shared\Entities\User;
use App\Domain\Shared\Entities\Privilege;
use App\Domain\Shared\Entities\PrivilegeType;
use App\Domain\Shared\Entities\PrivilegeAddon;
use App\Infrastructure\Shared\RZAuthProvider;
use App\Domain\Shared\Exceptions\AuthenticationFailedException;
use PDO;

/**
 * RZ Auth to DBIS Auth Adapter
 *
 * This service maps output and functions of RZ Auth Service to the model used
 * in DBIS.
 *
 * This function also serves as a provider for local privilege data!
 *
 */
class RZAuthToDbisAuthAdapter implements AuthService
{
    private RZAuthProvider $provider;
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->provider = new RZAuthProvider();
        $this->pdo = $pdo;
    }

    //put your code here
    public function getAuthenticatedUser(): ?User
    {
        return $_SESSION['user'] ?? null;
    }

    public function hasAuthenticatedUserRole(string $roleKey): bool
    {
        if ($roleKey == "admin") {
            return $this->getAuthenticatedUser()->isAdmin();
        } elseif ($roleKey == "superadmin") {
            return $this->getAuthenticatedUser()->isSuperadmin();
        }
        return false;
    }

    public function isSessionAuthenticated(): bool
    {
        return $this->getAuthenticatedUser() != null;
    }

    /**
     * @throws AuthenticationFailedException
     */
    public function login(string $login, string $password): bool
    {
        $success = $this->provider->login($login, $password);
        if ($success) {
            $_SESSION['user'] = $this->mapRZUserToDBISUser($this->provider->getAuthenticatedUser());
            return true;
        } else {
            throw new AuthenticationFailedException();
        }
    }

    public function logout(): void
    {
        unset($_SESSION);
        session_unset();
        session_destroy();
    }

    public function setAuthenticatedUser(User $user): void
    {
        $_SESSION['user'] = $user;
    }

    public function getUsers(array $query = []): array
    {
        if (!$this->isAccessingAsSuperadmin()) {
            return [];
        }
        return array_map(function ($rawUser) {
            return $this->mapRZUserToDBISUser($rawUser);
        }, $this->provider->getUsers());
    }

    private function isAccessingAsSuperadmin(): bool
    {
        return !($this->getAuthenticatedUser() == null
                || !$this->getAuthenticatedUser()->isSuperadmin());
    }

    public function getUserById(string $id): ?User
    {
        if (!$this->isAccessingAsSuperadmin()) {
            return null;
        }
        return $this->mapRZUserToDBISUser($this->provider->getUser($id));
    }

    public function persistUser(User $user): void
    {
        if (!$this->isAccessingAsSuperadmin()) {
            return;
        }
        $this->clearPrivilegesForUser($user);
        $this->persistPrivilegesForUser($user);
    }

    private function clearPrivilegesForUser(User $user): void
    {
        $sql = <<<EOD
                SELECT privilege.id from privilege 
                WHERE user_id = :user    
                EOD;
        $params = [
            "user" => $user->getId()
        ];

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        $privilege_ids = $statement->fetchAll(PDO::FETCH_ASSOC); 
               
        foreach($privilege_ids as $key => $value) {
            $id = (int) $value['id'];

            $sql = <<<EOD
                DELETE from privilege_addon_for_privilege 
                WHERE privilege = :id    
                EOD;
            $params = [
                "id" => $id
            ];
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);
        }        

        $sql = <<<EOD
                DELETE from privilege 
                WHERE user_id = :user    
                EOD;
        $params = [
            "user" => $user->getId()
        ];
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }

    private function persistPrivilegesForUser(User $user): void
    {
        foreach ($user->getPrivileges() as $cPrivilege) {
            $sql = <<<EOD
                    INSERT INTO privilege
                    (user_id, privilege_type, organization)
                    VALUES
                    (:user, :privilege_type, :organization);
                    EOD;
            $params = [
                "user" => $user->getId(),
                "privilege_type" => $cPrivilege->getType()->getId(),
                "organization" => $cPrivilege->getOrganizationId()
            ];
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);

            $privilege_id = (int)$this->pdo->lastInsertId();

            foreach($cPrivilege->getAddons() as $cAddon) {
                $sql = <<<EOD
                    INSERT INTO privilege_addon_for_privilege
                    (privilege, privilege_addon)
                    VALUES
                    (:privilege, :privilege_addon);
                    EOD;
                $params = [
                    "privilege" => $privilege_id,
                    "privilege_addon" => $cAddon->getId()
                ];
                $statement = $this->pdo->prepare($sql);
                $statement->execute($params);
            }
        }
    }

    private function mapRZUserToDBISUser(RZAuthUser $rzUser): User
    {
        $user = new User($rzUser->getId(), $rzUser->getEmail(), "de");
        $user->setId($rzUser->getId());
        $user->setPrename($rzUser->getPrename());
        $user->setSurname($rzUser->getSurname());
        $privileges = $this->getPrivilegesForUser($user);
        $user->setPrivileges($privileges);
        return $user;
    }

    private function getPrivilegesForUser(User $user)
    {
        $sql = <<<EOD
                select
                    privilege.*,
                    to_jsonb(privilege_type.*) as privilege_type,
                    coalesce(jsonb_agg(privilege_addon.*) filter (where privilege_addon.id is not null), '[]') as privilege_addons
                from
                    privilege
                left join privilege_type on
                    privilege_type.id = privilege.privilege_type
                left join privilege_addon_for_privilege on
                    privilege_addon_for_privilege.privilege = privilege.id
                left join privilege_addon on
                    privilege_addon.id = privilege_addon_for_privilege.privilege_addon
                where privilege.user_id=:user
                group by
                    privilege.id,
                    privilege_type.id;
                EOD;
        $params = [
                "user" => $user->getId()
            ];
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $result = array_map(function ($cEntry) {
            $typeRaw = json_decode($cEntry['privilege_type'], true);
            $type = $this->getPrivilegeTypeFromAssoc($typeRaw);
            $addonsRaw = json_decode($cEntry['privilege_addons'], true);
            $addons = $this->getPrivilegeAddonsFromAssoc($addonsRaw);
            $privilege = new Privilege("", $cEntry['organization'], []);
            $privilege->setType($type);
            $privilege->setAddons($addons);
            $privilege->setId((int)$cEntry['id']);
            return $privilege;
        }, $statement->fetchAll(PDO::FETCH_ASSOC));
        return $result;
    }

    private function getPrivilegeTypeFromAssoc(array $assoc): PrivilegeType
    {
        return new PrivilegeType(
            $assoc['id'],
            $assoc['name'],
            $assoc['title'],
            $assoc['help']
        );
    }

    private function getPrivilegeAddonFromAssoc(array $assoc): PrivilegeAddon
    {
        return new PrivilegeAddon(
            $assoc['id'],
            $assoc['name'],
            $assoc['title'],
            $assoc['help']
        );
    }

    private function getPrivilegeAddonsFromAssoc(array $assoc_list)
    {
        return array_map(function ($addonEntry) {
            return $this->getPrivilegeAddonFromAssoc($addonEntry);
        }, $assoc_list);
    }

    public function getPrivilegeTypes(): array
    {
        $sql = <<<EOD
            SELECT * FROM privilege_type;
            EOD;
        $statement = $this->pdo->prepare($sql);
        $statement->execute();
        return array_map(function ($cEntry) {
            $cEntry['name'] = $cEntry['name'];
            $cEntry['title'] = json_decode($cEntry['title'], true);
            $cEntry['help'] = json_decode($cEntry['help'], true);
            return $this->getPrivilegeTypeFromAssoc($cEntry);
        }, $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getPrivilegeAddons(): array
    {
        $sql = <<<EOD
            SELECT * FROM privilege_addon;
            EOD;
        $statement = $this->pdo->prepare($sql);
        $statement->execute();
        return array_map(function ($cEntry) {
            $cEntry['name'] = $cEntry['name'];
            $cEntry['title'] = json_decode($cEntry['title'], true);
            $cEntry['help'] = json_decode($cEntry['help'], true);
            return $this->getPrivilegeAddonFromAssoc($cEntry);
        }, $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getPrivilegeTypeById(int $id): ?PrivilegeType
    {
         $sql = <<<EOD
                SELECT * FROM privilege_type WHERE id=:id;
                EOD;
         $statement = $this->pdo->prepare($sql);
         $statement->execute([
             "id" => $id
         ]);
        return array_map(function ($cEntry) {
            $cEntry['name'] = $cEntry['name'];
            $cEntry['title'] = json_decode($cEntry['title'], true);
            $cEntry['help'] = json_decode($cEntry['help'], true);
            return $this->getPrivilegeTypeFromAssoc($cEntry);
        }, $statement->fetchAll(PDO::FETCH_ASSOC))[0] ?? null;
    }

    public function getPrivilegeAddonById(int $id): ?PrivilegeAddon
    {
         $sql = <<<EOD
                SELECT * FROM privilege_addon WHERE id=:id;
                EOD;
         $statement = $this->pdo->prepare($sql);
         $statement->execute([
             "id" => $id
         ]);
        return array_map(function ($cEntry) {
            $cEntry['name'] = $cEntry['name'];
            $cEntry['title'] = json_decode($cEntry['title'], true);
            $cEntry['help'] = json_decode($cEntry['help'], true);
            return $this->getPrivilegeAddonFromAssoc($cEntry);
        }, $statement->fetchAll(PDO::FETCH_ASSOC))[0] ?? null;
    }

    private function mapDBISUserToRZUser(User $u): RZAuthUser
    {
        $user = new RZAuthUser($u->getId());
        $user->setEmail($u->getEmail());
        $user->setPrename($u->getPrename());
        $user->setSurname($u->getSurname());

        if ($user->isAdmin()) {
            $user->setRole("admin");
        }
        if ($user->isSuperadmin()) {
            $user->setRole("superadmin");
        }
        return $user;
    }
}
