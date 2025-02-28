<?php

namespace App\Domain\Shared\Entities;

use App\Domain\Shared\Serializable;
use App\Domain\Shared\Internationalizable;

/**
 * PrivilegeType
 *
 */

class PrivilegeType implements Serializable, Internationalizable
{
    private int $id;
    private string $name;
    private array $title;
    private array $help;


    public function __construct(
        int $id,
        string $name,
        array $title,
        array $help
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->help = $help;
        $this->title = $title;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function toAssocArray(): array
    {
        $result = [
            "id" => $this->id,
            "name" => $this->name,
            "help" => $this->help,
            "title" => $this->title
        ];
        return $result;
    }

    public function toI18nAssocArray(string $language): array
    {
        $result = $this->toAssocArray();
        $result['help'] = $result['help'][$language];
        $result['title'] = $result['title'][$language];
        return $result;
    }
}
