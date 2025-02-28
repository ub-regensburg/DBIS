<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Organizations;

use App\Domain\Organizations\OrganizationService;
use App\Domain\Shared\AuthService;
use App\Domain\Organizations\Entities\Organization;
use App\Domain\Organizations\Entities\ExternalOrganizationIdentifierNamespace;
use App\Domain\Organizations\Entities\ExternalOrganizationIdentifier;
use App\Infrastructure\Shared\Util;
use App\Domain\Shared\Exceptions\InvalidFiletypeException;

/**
 * OrganizationsBaseAction
 *
 * Base class for organizations API actions
 *
 */
class OrganizationsBaseAction
{
    /** @var OrganizationService */
    protected OrganizationService $service;

    /** @var AuthService */
    protected AuthService $authService;

    public function __construct(
        OrganizationService $service,
        AuthService $authService
    ) {
        $this->service = $service;
        $this->authService = $authService;
    }
}
