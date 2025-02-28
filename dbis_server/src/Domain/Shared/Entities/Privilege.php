<?php

namespace App\Domain\Shared\Entities;

use App\Domain\Shared\Entities\PrivilegeType;
use App\Domain\Shared\Entities\PrivilegeAddon;
use App\Domain\Shared\Serializable;
use App\Domain\Shared\Internationalizable;

/**
 * Privilege
 *
 * Object to define, what a user or user group may do with something (e.g. an
 * organization)
 *
 */

class Privilege implements Serializable, Internationalizable
{
    /** Organization, for which the privilege has been granted
     * @var string */
    private ? string $organizationId = null;
    /** @var string */
    private $privilege;

    private PrivilegeType $type;

    private array $addons;

    /** Additional info for the privilege, can be freeform or empty array
     * @var array */
    private array $extras;

    private ? int $id = null;


    public function __construct(
        string $privilege,
        string $organizationId = null,
        array $addons = [],
        array $extras = []
    ) {
        $this->privilege = $privilege;
        if ($organizationId) {
            $this->organizationId = $organizationId;
        }
        $this->addons = $addons;
        $this->extras = $extras;
    }

    public function setType(PrivilegeType $type)
    {
        $this->type = $type;
    }

    public function getType() : PrivilegeType
    {
        return $this->type;
    }

    public function setAddons($addons)
    {
        $this->addons = $addons;
    }

    public function getAddons()
    {
        return $this->addons;
    }

    public function setId(int $id) : void
    {
        $this->id = $id;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganizationId(): ?string
    {
        return $this->organizationId;
    }

    public function getPrivilege(): string
    {
        return $this->privilege;
    }

    public function getExtras(): array
    {
        return $this->extras;
    }

    public function toAssocArray(): array
    {
        return [
            "id" => $this->getId(),
            "privilege" => $this->privilege,
            "type" => $this->type->toAssocArray(),
            "addons" => array_map(
                function ($a) {
                    return $a->toAssocArray();
                },
                $this->getAddons()
            ),
            "organizationId" => $this->organizationId ?? null,
            "extras" => $this->extras
        ];
    }

    public function toI18nAssocArray(string $language): array
    {
        $assoc = $this->toAssocArray();
        $assoc['type'] = $this->type->toI18nAssocArray($language);
        $assoc['addons'] = array_map(
            function ($a) use ($language) {
                return $a->toI18nAssocArray($language);
            }, $this->getAddons());
        return $assoc;
    }

    /**
     * Helper method for comparing two privileges
     * @param Privilege $privilege1
     * @param Privilege $privilege2
     * @return type
     */
    public static function isEqual(Privilege $privilege1, Privilege $privilege2, bool $isRespectingExtras)
    {
        return $privilege1->getOrganizationId() == $privilege2->getOrganizationId() &&
                $privilege1->getPrivilege() == $privilege2->getPrivilege() &&
                ($isRespectingExtras || json_encode($privilege1->getExtras()) == json_encode($privilege2->getExtras()));
    }
}
