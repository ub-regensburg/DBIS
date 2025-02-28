<?php

namespace App\Domain\Resources\Entities;

use App\Domain\Shared\Serializable;

/**
 * Url
 *
 * Defines an api rul for a resource.
 *
 */
class Url implements Serializable
{
    /** @var ?int */
    private ? int $id;
    /** @var string */
    private string $url;

    public function __construct(
        string $url,
        int $id = null
    ) {
        $this->url = $url;
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


    public function getUrl() : ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }


    public function toAssocArray(): array
    {
        return [
            "id" => $this->getId(),
            "url" => $this->getUrl()
        ];
    }
}
