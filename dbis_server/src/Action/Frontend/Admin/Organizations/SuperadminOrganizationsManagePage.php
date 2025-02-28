<?php

declare(strict_types=1);

namespace App\Action\Frontend\Admin\Organizations;

use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;

/**
 * AdminManageOrganisationsPage
 *
 * Lists existing organisations in DBIS.
 * Allows for editing, deleting and creating new organisations.
 */
class SuperadminOrganizationsManagePage extends AdminOrganizationBasePage
{
    private function sortOrganizations(
        array $organizationsAssoc,
        string $criterion,
        string $direction
    ) {
        if (!(in_array($criterion, ['name', 'city', 'ubrId', 'createdAtDate', 'dbisView']))) {
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

        // parse query parameters
        $q = isset($request->getQueryParams()['q']) ?
                $request->getQueryParams()['q'] : null;

        $sortby = isset($request->getQueryParams()['sortby']) ?
                $request->getQueryParams()['sortby'] : "city";
        $sortdirection = isset($request->getQueryParams()['sortdirection']) ?
                $request->getQueryParams()['sortdirection'] : "asc";
        //


        $view = Twig::fromRequest($request);

        $organizationsAssoc = $this->transformOrganizationsToAssoc(
            $this->orgService->getOrganizations([
                    'q' => $q
                ]),
            $this->language
        );

        $organizationsAssoc = $this->sortOrganizations(
            $organizationsAssoc,
            $sortby,
            $sortdirection
        );


        // bind special params to template ("params" is initialized in AdminBasePage)
        $this->params['pageTitle'] = $this->resourceProvider->
                getText("page_title_manage_organizations", $this->language);
        $this->params['organizations'] = $organizationsAssoc;
        $this->params['is_deleted_successfully'] = array_key_exists(
            "deleted_successfully",
            $request->getQueryParams()
        );
        $this->params['queryParams'] = [
                    'q' => $q,
                    'sortby' => $sortby,
                    'sortdirection' => $sortdirection
                ];
        return $view->render(
            $response,
            'admin/manage_organizations.twig',
            $this->params
        );
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
