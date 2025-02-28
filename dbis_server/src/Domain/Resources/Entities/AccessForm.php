<?php

namespace App\Domain\Resources\Entities;


class AccessForm
{
    /** @var int */
    private int $id;
    /** @var array */
    private ? array $title = null;

    public function __construct(
        int $id,
        array $title = null
    ) {
        $this->id = $id;
        $this->title = $title;
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

    public function toAssocArray(): array
    {
        return [
            "id" => $this->getId(),
            "title" => $this->getTitle()
        ];
    }

    public function toI18nAssocArray(string $language): array
    {
        $result = $this->toAssocArray();
        $result['title'] = $result['title'][$language];
        return $result;
    }
}
