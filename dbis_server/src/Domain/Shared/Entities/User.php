<?php

declare(strict_types=1);

namespace App\Domain\Shared\Entities;

use App\Domain\Shared\Entities\Privilege;
use App\Domain\Shared\Serializable;
use App\Domain\Shared\Internationalizable;

/**
 * User entity
 *
 */
class User implements Serializable, Internationalizable
{
    private $nickname;
    private $email;
    private $language;
    private $id;
    private $prename;
    private $surname;



    /** @var Privilege[] */
    private $privileges = [];

    public function __construct(string $nickname, string $email, string $language = "de")
    {
        $this->nickname = $nickname;
        $this->email = $email;
        $this->language = $language;
        $this->privileges = [];
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): void
    {
        $this->language = $language;
    }

    public function getNickname(): string
    {
        return $this->nickname;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /** @return Privilege[] */
    public function getPrivileges(): array
    {
        return $this->privileges ?? [];
    }

    /** @param Privilege[] $privilege
     * @return void
     */
    public function setPrivileges(array $privileges): void
    {
        $this->privileges = $privileges;
    }

    public function addPrivilege(Privilege $privilege): void
    {
        if (!$this->containsPrivilege($privilege)) {
            array_push($this->privileges, $privilege);
        }
    }

    public function removePrivilegeById(int $id)
    {
        $this->privileges = array_filter($this->privileges, function (Privilege $item, int $key) use ($id) {
            return $item->getId() != $id;
        }, ARRAY_FILTER_USE_BOTH);
    }

    public function containsPrivilege(Privilege $privilege, bool $isRespectingExtras = false)
    {
        $filteredPrivileges = array_filter(
            $this->privileges,
            function (Privilege $cPrivilege, int $key) use ($privilege, $isRespectingExtras) {
                return Privilege::isEqual($cPrivilege, $privilege, $isRespectingExtras);
            },
            ARRAY_FILTER_USE_BOTH
        );
        return count($filteredPrivileges) > 0;
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


    public function isAdmin($organization_id = null): bool
    {   
        $adminPrivs = [];

        if ($organization_id) {
            $adminPrivs = array_filter(
                $this->getPrivileges(),
                function (Privilege $p, $key) use ($organization_id) {
                    return $p->getType()->getId() == 1 && $organization_id == $p->getOrganizationId();
                },
                ARRAY_FILTER_USE_BOTH
            );
        } else {
            $adminPrivs = array_filter(
                $this->getPrivileges(),
                function (Privilege $p, $key) {
                    return $p->getType()->getId() == 1;
                },
                ARRAY_FILTER_USE_BOTH
            );
        }

        
        return count($adminPrivs) > 0 || $this->isSuperadmin();
    }

    public function isSubjectSpecialist($organization_id = null): bool
    {
        $adminPrivs = [];

        if ($organization_id) {
            $adminPrivs = array_filter(
                $this->getPrivileges(),
                function (Privilege $p, $key) use ($organization_id) {
                    return $p->getType()->getId() == 2 && $organization_id == $p->getOrganizationId();
                },
                ARRAY_FILTER_USE_BOTH
            );
        } else {
            $adminPrivs = array_filter(
                $this->getPrivileges(),
                function (Privilege $p, $key) {
                    return $p->getType()->getId() == 2;
                },
                ARRAY_FILTER_USE_BOTH
            );
        }
        
        return count($adminPrivs) > 0;
    }

    public function isSubjectSpecialistFor($ubrId): bool
    {
        $adminPrivs = array_filter(
            $this->getPrivileges(),
            function (Privilege $p, $key) use ($ubrId) {
                return $p->getType()->getId() == 2 && $p->getOrganizationId() == $ubrId;
            },
            ARRAY_FILTER_USE_BOTH
        );
        // Superadmins may act as specialists for all organizations
        return count($adminPrivs) > 0 || $this->isSuperadmin();
    }

    public function isAdminFor($ubrId): bool
    {
        $adminPrivs = array_filter(
            $this->getPrivileges(),
            function (Privilege $p, $key) use ($ubrId) {
                return ($p->getType()->getId() == 1) && $p->getOrganizationId() == $ubrId;
            },
            ARRAY_FILTER_USE_BOTH
        );
        // Superadmins may act as admins for all organizations
        return count($adminPrivs) > 0 || $this->isSuperadmin();
    }

    public function isSuperadmin(): bool
    {
        $adminPrivs = array_filter(
            $this->getPrivileges(),
            function (Privilege $p, $key) {
                return $p->getType()->getId() == 1000;
            },
            ARRAY_FILTER_USE_BOTH
        );
        return count($adminPrivs) > 0;
    }

    public function hasPrivilegesToCreateNationalLicenses($ubrId): bool {
        foreach ($this->getPrivileges() as $privilege) {
            if ($privilege->getOrganizationId() == $ubrId) {
                foreach ($privilege->getAddons() as $privilege_addon) {
                    if ($privilege_addon->getName() == "national") {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function hasPrivilegesToCreateConsortialLicenses($ubrId): bool {
        foreach ($this->getPrivileges() as $privilege) {
            if ($privilege->getOrganizationId() == $ubrId) {
                foreach ($privilege->getAddons() as $privilege_addon) {
                    if ($privilege_addon->getName() == "consortial") {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function toAssocArray(): array
    {
        return [
            "id" => $this->id,
            "nickname" => $this->nickname,
            "prename" => $this->prename,
            "surname" => $this->surname,
            "email" => $this->email,
            "language" => $this->language,
            "privileges" => array_map(
                function (Privilege $p) {
                    return $p->toAssocArray();
                },
                $this->getPrivileges()
            )
        ];
    }

    public function toI18nAssocArray(string $language): array
    {
        $assoc = $this->toAssocArray();
        $assoc['privileges'] = array_map(
            function (Privilege $p) use ($language) {
                return $p->toI18nAssocArray($language);
            },
            $this->getPrivileges()
        );
        return $assoc;
    }
}
