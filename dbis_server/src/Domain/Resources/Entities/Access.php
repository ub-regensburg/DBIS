<?php

namespace App\Domain\Resources\Entities;

use App\Domain\Resources\Entities\AccessType;
use App\Domain\Resources\Entities\AccessForm;
use App\Domain\Resources\Entities\Host;
use App\Domain\Resources\Entities\Resource;

/**
 * Access
 *
 * Describes, how a resource can be accessed, and under which circumstances.
 *
 */
class Access
{
    /** @var int */
    private ? int $id;
    /** @var array */
    private ? array $description = null;
    /** @var AccessType */
    private ? AccessType $type = null;
     /** @var AccessForm */
     private ? AccessForm $form = null;
    /** @var string */
    private ? string $accessUrl = null;
    /** @var string */
    private ? string $manualUrl = null;
    private ? array $label = null;
    private ? array $labelLong = null;
    private ? array $labelLongest = null;
    /** @var string */
    private ? string $url404 = null;
    /** @var array */
    private ? array $requirements = null;

    private ? Host $host = null;

    private ? string $organizationId = null;

    private ? string $state = null;

    /** @var int */
    private ? int $licenseId = null;

    private ? Resource $resource = null;

    /** @var string */
    private ? string $shelfmark = null;

    /** @var bool */
    private bool $isMainAccess = false;

    private bool $is_visible = true;

    private ? string $accessHash = null;

    /** @var int */
    private ? int $labelId = null;

    public function __construct(
        ?AccessType $type,
        int $id = null
    ) {
        $this->type = $type;
        $this->id = $id;
    }

    public function getId() : ?int
    {
        return $this->id;
    }

    public function setId(int $id) : void
    {
        $this->id = $id;
    }

    public function isMainAccess() : bool
    {
        return $this->isMainAccess;
    }

    public function setMainAccess(bool $isMainAccess) : void
    {
        $this->isMainAccess = $isMainAccess;
    }

    public function getLicenseId() : ?int
    {
        return $this->licenseId;
    }

    public function setLicenseId(int $licenseId) : void
    {
        $this->licenseId = $licenseId;
    }

    public function getType() : ?AccessType
    {
        return $this->type;
    }

    public function setForm(?AccessForm $form) : void
    {
        $this->form = $form;
    }

    public function getForm() : ?AccessForm
    {
        return $this->form;
    }

    public function getLabelId() : ?int
    {
        return $this->labelId;
    }

    public function setLabelId(?int $labelId) : void
    {
        $this->labelId = $labelId;
    }

    public function getLabel() : ?array
    {
        return $this->label;
    }

    public function setLabel(?array $label) : void
    {
        $this->label = $label;
    }

    public function getLongLabel() : ?array
    {
        return $this->labelLong;
    }

    public function setLongLabel(?array $label) : void
    {
        $this->labelLong = $label;
    }

    public function getLongestLabel() : ?array
    {
        return $this->labelLongest;
    }

    public function setLongestLabel(?array $label) : void
    {
        $this->labelLongest = $label;
    }

    public function getAccessUrl() : ?string
    {
        return $this->accessUrl;
    }

    public function setAccessUrl(?string $url) : void
    {
        $this->accessUrl = $url;
    }

    public function setAccessHash($hash) {
        $this->accessHash = $hash;
    }

    public function getAccessHash() {
        return $this->accessHash;
    }

    public function getManualUrl() : ?string
    {
        return $this->manualUrl;
    }

    public function setManualUrl(?string $url): void
    {
        $this->manualUrl = $url;
    }

    public function get404Url(): ?string
    {
        return $this->url404;
    }

    public function set404Url(?string $url): void
    {
        $this->url404 = $url;
    }

    public function getDescription(): ?array
    {
        return $this->description;
    }

    public function setDescription(?array $description): void
    {
        $this->description = $description;
    }

    public function getRequirements(): ?array
    {
        return $this->requirements;
    }

    public function setRequirements(?array $requirements): void
    {
        $this->requirements = $requirements;
    }

    public function setHost(?Host $host): void
    {
        $this->host = $host;
    }

    public function getHost(): ?Host
    {
        return $this->host;
    }

    public function getOrganizationId() : ?string
    {
        return $this->organizationId;
    }

    public function setOrganizationId(string $organizationId) : void
    {
        $this->organizationId = $organizationId;
    }

    public function getState() : ?string
    {
        return $this->state;
    }

    public function setState(string $state) : void
    {
        $this->state = $state;
    }

    public function getResource() : ?Resource
    {
        return $this->resource;
    }

    public function setResource(Resource $resource) : void
    {
        $this->resource = $resource;
    }

    public function getShelfmark() : ?string
    {
        return $this->shelfmark;
    }

    public function setShelfmark(string $shelfmark) : void
    {
        $this->shelfmark = $shelfmark;
    }

    public function setVisibility(bool $is_visible) {
        $this->is_visible = $is_visible;
    }

    public function getVisibility(): bool {
        return $this->is_visible;
    }

    public function toAssocArray(): array
    {
        return [
          "id" => $this->getId(),
          "type" => $this->getType() ? $this->getType()->toAssocArray() : null,
          "form" => $this->getForm() ? $this->getForm()->toAssocArray() : null,
          "license" => $this->getLicenseId() ? $this->getLicenseId() : null,
          "accessUrl" => $this->getAccessUrl(),
          "manualUrl" => $this->getManualUrl(),
          "_404Url" => $this->get404Url(),
          "label" => $this->getLabel(),
          "labelLong" => $this->getLongLabel(),
          "labelLongest" => $this->getLongestLabel(),
          "description" => $this->getDescription(),
          "requirements" => $this->getRequirements(),
          "host" => $this->getHost() ? $this->getHost()->toAssocArray() : null,
          "organization" => $this->getOrganizationId(),
          "state" => $this->getState(),
          "resource" => $this->getResource() ? $this->getResource()->toAssocArray() : null,
          "shelfmark" => $this->getShelfmark(),
          "isMainAccess" => $this->isMainAccess(),
          "is_visible" => $this->getVisibility(),
          "access_hash" => $this->getAccessHash(),
          "label_id" => $this->getLabelId()
        ];
    }

    public function toI18nAssocArray(string $language): array
    {
        $result = $this->toAssocArray();
        $result["type"] = $this->getType() ? $this->getType()->toI18nAssocArray($language) : null;
        $result["form"] = $this->getForm() ? $this->getForm()->toI18nAssocArray($language) : null;
        $result["description"] = $this->getDescription()[$language] ?? null;
        $result["host"] = $this->getHost() ? $this->getHost()->toAssocArray() : null;
        $result["requirements"] = $this->getRequirements()[$language] ?? null;
        $result["label"] = $this->getLabel()[$language] ?? null;
        $result["labelLong"] = $this->getLongLabel()[$language] ?? null;
        $result["labelLongest"] = $this->getLongestLabel()[$language] ?? null;
        $result["resource"] = $this->getResource() ? $this->getResource()->toI18nAssocArray($language) : null;
        return $result;
    }
}
