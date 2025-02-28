<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Entities;

use App\Domain\Organizations\Entities\ExternalOrganizationIdentifierNamespace;

/**
 * Description of ExternalOrganizationIdentifier
 *
 */
class ExternalOrganizationIdentifier
{
    /** @var ExternalOrganizationIdentifierNamespace */
    private $namespace;
    /** @var string*/
    private $key;

    public function __construct(
        string $key,
        ExternalOrganizationIdentifierNamespace $namespace
    ) {
        $this->key = $key;
        $this->namespace = $namespace;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getNamespace(): ExternalOrganizationIdentifierNamespace
    {
        return $this->namespace;
    }

    public function toAssocArray(): array
    {
        return [
            "namespace" => $this->getNamespace()->toAssocArray(),
            "key" => $this->getKey()
        ];
    }

    public function toI18nAssocArray(string $lang): array
    {
        return [
            "namespace" => $this->getNamespace()->toI18nAssocArray($lang),
            "key" => $this->getKey()
        ];
    }
}
