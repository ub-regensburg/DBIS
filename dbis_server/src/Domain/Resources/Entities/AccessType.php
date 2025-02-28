<?php

namespace App\Domain\Resources\Entities;

/**
 * AccessType
 *
 * Describes a kind of access, e.g. "Public", "VPN"...
 *
 */
class AccessType
{
    /** @var int */
    private int $id;
    /** @var array */
    private array $title;
    /** @var array */
    private ? array $description;
    private bool $isGlobal = false;

    public function __construct(
        int $id,
        array $title,
        array $description = null
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->description = $description;
    }

    public function getId() : int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setTitle(array $title): void
    {
        $this->title = $title;
    }

    public function getTitle(): array
    {
        return $this->title;
    }

    public function setDescription(?array $description): void
    {
        $this->description = $description;
    }

    public function getDescription(): ?array
    {
        return $this->description;
    }

    public function isGlobal(): bool
    {
        return $this->isGlobal;
    }

    public function setGlobal($isGlobal): void
    {
        $this->isGlobal = $isGlobal;
    }

    public function toAssocArray(): array
    {
        return [
            "id" => $this->getId(),
            "title" => $this->getTitle(),
            "description" => $this->getDescription(),
            "isGlobal" => $this->isGlobal()
        ];
    }

    public function toI18nAssocArray(string $language): array
    {
        $result = $this->toAssocArray();
        $result['title'] = $result['title'][$language];
        $result['description'] = $result['description'] ? $result['description'][$langauge] : null;
        return $result;
    }
}
