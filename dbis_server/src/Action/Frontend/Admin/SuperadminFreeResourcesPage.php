<?php

namespace App\Action\Frontend\Admin;

use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;
use App\Domain\Organizations\Entities\Organization;
use App\Domain\Organizations\OrganizationService;
use App\Domain\Resources\ResourceService;
use App\Domain\Resources\Entities\Subject;
use App\Domain\Shared\AuthService;
use App\Infrastructure\Shared\CountryProvider;
use App\Infrastructure\Shared\Exceptions\LanguageNotFoundException;
use App\Infrastructure\Shared\Exceptions\ResourceNotFoundException;
use App\Infrastructure\Shared\ResourceProvider;

class SuperadminFreeResourcesPage extends AdminBasePage
{
    private OrganizationService $organizationProvider;

    protected ResourceService $resourceService;

    public function __construct(
        ResourceProvider $rp,
        AuthService $auth,
        OrganizationService $organizationService,
        CountryProvider $countryProvider,
        ResourceService $resourceService
    ) {
        parent::__construct($rp, $auth, $organizationService, $countryProvider, $resourceService);
        $this->organizationProvider = $organizationService;
        $this->resourceService = $resourceService;
    }

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // simple auth guard
        if (!$this->authService->isSessionAuthenticated()) {
            return $response->withHeader('Location', '/admin/login')->withStatus(302);
        } elseif (!$this->authService->getAuthenticatedUser()->isSuperadmin()) {
            // IMPORTANT! Only authenticated super admins are allowed to see this page!
            return $response->withHeader('Location', '/admin')->withStatus(302);
        }

        if ($request->getMethod() == "GET") {
            return $this->handleGetRequest($request, $response);
        } elseif ($request->getMethod() == "POST" || $request->getMethod() == "PUT") {
            return $this->handlePostRequest($request, $response);
        } else {
            return $this->handleGetRequest($request, $response);
        }
    }

    private function handlePostRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        $body = $request->getParsedBody();

        $ubrId = $body['ubr-id'];

        $selectedSubjectIds = $body["subjects"] ?? [];

        $subjects = $this->resourceService->getSubjects(['sort_language' => $language,
        'include_collections' => false, 'without_resources' => true]);

        foreach($subjects as $subject) {
            $subjectId = $subject->getId();

            $resources = $this->resourceService->getResourcesForSubject([
                "for_subject" => $subjectId
            ], null);

            foreach($resources as $resource) {
                foreach($resource->getLicenses() as $license) {

                    if ($license->getType()->getId() === 1) {
                        $licenseId = $license->getId();
                        // Remove all global licenses
                        $this->resourceService->removeLicenseFromResource($license, $ubrId, "onlyMyInstitution");

                        foreach ($selectedSubjectIds as $selectedSubjectId) {
                            $selectedSubjectId = (int) $selectedSubjectId;
            
                            if ($subjectId === $selectedSubjectId) {
                                // Create new licenses for resources of selected subjects
                                $this->resourceService->persistLicenseForOrganization($licenseId, $ubrId);
                            }
                        }
                    }
                }
            }
        }

        $url = explode('?', $_SERVER['REQUEST_URI'])[0];
        header("Location: {$url}?updated_successfully=1", true, 303);
        exit();
    }

    /**
     * @throws ResourceNotFoundException
     * @throws LanguageNotFoundException
     */
    private function handleGetRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {

        $language = $this->authService->getAuthenticatedUser()->getLanguage();

        $organizations = array_map(function (Organization $org) use ($language) {
            return $org->toI18nAssocArray($language);
        }, $this->organizationProvider->getOrganizations());
        $this->params['organizations'] = $organizations;

        $language = $this->language;
        $subjects = array_map(function (Subject $subject) use ($language) {
            return $subject->toI18nAssocArray($language);
        }, $this->resourceService->getSubjects(['sort_language' => $language,
        'include_collections' => false, 'without_resources' => true]));

        $this->params['subjects'] = $subjects;

        $this->params['is_updated_successfully'] = array_key_exists(
            "updated_successfully",
            $request->getQueryParams()
        );

        $this->params['pageTitle'] = "DBIS - " . $this->resourceProvider->getText(
            "h_super_free_resources",
            $language
        );
        $view = Twig::fromRequest($request);
        return $view->render(
            $response,
            'admin/manage_free_resources.twig',
            $this->params
        );
    }
}
