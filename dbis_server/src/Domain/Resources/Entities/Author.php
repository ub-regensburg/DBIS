<?php

namespace App\Domain\Resources\Entities;

use App\Domain\Shared\Internationalizable;
use App\Domain\Shared\Serializable;

/**
 * Author entity
 *
 */
class Author implements Serializable
{
    /** @var int|null */
    private ? int $id  = null;

    /** @var string */
    private string $title;


    public function __construct(string $title)
    {
        $this->title = $title;
    }

    /**
     * @return ?int
     */
    public function getId() : ?int
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
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function toAssocArray(): array
    {
        return [
            "title" => $this->title,
            "id" => $this->id
        ];
    }
}
