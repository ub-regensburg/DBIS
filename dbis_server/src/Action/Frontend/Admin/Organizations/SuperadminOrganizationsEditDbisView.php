<?php

declare(strict_types=1);

namespace App\Action\Frontend\Admin\Organizations;

use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;

/**
 * AdminDbisViewsManagePage
 *
 * Lists existing organizations and display, whether they have a DBIS view
 */
class SuperadminOrganizationsEditDbisView extends AdminOrganizationBasePage
{
    private function sortOrganizations(
        array $organizationsAssoc,
        string $criterion,
        string $direction
    ) {
        if (!(in_array($criterion, ['name', 'city', 'ubrId', 'createdAtDate']))) {
            $criterion = "city";
        }
        if (!(in_array($direction, ['asc', 'desc']))) {
            $direction = "asc";
        }

        usort($organizationsAssoc, function ($a, $b) use ($criterion, $direction) {
            if ($a[$criterion] == $b[$criterion]) {
                return 0;
            }
            $val = ($a[$criterion] < $b[$criterion]) ? -1 : 1;
            if ($direction == "desc") {
                $val *= -1;
            }
            return $val;
        });
        return $organizationsAssoc;
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // simple auth guard
        if (!$this->authService->isSessionAuthenticated()) {
            return $response->withHeader('Location', '/admin/login');
        } elseif (!$this->isSuperAdmin) {
            return $response->withHeader('Location', '/admin');
        }

        // Set organisation according to route parameter
        if ($request->getAttribute('ubrId')) {
            parent::setAdministratedOrganization($request->getAttribute('ubrId'));
        }

        if ($request->getMethod() == "GET") {
            return $this->handleGetRequest($request, $response);
        } elseif ($request->getMethod() == "POST") {
            return $this->handleCreateRequest($request, $response);
        } elseif ($request->getMethod() == "DELETE") {
            return $this->handleDeleteRequest($request, $response);
        }
    }

    private function handleGetRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        // parse query parameters
        $ubrId = $request->getAttribute('ubrId');
        $organization = $this->orgService->getOrganizationByUbrId($ubrId)->toI18nAssocArray($this->language);

        // bind special params to template ("params" is initialized in AdminBasePage)
        $this->params['pageTitle'] = $this->resourceProvider->
                getText("tab_view", $this->language);
        $this->params['queryParams'] = $request->getQueryParams();
        $this->params['organization'] = $organization;
        $this->params['is_created_successfully'] = array_key_exists(
            "created_successfully",
            $request->getQueryParams()
        );
        $this->params['is_deleted_successfully'] = array_key_exists(
            "deleted_successfully",
            $request->getQueryParams()
        );
        $this->params['is_superadmin'] = $this->user->isSuperadmin();
        $view = Twig::fromRequest($request);
        return $view->render(
            $response,
            'admin/manage_dbis_views.twig',
            $this->params
        );
    }

    private function handleCreateRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        $ubrId = $request->getAttribute('ubrId');
        $organization =  $this->orgService->getOrganizationByUbrId($ubrId);
        $this->orgService->addDbisViewToOrganization($organization);

        // if the action is triggered from form, link back to organizations page
        if ($_SERVER['HTTP_REFERER']) {
            header("Location: ./?created_successfully=1");
            exit;
        }

        return $response;
    }

    private function handleDeleteRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        $ubrId = $request->getAttribute('ubrId');
        $body = $request->getParsedBody();
        $organization = $this->orgService->getOrganizationByUbrId($ubrId);
        $this->orgService->deleteDbisViewFromOrganization($organization);

        // if the action is triggered from form, link back to organizations page
        if ($_SERVER['HTTP_REFERER']) {
            header("Location: ./?deleted_successfully=1");
            exit;
        }

        return $response;
    }

    private function transformOrganizationsToAssoc(array $organizations, string $lang): array
    {
        $arrays = [];
        foreach ($organizations as $org) {
            array_push($arrays, $org->toI18nAssocArray($lang));
        }
        return $arrays;
    }
}
