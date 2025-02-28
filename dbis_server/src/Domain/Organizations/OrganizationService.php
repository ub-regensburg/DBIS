<?php

declare(strict_types=1);

namespace App\Domain\Organizations;

use App\Domain\Organizations\Exceptions\OrganizationWithDbisIdNotExistingException;
use App\Domain\Organizations\Repositories\OrganizationRepository;
use App\Domain\Organizations\Entities\Organization;
use App\Domain\Organizations\Entities\ExternalOrganizationIdentifierNamespace;
use App\Domain\Organizations\Exceptions\OrganizationWithUbrIdExistingException;
use App\Domain\Organizations\Exceptions\OrganizationWithDbisIdExistingException;
use App\Domain\Organizations\Exceptions\OrganizationWithUbrIdNotExistingException;
use App\Domain\Organizations\Exceptions\OrganizationWithIpNotExistingException;
use App\Domain\Organizations\Exceptions\OrganizationWithUbrIdTakenException;
use App\Domain\Organizations\Exceptions\OrganizationAlreadyContainsDbisViewException;
use App\Domain\Organizations\Exceptions\OrganizationDoesNotHaveDbisViewException;
use App\Domain\Organizations\Entities\DbisView;

/**
 * OrganizationService
 *
 * Domain service for organizations. Handles all domain logic.
 *
 */
class OrganizationService
{
    /** @type OrganizationRepository */
    private $repository;

    public function __construct(
        OrganizationRepository $repo
    ) {
        $this->repository = $repo;
    }

    public function createOrganization(Organization $organization, $icon = null): void
    {
        if ($this->repository->existsOrganizationwithUbrId($organization->getUbrId())) {
            throw new OrganizationWithUbrIdExistingException($organization->getUbrId());
        }
        if ($this->repository->existsOrganizationwithUbrId($organization->getUbrId(), true)) {
            // this checks, whether a previously deleted org has had the same id
            throw new OrganizationWithUbrIdTakenException($organization->getUbrId());
        }
        if ($organization->getDbisId() &&
                $this->repository->existsOrganizationwithDbisId($organization->getDbisId())
        ) {
            throw new OrganizationWithDbisIdExistingException($organization->getDbisId());
        }
        $this->repository->createOrganization($organization);
        if ($icon != null && is_uploaded_file($icon['tmp_name'])) {
            $newPath = $this->repository->storeIconForOrganization($organization, $icon);
            $organization->setIconPath($newPath);
            $this->repository->updateOrganization($organization);
        }
    }

    public function updateOrganization(Organization $org, $icon = null): void
    {
        if (!$this->repository->existsOrganizationwithUbrId($org->getUbrId())) {
            throw new OrganizationWithUbrIdNotExistingException($org->getUbrId());
        }
        $this->repository->updateOrganization($org);

        if ($icon != null && is_uploaded_file($icon['tmp_name'])) {
            $newPath = $this->repository->storeIconForOrganization($org, $icon);
            $org->setIconPath($newPath);
            $this->repository->updateOrganization($org);
        }
    }

    public function deleteOrganizationByUbrId($ubrId): void
    {
        if (!$this->repository->existsOrganizationwithUbrId($ubrId)) {
            throw new OrganizationWithUbrIdNotExistingException($ubrId);
        }
        $this->repository->deleteOrganizationById($ubrId);
    }

    /**
     * @param array $options
     * @return Organization[]
     */
    public function getOrganizations(array $options = null): array
    {
        return $this->repository->getOrganizations($options);
    }

    public function getOrganizationByUbrId(string $ubrId): ?Organization
    {
        if ($ubrId == 'ALL' || $this->repository->existsOrganizationwithUbrId($ubrId)) {
            return $this->repository->getOrganizationByUbrId($ubrId);
        } else {
            // return null;
            throw new OrganizationWithUbrIdNotExistingException($ubrId);
        }       
    }

    public function getOrganizationByDbisId(string $dbisId): Organization
    {
        if ($dbisId == 'ALL' || $this->repository->existsOrganizationwithDbisId($dbisId)) {
            return $this->repository->getOrganizationByDbisId($dbisId);
        } else {
            throw new OrganizationWithDbisIdNotExistingException($dbisId);
        }
    }

    /**
     * @throws OrganizationWithDbisIdNotExistingException
     */
    public function getUbrIdForDbisId(string $dbisId): string
    {
        return $this->repository->getUbrIdForDbisId($dbisId);
    }

    public function getDbisIdForUbrId(string $ubrId): ?string
    {
        return $this->repository->getDbisIdForUbrId($ubrId);
    }

    public function getOrganizationByIp(string $ip): ?Organization
    {
        $org = $this->repository->getOrganizationByIp($ip);

        if ($org) {
            return $org;
        } else {
            throw new OrganizationWithIpNotExistingException($ip);
            //return null;
        }
    }

    /**
     *
     * @return ExternalOrganizationIdentifierNamespace[]
     */
    public function getExternalOrganizationNamespaces(): array
    {
        return $this->repository->getIdentifierNamespaces();
    }

    public function getSettings(): array
    {
        return $this->repository->getSettings();
    }

    public function getFIDs(): array
    {
        return $this->repository->getFIDs();
    }

    public function updateSettings(array $settings): void
    {
        $this->repository->updateSettings($settings);
    }

    public function addDbisViewToOrganization(Organization $organization): void
    {
        if ($organization->getDbisView() != null) {
            throw new OrganizationAlreadyContainsDbisViewException($organization->getUbrId());
        }
        $organization->setDbisView(new DbisView([]));
        $this->repository->updateOrganization($organization);
    }

    public function deleteDbisViewFromOrganization(Organization $organization): void
    {
        $organization->setDbisView(null);
        $this->repository->updateOrganization($organization);
    }
}
