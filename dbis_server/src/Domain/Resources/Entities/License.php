<?php

namespace App\Domain\Resources\Entities;

use App\Action\Api\v1\Organizations\GetOrganizationsAction;
use App\Domain\Resources\Entities\LicenseType;
use App\Domain\Resources\Entities\LicenseForm;
use App\Domain\Resources\Entities\LicenseLocalisation;
use App\Domain\Resources\Entities\Enterprise;
use App\Domain\Resources\Entities\Access;
use DateTime;

/**
 * License entity
 *
 */
class License
{
    private ?int $id;

    private ?int $resourceId = null;

    /** @var LicenseType */
    private LicenseType $type;

    /** @var ?LicenseForm */
    private ?LicenseForm $form = null;

    private ?int $numberOfConcurrentUsers = null;

    /** @var ?DateTime */
    private ?DateTime $validFromDate = null;

    /** @var ?DateTime */
    private ?DateTime $validToDate = null;

    /** @var bool */
    private bool $isActive = true;

    /** @var bool */
    private bool $textMiningAllowed = false;

    /** @var bool */
    private bool $isOA = false;

    /** @var ?Enterprise */
    private ?Enterprise $vendor = null;

    /** @var ?Enterprise */
    private ?Enterprise $publisher = null;

    /** @var ?string  */
    private ?string $lastCheck = null;

    /** @var ?string  */
    private ?string $aquired = null;

    /** @var ?string  */
    private ?string $cancelled = null;

    /** @var Access[] */
    private array $accesses = [];
    private ?array $internalNotes = null;
    private ?array $externalNotes = null;
    private bool $isAllowingWalking = false;

    private ?LicenseLocalisation $licenseLocalisation = null;

    private ?PublicationForm $publication_form = null;

     /** @var ExternalID[] */
     private ?array $external_resource_ids = [];

    /** @var ?string  */
    private ?string $fidId = null;

    /** @var bool */
    private bool $fidHostingPrivilege = false;

     /** @var null|string */
     private ?string $created_at = null;

     /** @var null|string */
     private ?string $modified_at = null;

