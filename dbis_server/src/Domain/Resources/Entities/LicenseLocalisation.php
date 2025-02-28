<?php

namespace App\Domain\Resources\Entities;


class LicenseLocalisation
{
    private ?int $id;

    private ?int $license_id;

    private string $organisation;

    private ?array $internal_notes = null;
    private ?array $external_notes = null;

    /** @var null|string */
    private ?string $aquired = null;

    /** @var null|string */
    private ?string $cancelled = null;

    /** @var null|string */
    private ?string $input_date = null;

    /** @var null|string */
    private ?string $last_check = null;

    public function __construct(
        string $organisation,
        ?int $license_id
    ) {
        $this->id = null;
        $this->license_id = $license_id ?: null;
        $this->organisation = $organisation;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getExternalNotes(): ?array
    {
        return $this->external_notes;
    }

    public function setExternalNotes(?array $externalNotes): void
    {
        $this->external_notes = $externalNotes;
    }

    public function getInternalNotes(): ?array
    {
        return $this->internal_notes;
    }

    public function setInternalNotes(?array $internal_notes): void
    {
        $this->internal_notes = $internal_notes;
    }


    public function getOrganisation(): string
    {
        return $this->organisation;
    }

    public function setOrganisation(string $organisation): void
    {
        $this->organisation = $organisation;
    }

    public function setLicenseId(int $license_id): void
    {
        $this->license_id = $license_id;
    }

    public function getLicenseId(): ?int
    {
        return $this->license_id;
    }

    public function getLastCheck(): ?string
    {
        return $this->last_check;
    }

    public function setLastCheck(?string $d): void
    {
        $this->last_check = $d;
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

    public function getInputDate(): ?string
    {
        return $this->input_date;
    }

    public function setInputDate(?string $d): void
    {
        $this->input_date = $d;
    }

    public function toAssocArray(): array
    {
        return [
            "id" => $this->id,
            "license" => $this->getLicenseId(),
            "organisation" => $this->getOrganisation(),
            "internalNotes" => $this->getInternalNotes(),
            "externalNotes" => $this->getExternalNotes(),
            "lastCheck" => $this->getLastCheck(),
            "aquired" => $this->getAquired(),
            "cancelled" => $this->getCancelled(),
            "inputDate" => $this->getInputDate()
        ];
    }

    public function toI18nAssocArray($language): array
    {
        $result = $this->toAssocArray();

        $result['internalNotes'] = $result['internalNotes'] ? $result['internalNotes'][$language] : null;
        $result['externalNotes'] = $result['externalNotes'] ? $result['externalNotes'][$language] : null;

        return $result;
    }
}
