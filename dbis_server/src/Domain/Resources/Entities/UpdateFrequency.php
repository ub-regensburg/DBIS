<?php

namespace App\Domain\Resources\Entities;

/**
 * Type entity
 *
 */
class UpdateFrequency
{
    /** @var int */
    private int $id;

    /** @var null|array */
    private ? array $title  = null;

    public function __construct(int $id)
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getId() : int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return ?array
     */
    public function getTitle(): ?array
    {
        return $this->title;
    }

    /**
     * @param array $title
     */
    public function setTitle(array $title): void
    {
        $this->title = $title;
    }

    /**
     * @return array
     */
    public function toAssocArray(): array
    {
        return [
            "title" => $this->title,
            "id" => $this->id
        ];
    }

    /**
     * @return array
     */
    public function toI18nAssocArray(string $language): array
    {
        $assoc = $this->toAssocArray();
        $assoc['title'] = $assoc['title'][$language];
        return $assoc;
    }
}