    public function __construct(
        LicenseType $type,
        int $id = null
    ) {
        $this->id = $id ?: null;
        $this->type = $type;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getResourceId(): ?int
    {
        return $this->resourceId;
    }

    public function setResourceId(?int $resourceId): void
    {
        $this->resourceId = $resourceId;
    }

    public function getType(): LicenseType
    {
        return $this->type;
    }

    public function setType(LicenseType $type): void
    {
        $this->type = $type;
    }

    /**
     *
     * @param Access[] $accesses
     * @return void
     */
    public function setAccesses(array $accesses): void
    {
        $this->accesses = $accesses;
    }

    /**
     *
     * @return Access[]
     */
    public function getAccesses(): array
    {
        return $this->accesses;
    }

    public function getForm(): ?LicenseForm
    {
        return $this->form;
    }

    public function setForm(LicenseForm $form): void
    {
        $this->form = $form;
    }

    public function getNumberOfConcurrentUsers(): ?int
    {
        return $this->numberOfConcurrentUsers;
    }

    public function setNumberOfConcurrentUsers(?int $num): void
    {
        $this->numberOfConcurrentUsers = $num;
    }

    public function getValidFromDate(): ?DateTime
    {
        return $this->validFromDate;
    }

    public function setValidFromDate(DateTime $d): void
    {
        $this->validFromDate = $d;
    }

    public function getValidToDate(): ?DateTime
    {
        return $this->validToDate;
    }

    public function setValidToDate(DateTime $d): void
    {
        $this->validToDate = $d;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $active): void
    {
        $this->isActive = $active;
    }

    public function textMiningAllowed(): bool
    {
        return $this->textMiningAllowed;
    }

    public function setTextMiningAllowed(bool $textMiningAllowed): void
    {
        $this->textMiningAllowed = $textMiningAllowed;
    }

    public function setOA(bool $isOA): void
    {
        $this->isOA = $isOA;
    }

    public function isOA(): bool
    {
        return $this->isOA;
    }

    public function setAllowingWalking(bool $isAllowingWalking): void
    {
        $this->isAllowingWalking = $isAllowingWalking;
    }

    public function isAllowingWalking(): bool
    {
        return $this->isAllowingWalking;
    }

    public function getExternalNotes(): ?array
    {
        return $this->externalNotes;
    }

    public function setExternalNotes(?array $externalNotes): void
    {
        $this->externalNotes = $externalNotes;
    }

    public function getInternalNotes(): ?array
    {
        return $this->internalNotes;
    }

    public function setInternalNotes(?array $internalNotes): void
    {
        $this->internalNotes = $internalNotes;
    }

    public function setVendor(?Enterprise $vendor): void
    {
        $this->vendor = $vendor;
    }

    public function getVendor(): ?Enterprise
    {
        return $this->vendor;
    }

    public function setPublisher(?Enterprise $publisher): void
    {
        $this->publisher = $publisher;
    }

    public function getPublisher(): ?Enterprise
    {
        return $this->publisher;
    }

    public function getLastCheck(): ?string
    {
        return $this->lastCheck;
    }

    public function setLastCheck(?string $d): void
    {
        $this->lastCheck = $d;
    }

    public function getAquired(): ?string
    {
        return $this->aquired;
    }

    public function setAquired(?string $d): void
    {
        $this->aquired = $d;
    }

    public function getCancelled(): ?string
    {
        return $this->cancelled;
    }

    public function setCancelled(?string $d): void
    {
        $this->cancelled = $d;
    }

    public function setPublicationForm(PublicationForm $publication_form): void
    {
        $this->publication_form = $publication_form;
    }

    public function getPublicationForm(): ?PublicationForm
    {
        return $this->publication_form;
    }

    public function setLicenseLocalisation(LicenseLocalisation $licenseLocalisation): void
    {
        $this->licenseLocalisation = $licenseLocalisation;
    }

    public function getLicenseLocalisation(): ?LicenseLocalisation
    {
        return $this->licenseLocalisation;
    }

    public function setFID(?string $ubrId): void {
        $this->fidId = $ubrId;
    }

    public function getFID(): ?string {
        return $this->fidId;
    }

    public function setHostingPrivilege(bool $fidHostingPrivilege): void {
        $this->fidHostingPrivilege = $fidHostingPrivilege;
    }

    public function getHostingPrivilege(): bool {
        return $this->fidHostingPrivilege;
    }

    /**
     * @return ?string
     */
    public function getCreatedAt(): ?string
    {
        return $this->created_at;
    }

    /**
     * @param string|null $created_at
     */
    public function setCreatedAt(?string $created_at): void
    {
        $this->created_at = $created_at;
    }

     /**
     * @param string|null $modified_at
     */
    public function setModifiedAt(?string $modified_at): void
    {
        $this->modified_at = $modified_at;
    }

    /**
     * @return ?string
     */
    public function getModifiedAt(): ?string
    {
        return $this->modified_at;
    }

    /**
     * @param ExternalID[] $external_ids
     * @return void
     */
    public function setExternalIDs(array $external_ids): void
    {
        $this->external_resource_ids = $external_ids;
    }

    public function getExternalIDs(): ?array
    {
        return $this->external_resource_ids;
    }

    public function filterAccessesByOrganization($organizationId) {
        $this->accesses = array_filter($this->accesses, function ($a, $key) use ($organizationId) {
                return $a->getOrganizationId() == $organizationId || $a->getOrganizationId() == null;
        }, ARRAY_FILTER_USE_BOTH);
    }

        /**
     * @return array
     */
    public function validate(): array
    {
        $errors = array();

        if (count($this->accesses) < 1) {
            $errors['accesses'] = true;
        } else {
            $hasAccessLink = false;
            if ($this->type->getId() == 1) {
                foreach($this->accesses as $access) {
                    $isGlobal = $access->getOrganizationId() ? false : true;
                    if ($isGlobal && $access->getAccessUrl()) {
                        $hasAccessLink = true;
                    }
                }
            } else {
                $hasAccessLink = true;
            }

            if (!$hasAccessLink) {
                $errors['no_access_url'] = true;
            } 
        }

        return $errors;
    }

    public function toAssocArray(): array
    {
        return [
            "id" => $this->id,
            "resourceId" => $this->resourceId ? (int) $this->resourceId: null,
            "form" => $this->form ? $this->getForm()->toAssocArray() : null,
            "type" => $this->type ? $this->getType()->toAssocArray() : null,
            "licenseLocalisation" => $this->licenseLocalisation ? $this->getLicenseLocalisation()->toAssocArray() : null,
            "isActive" => $this->isActive,
            "textMiningAllowed" => $this->textMiningAllowed,
            "isOA" => $this->isOA,
            "fid" => $this->fidId,
            "fidHostingPrivilege" => $this->fidHostingPrivilege,
            "isAllowingWalking" => $this->isAllowingWalking,
            "numberOfConcurrentUsers" => $this->numberOfConcurrentUsers,
            "validFromDate" => $this->getValidFromDate(),
            "validToDate" => $this->getValidToDate(),
            "internalNotes" => $this->getInternalNotes(),
            "externalNotes" => $this->getExternalNotes(),
            "vendor" => $this->getVendor(),
            "publisher" => $this->getPublisher(),
            "accesses" => array_map(
                function ($a) {
                    return $a->toAssocArray();
                },
                $this->getAccesses()
            ),
            "lastCheck" => $this->getLastCheck(),
            "aquired" => $this->getAquired(),
            "cancelled" => $this->getCancelled(),
            "publicationForm" => $this->getPublicationForm() ? $this->getPublicationForm()->toAssocArray() : null,
            "external_ids" => array_map(
                function (ExternalID $e) {
                    return $e->toAssocArray();
                },
                $this->getExternalIDs()
            ),
            "created_at" => $this->created_at,
            "modified_at" => $this->modified_at,
        ];
    }

    public function toI18nAssocArray($language): array
    {
        $result = $this->toAssocArray();
        $result['type'] = $this->type ? $this->getType()->toI18nAssocArray($language) : null;
        $result['form'] = $this->form ? $this->getForm()->toI18nAssocArray($language) : null;
        $result['licenseLocalisation'] = $this->licenseLocalisation ? $this->getLicenseLocalisation()->toI18nAssocArray($language) : null;
        $result['internalNotes'] = $result['internalNotes'] ? $result['internalNotes'][$language] : null;
        $result['externalNotes'] = $result['externalNotes'] ? $result['externalNotes'][$language] : null;
        $result['vendor'] = $result['vendor'] ? $this->getVendor() : null;
        $result['publisher'] = $result['publisher'] ? $this->getPublisher() : null;
        $result['accesses'] = array_map(
            function (Access $a) use ($language) {
                return $a->toI18nAssocArray($language);
            },
            $this->getAccesses()
        );
        $result["publicationForm"] =
            $this->getPublicationForm() ? $this->getPublicationForm()->toI18nAssocArray($language) : null;
        return $result;
    }
}
