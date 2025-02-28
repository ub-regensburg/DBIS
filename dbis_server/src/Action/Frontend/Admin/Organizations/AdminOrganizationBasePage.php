<?php

declare(strict_types=1);

namespace App\Action\Frontend\Admin\Organizations;

use App\Action\Frontend\Admin\AdminBasePage;
use App\Domain\Organizations\Exceptions\OrganizationWithUbrIdNotExistingException;
use App\Infrastructure\Shared\ResourceProvider;
use App\Infrastructure\Shared\CountryProvider;
use App\Domain\Shared\AuthService;
use App\Domain\Organizations\OrganizationService;
use App\Domain\Resources\ResourceService;
use App\Domain\Organizations\Entities\ExternalOrganizationIdentifierNamespace;
use App\Domain\Organizations\Entities\Organization;

/**
 * AdminOrganizationBasePage
 *
 * Abstract class for all admin pages concerning managing or displaying
 * organizations. Injects typical dependencies used in organization actions.
 */
abstract class AdminOrganizationBasePage extends AdminBasePage
{
    /** @var OrganizationService */
    protected $orgService;

    /** @var ExternalOrganizationIdentifierNamespace[] */
    protected $externalIdentifierNamespaces;
    /** @var array */
    protected $externalIdentifierNamespacesAssoc;

    public function __construct(
        ResourceProvider $rp,
        AuthService $auth,
        OrganizationService $orgService,
        CountryProvider $countryProvider,
        ResourceService $resourceService
    ) {
        parent::__construct($rp, $auth, $orgService, $countryProvider, $resourceService);
        $this->orgService = $orgService;
        $this->externalIdentifierNamespaces = $this->orgService->getExternalOrganizationNamespaces();
    }
}