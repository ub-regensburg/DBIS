<?php

namespace App\Action\Frontend\Admin;

use App\Domain\Organizations\OrganizationService;
use App\Domain\Resources\ResourceService;
use App\Domain\Shared\AuthService;
use App\Infrastructure\Shared\CountryProvider;
use App\Infrastructure\Shared\ResourceProvider;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;

class AdminDailyStatisticsPage extends AdminBasePage
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
        if ($request->getAttribute('organizationId')) {
            parent::setAdministratedOrganization($request->getAttribute('organizationId'));
        }

        // simple auth guard
        if (!$this->authService->isSessionAuthenticated()) {
            return $response->withHeader('Location', '/admin/login');
        } elseif (!$this->isSuperAdmin && !$this->isAdmin && !$this->isSubjectSpecialist) {
            return $response->withHeader('Location', '/admin');
        }

        $this->params['pageTitle'] = $this->resourceProvider->getText(
            "h_daily_statistics",
            $this->authService->getAuthenticatedUser()->getLanguage()
        );

        $organization_id = $request->getAttribute('organizationId');
        // TODO: $this->organization_id doesn't work ...

        $params = $request->getQueryParams();

        $days = array_key_exists('days', $params) ? (int)$params['days']: null;

        $statisticsAll = array();
        $statisticsOrg = array();

        if ($days) {
            if ($days > 99) {
                $days = 99;
            }
            if ($days < 1) {
                $days = 1;
            }

            $statisticsAll = $this->service->getDailyStatistics($days);
            $statisticsOrg = $this->service->getDailyStatistics($days, $organization_id);
        }

        $this->params['statistics_all'] = $statisticsAll;
        $this->params['statistics_org'] = $statisticsOrg;
        $this->params['days'] = $days;
        $this->params['ubrId'] = $organization_id;

        $view = Twig::fromRequest($request);

        return $view->render(
            $response,
            'admin/statistics/daily.twig',
            $this->params
        );
    }
}
