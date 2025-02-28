<?php

namespace App\Domain\Resources\Entities;

use App\Domain\Shared\Internationalizable;
use App\Domain\Shared\Serializable;

/**
 * Type entity
 *
 */
class Country implements Internationalizable, Serializable
{
    /** @var int */
    private int $id;

    /** @var array|null */
    private ? array $title = null;
    /** @var string|null */
    private ? string $code = null;

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
    public function getTitle() : ?array
    {
        return $this->title;
    }

    /**
     * @param array $title
     */
    public function setTitle(array $title) : void
    {
        $this->title = $title;
    }

    /**
     * @return ?string
     */
    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * @param string $code
     */
    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function toAssocArray(): array
    {
        return [
            "title" => $this->title,
            "code" => $this->code,
            "id" => $this->id
        ];
    }

    public function toI18nAssocArray(string $language): array
    {
        $assoc = $this->toAssocArray();

        if (!is_null($assoc['title']) && key_exists($language, $assoc['title'])) {
            $assoc['title'] = $assoc['title'][$language];
        }

        return $assoc;
    }
}
