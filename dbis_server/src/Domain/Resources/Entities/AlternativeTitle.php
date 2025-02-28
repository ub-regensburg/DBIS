<?php

namespace App\Domain\Resources\Entities;

use App\Domain\Shared\Internationalizable;
use App\Domain\Shared\Serializable;
use DateTime;

/**
 * AlternativeTitle
 *
 * Defines an alternative title for a resource. The title can
 *
 */
class AlternativeTitle implements Serializable
{
    /** @var ?int */
    private ? int $id;
    /** @var string */
    private string $title;

    private ? DateTime $validFrom = null;
    private ? DateTime $validTo = null;

    public function __construct(
        string $title,
        int $id = null
    ) {
        $this->title = $title;
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


    public function getTitle() : ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }


    public function getValidFromDate(): ?DateTime
    {
        return $this->validFrom;
    }

    public function setValidFromDate(DateTime $validFrom): void
    {
        $this->validFrom = $validFrom;
    }


    public function getValidToDate(): ?DateTime
    {
        return $this->validTo;
    }

    public function setValidToDate(DateTime $validTo)
    {
        $this->validTo = $validTo;
    }


    public function toAssocArray(): array
    {
        return [
            "id" => $this->getId(),
            "title" => $this->getTitle(),
            "valid_from" => $this->getValidFromDate() ? $this->getValidFromDate()->format("Y-m-d") : null,
            "valid_to" => $this->getValidToDate() ? $this->getValidToDate()->format("Y-m-d") : null
        ];
    }
}
