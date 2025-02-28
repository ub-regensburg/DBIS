<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Resources;

use App\Domain\Organizations\Exceptions\OrganizationWithDbisIdNotExistingException;
use App\Domain\Resources\ResourceService;
use App\Domain\Shared\AuthService;
use App\Domain\Organizations\OrganizationService;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;

/**
 * ResourceBaseAction
 *
 * Parent class for all resource domain actions. Inject domain level
 * dependencies, e.g. PDOs, Repositories, Libraries... that are needed all
 * over the domain.
 */
abstract class ResourcesBaseAction
{
    /** @var ResourceService */
    protected $service;
    /** @var AuthService */
    protected $authService;

    /** @var OrganizationService */
    protected $orgService;

    public function __construct(
        ResourceService $service,
        AuthService $authService,
        OrganizationService $orgService
    ) {
        $this->service = $service;
        $this->authService = $authService;
        $this->orgService = $orgService;
    }

    protected function extractUbrIdFromQueryParams(ServerRequestInterface $request): ?string
    {
        $query_params = $request->getQueryParams();
        $dbis_id = null;
        if (isset($query_params['bib_id'])) {
            $dbis_id = $query_params['bib_id'];
        } else if (isset($query_params['url_bibid'])) {
            $dbis_id = $query_params['url_bibid'];
        }
        if (!$dbis_id) {
            return null;
        }
        if (str_starts_with(strtoupper($dbis_id), 'ALL')) {
            return null;
        }
        try {
            return $this->orgService->getUbrIdForDbisId($dbis_id);
        } catch (OrganizationWithDbisIdNotExistingException $e) {
            return null;
        }
    }
}
