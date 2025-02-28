<?php

namespace App\Domain\Resources\Entities;

/**
 * RESOURCE AGGREGATE
 *
 * Formerly known as "TreatedAsSubject"; adds capabilities of collecting one
 * or multiple resources by their ids.
 *
 * This may be useful for listing subjects and collecitons treated as subjects,
 * since they can be represented in a unified way.
 */
interface ResourceAggregate
{
    public function getId(): ?int;
    public function getTitle(): ?array;
    public function getResourceIds(): ?array;
    public function setResourceIds(array $ids): void;
    public function getResourceCount(): ?int;

    public function getType(): string;

    public function treatedAsSubject(): bool;
    public function isCollection(): bool;

    public function toAssocArray(): array;
    public function toI18nAssocArray(string $language): array;
}
