<?php

namespace App\Domain\Resources\Entities;

use App\Domain\Shared\Internationalizable;
use App\Domain\Shared\Serializable;

/**
 * Type entity
 *
 */
class PublicationForm implements Internationalizable, Serializable
{
    /** @var int */
    private int $id;

    /** @var array|null */
    private ? array $title  = null;
    /** @var array|null */
    private ? array $description  = null;


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
    public function setId(int $id) : void
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
     * @return ?array
     */
    public function getDescription(): ?array
    {
        return $this->description;
    }

    /**
     * @param array $description
     */
    public function setDescription(array $description): void
    {
        $this->description = $description;
    }

    public function toAssocArray(): array
    {
        return [
            "title" => $this->title,
            "description" => $this->description,
            "id" => $this->id
        ];
    }

    public function toI18nAssocArray(string $language): array
    {
        $assoc = $this->toAssocArray();
        if (!is_null($assoc['title'])) {
            $assoc['title'] = $assoc['title'][$language];
        }
        if (!is_null($assoc['description'])) {
            $assoc['description'] = $assoc['description'][$language];
        }

        return $assoc;
    }
}
