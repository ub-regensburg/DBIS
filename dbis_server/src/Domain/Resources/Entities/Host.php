<?php

namespace App\Domain\Resources\Entities;

/**
 * Host
 *
 * Describes, where an access is hosted for users
 *
 */
class Host
{
    /** @var int */
    private ? int $id = null;
    /** @var string */
    private ? string $title = null;

    public function __construct()
    {
        // Constructor does not take arguments, because there are no real
        // mandatory fields, cases are
        // - Existing host: Just the id may be set, title may be null
        // - Not existing host: ID is not set yet, but title must be defined
    }

    public function getId() : ?int
    {
        return $this->id;
    }

    public function setId(int $id)
    {
        $this->id = $id;
    }

    public function getTitle() : ?string
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
          "id" => $this->getId(),
          "title" => $this->getTitle()
        ];
    }
}
