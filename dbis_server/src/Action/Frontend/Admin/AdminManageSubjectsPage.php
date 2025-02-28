<?php

namespace App\Action\Frontend\Admin;

use App\Domain\Organizations\OrganizationService;
use App\Domain\Resources\ResourceService;
use App\Domain\Resources\Entities\Subject;
use App\Domain\Shared\AuthService;
use App\Infrastructure\Shared\CountryProvider;
use App\Infrastructure\Shared\ResourceProvider;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;

class AdminManageSubjectsPage extends AdminBasePage
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
        } elseif ($request->getMethod() == "POST") {
            return $this->handleUpdateRequest($request, $response);
        }
    }

    private function handleGetRequest($request, $response) {
        $options = array('organizationId' => $this->organization_id, 'without_resources' => true);

        $language = $this->language;
        $subjects = array_map(function (Subject $subject) use ($language) {
            return $subject->toI18nAssocArray($language);
        }, $this->resourceService->getSubjects($options));

        return $this->renderPage($request, $response, $subjects);
    }

    private function handleUpdateRequest($request, $response) {
        $options = array('organizationId' => $this->organization_id, 'without_resources' => true);

        $body = $request->getParsedBody();
        $subjectIds = $body["hide_subject"] ?? [];

        $this->resourceService->setSubjectsVisibility($subjectIds, $this->organization_id);

        $language = $this->language;
        $subjects = array_map(function (Subject $subject) use ($language) {
            return $subject->toI18nAssocArray($language);
        }, $this->resourceService->getSubjects($options));

        $updateSuccessfully = true;

        return $this->renderPage($request, $response, $subjects, $updateSuccessfully);
    }

    private function renderPage($request, $response, $subjects, $updateSuccessfully = null) {
        $this->params['subjects'] = $subjects;

        $this->params['pageTitle'] = $this->resourceProvider->getText(
            "h_subjects_manage",
            $this->authService->getAuthenticatedUser()->getLanguage()
        );

        if ($updateSuccessfully) {
            $this->params['updated_successfully'] = True;
        } else {
            $this->params['updated_successfully'] = False;
        }

        $view = Twig::fromRequest($request);

        return $view->render(
            $response,
            'admin/manage_subjects.twig',
            $this->params
        );
    }
}
