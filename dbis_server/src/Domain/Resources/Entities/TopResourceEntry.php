<?php

namespace App\Domain\Resources\Entities;

use App\Domain\Resources\Entities\ResourceAggregate;
use App\Domain\Shared\Serializable;
use App\Domain\Shared\Internationalizable;

/*
 * TopResourceEntry
 *
 * Struct-like for storing information for a TOP-database nomination.
 *
 */


class TopResourceEntry implements Serializable, Internationalizable
{
    private string $organizationId;
    private int $resourceId;
    private ResourceAggregate $subject;
    private int $order = 0;

    public function __construct(
        string $organizationId,
        int $resourceId,
        ResourceAggregate $subject
    ) {
        $this->organizationId = $organizationId;
        $this->resourceId = $resourceId;
        $this->subject = $subject;
    }

    public function getOrganizationId(): string
    {
        return $this->organizationId;
    }

    public function getResourceId(): int
    {
        return $this->resourceId;
    }

    public function setResourceId(int $id): void
    {
        $this->resourceId = $id;
    }

    public function getSubject(): ResourceAggregate
    {
        return $this->subject;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function setOrder(int $order): void
    {
        $this->order = $order;
    }

    public function isCollection(): bool
    {
        return $this->subject->isCollection();
    }

    public function toAssocArray(): array
    {
        return [
          "organizationId" => $this->organizationId,
          "resourceId" => $this->resourceId,
          "subject" => $this->subject->toAssocArray(),
          "order" => $this->order
        ];
    }

    public function toI18nAssocArray(string $language): array
    {
        $assoc = $this->toAssocArray();
        $assoc["subject"] = $this->subject->toI18nAssocArray($language);
        return $assoc;
    }
}
