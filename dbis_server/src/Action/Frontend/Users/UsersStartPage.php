<?php

declare(strict_types=1);

namespace App\Action\Frontend\Users;

use App\Domain\Organizations\Exceptions\OrganizationWithUbrIdNotExistingException;
use App\Infrastructure\Shared\Exceptions\LanguageNotFoundException;
use App\Infrastructure\Shared\Exceptions\ResourceNotFoundException;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;
use App\Domain\Organizations\OrganizationService;
use App\Infrastructure\Shared\ResourceProvider;
use App\Infrastructure\Shared\ContextProvider;
use App\Domain\Resources\ResourceService;

/**
 * UsersSearchPage
 *
 */
class UsersStartPage extends UsersBasePage
{
    protected ResourceService $service;

    public function __construct(
        OrganizationService $os,
        ResourceProvider $rp,
        ResourceService $service,
        ContextProvider $ctx
    ) {
        parent::__construct($rp, $service, $os, $ctx);
    }

    /**
     * @throws LanguageNotFoundException
     * @throws ResourceNotFoundException
     * @throws OrganizationWithUbrIdNotExistingException
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // LEGACY: respond with redirect if query param bib_id is present
        $dbis_id = $request->getQueryParams()['bib_id'] ?? null;
        if ($dbis_id) {
            if (str_starts_with(strtoupper($dbis_id), 'ALL')) {
                $ubr_id = 'ALL';
            } else {
                $ubr_id = $this->organizationService->getUbrIdForDbisId($dbis_id);
            }
            return $response->withHeader('Location', '/'.$ubr_id.'/')->withStatus(301);
        }

        // Set organisation according to route parameter which is done after session and ip test in parent constructor
        // But first the if the orgId exists otherwise the session gets unset.
        if ($request->getAttribute('organizationId')) {
            parent::setSelectedOrganization($request->getAttribute('organizationId'));
        }

        $view = Twig::fromRequest($request);
        $this->params['pageTitle'] = $this->resourceProvider->getText("page_title_start", $this->language);

        // Needs to be done here and not in parent class, as the organizationId is at last set in this invoke function
        $this->params['doesOrganizationHasCollections'] =
            $this->getSelectedOrganizationIdFromSession() != null && $this->doesOrganizationHasCollections() == true;

        return $view->render(
            $response,
            'users/start.twig',
            $this->params
        );
    }
}
