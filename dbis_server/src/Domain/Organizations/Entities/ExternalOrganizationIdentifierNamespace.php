<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Entities;

/**
 * Description of ExternalOrganizationIdentifierNamespace
 *
 */
class ExternalOrganizationIdentifierNamespace
{
    /** @var array*/
    private $name;
    /** @var string*/
    private $id;

    public function __construct(
        string $id,
        array $name = null
    ) {
        if ($name != null) {
            $this->name = $name;
        }
        $this->id = $id;
    }

    public function getName(): ?array
    {
        return $this->name;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function toAssocArray(): array
    {
        return [
            "nsId" => $this->getId(),
            "name" => $this->getName() ? $this->getName() : null
        ];
    }

    public function toI18nAssocArray(string $lang): array
    {
        return [
            "nsId" => $this->getId(),
            "name" => $this->getName() ? $this->getName()[$lang] : null
        ];
    }
}
