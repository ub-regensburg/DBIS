<?php

namespace App\Domain\Resources\Entities;

/**
 * License Type
 *
 */
class LicenseType
{
    /** @var int */
    private $id;
    /** @var array */
    private $title;
    /** @var array */
    private $description;
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

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): array
    {
        return $this->title;
    }

    public function getDescription(): array
    {
        return $this->description;
    }

    public function isGlobal(): bool
    {
        return $this->isGlobal;
    }

    public function setGlobal(bool $isGlobal): void
    {
        $this->isGlobal = $isGlobal;
    }

    public function toAssocArray(): array
    {
        return [
            "id" => $this->id,
            "title" => $this->title,
            "description" => $this->description,
            "isGlobal" => $this->isGlobal()
        ];
    }

    public function toI18nAssocArray($language): array
    {
        $result = $this->toAssocArray();
        $result['title'] = $result['title'][$language];
        $result['description'] = $result['description'] ? $result['description'][$language] : null;
        return $result;
    }
}
