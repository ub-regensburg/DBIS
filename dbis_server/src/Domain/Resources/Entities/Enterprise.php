<?php

namespace App\Domain\Resources\Entities;

/**
 * Enterprise
 * Is a joint entity for license vendors and license publishers.
 *
 */
class Enterprise
{
    /** @var int */
    private ?int $id;
    /** @var string */
    private string $title;

    public function __construct(
        ?int $id = null
    ) {
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function toAssocArray(): array
    {
        return [
            "id" => $this->id,
            "title" => $this->title
        ];
    }
}
