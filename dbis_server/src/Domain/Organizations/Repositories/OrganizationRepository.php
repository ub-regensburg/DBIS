<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Repositories;

use App\Domain\Organizations\Entities\Organization;
use App\Domain\Organizations\Entities\ExternalOrganizationIdentifier;
use App\Domain\Organizations\Exceptions\OrganizationWithDbisIdNotExistingException;

/**
 * OrganizationRepository
 *
 * Repository for managing organizations in DBIS.
 *
 */
interface OrganizationRepository
{
    public function createOrganization(Organization $organization): void;
    /**
     * @param array $options
     * @return Organization[]
     */
    public function getOrganizations(array $options = null): array;
    public function getOrganizationByUbrId(string $id): Organization;
    public function getOrganizationByDbisId(string $id): Organization;
    /**
     * @throws OrganizationWithDbisIdNotExistingException
     */
    public function getUbrIdForDbisId(string $dbisId): string;
    public function getDbisIdForUbrId(string $ubrId): ?string;
    public function getOrganizationByIp(string $ip): ?Organization;
    public function updateOrganization(Organization $Org): void;
    public function storeIconForOrganization(Organization $org, $logoFile): string;
    public function deleteOrganizationById(string $ubrId): void;
    public function existsOrganizationwithUbrId(string $ubrId, bool $isIncludingDeleted = false): bool;
    public function existsOrganizationwithDbisId(string $dbisId): bool;
    public function existsOrganizationwithIp(string $ip): bool;
    public function getFIDs(): array;
    /**
     *
     * @return ExternalOrganizationIdentifier[]
     */
    public function getIdentifierNamespaces(): array;

    public function getSettings(): array;

    public function updateSettings(array $settings): void;
}
