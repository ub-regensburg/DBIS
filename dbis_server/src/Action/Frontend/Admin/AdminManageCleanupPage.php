<?php

namespace App\Action\Frontend\Admin;

use App\Domain\Organizations\OrganizationService;
use App\Domain\Resources\Entities\Access;
use App\Domain\Resources\ResourceService;
use App\Domain\Shared\AuthService;
use App\Infrastructure\Shared\CountryProvider;
use App\Infrastructure\Shared\ResourceProvider;
use App\Domain\Resources\Entities\InvalidUrls;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;

class AdminManageCleanupPage extends AdminBasePage
{
    protected ResourceService $resourceService;

    // TODO: OrganizationService should not be in the base class
    public function __construct(
        ResourceProvider $rp,
        AuthService $auth,
        OrganizationService $service,
        CountryProvider $countryProvider,
        ResourceService $resourceService
    ) {
        parent::__construct($rp, $auth, $service, $countryProvider, $resourceService);
        $this->resourceService = $resourceService;
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // Set organisation according to route parameter
        if ($request->getAttribute('ubrId')) {
            parent::setAdministratedOrganization($request->getAttribute('ubrId'));
        }

        // simple auth guard
        if (!$this->authService->isSessionAuthenticated()) {
            return $response->withHeader('Location', '/admin/login');
        } elseif (!$this->isSuperAdmin && !$this->isAdmin) {
            return $response->withHeader('Location', '/admin');
        }

        if ($request->getMethod() == "GET") {
            return $this->handleGetRequest($request, $response);
        } elseif ($request->getMethod() == "POST" || $request->getMethod() == "PUT") {
            return $this->handleUpdateRequest($request, $response);
        }

        // simple auth guard
        if (!$this->authService->isSessionAuthenticated()) {
            return $response->withHeader('Location', '/admin/login');
        } elseif (!$this->isSuperAdmin && !$this->isAdmin && !$this->isSubjectSpecialist) {
            return $response->withHeader('Location', '/admin');
        }        
    }

    private function handleUpdateRequest(ServerRequestInterface $request, ResponseInterface $response)
    {
        $organization_id = $request->getAttribute('ubrId');

        $parsed_body = $request->getParsedBody();

        $statesOkay = $parsed_body['state-okay'] ?? [];

        foreach($statesOkay as $idx => &$stateOkay) {
            $accesId = (int) $stateOkay;

            $newState = "okay";
            $this->resourceService->setAccessesUrlState($accesId, $newState);
        }

        /*
        $url = "/admin/manage/{$organization_id}/collections/";
        header("Location: {$url}?updated_successfully=1", true, 303);
        exit();
        */

        $updateSuccessfully = true;

        return $this->renderPage($request, $response, $updateSuccessfully);
    }

    private function handleGetRequest(ServerRequestInterface $request, ResponseInterface $response)
    {
        return $this->renderPage($request, $response);
    }

    private function renderPage(
        ServerRequestInterface $request,
        ResponseInterface $response,
        $updateSuccessfully = null
    ) {
        $view = Twig::fromRequest($request);
        $organization_id = $request->getAttribute('ubrId');

        $p = isset($queryParams['p']) ?
            $request->getQueryParams()['p'] : 1;

        $pagination_size =
            isset($queryParams['ps']) ?
                // This handles strings as well, which return 0 after abs(int()[STRING])
                (abs((int)$queryParams['ps']) == 0 ?
                    25 :
                    abs((int)$queryParams['ps'])) :
                25;

        $from = (int) $pagination_size * ($p - 1);
        $size = (int) $pagination_size;

        $this->params['pageTitle'] = $this->resourceProvider->getText(
            "h_clean_up",
            $this->authService->getAuthenticatedUser()->getLanguage()
        );

        $language = $this->language;

        if ($updateSuccessfully) {
            $this->params['updated_successfully'] = True;
        } else {
            $this->params['updated_successfully'] = False;
        }
        
        $this->params['accesses_with_invalid_urls'] = array_map(function (Access $access) use ($language) {
            return $access->toI18nAssocArray($language);
        }, $this->resourceService->getAccessesWithInvalidUrls($organization_id));
        $this->params['organizationId'] = $organization_id;

        $view = Twig::fromRequest($request);

        return $view->render(
            $response,
            'admin/cleanup.twig',
            $this->params
        );
    }
}
