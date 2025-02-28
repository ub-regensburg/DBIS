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

class AdminRedirectToEditPage extends AdminBasePage
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
        $queryParams = $request->getQueryParams();
        $resourceId = array_key_exists('resource', $queryParams) ? (int)$queryParams['resource']: null;
        $ubrId = array_key_exists('ubrid', $queryParams) ? str_replace(' ', '', $queryParams['ubrid']): null;

        // Set organisation according to route parameter
        /*
        if ($request->getAttribute('ubrId')) {
            parent::setAdministratedOrganization($request->getAttribute('ubrId'));
        }
        */

        // simple auth guard
        if (!$this->authService->isSessionAuthenticated()) {
            $_SESSION['redirect_to'] = '/admin/redirect_to_edit?resource=' . $resourceId . '&ubrid=' . $ubrId;
            header('Location: /admin/login?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
            exit();
        } elseif (!$this->isSuperAdmin && !$this->isAdmin) {           
            header('Location: /admin');
            exit();
        }

        // Redirect to the admin edit page
        if ($resourceId) {
            if ($this->organization_id) {
                header('Location: /admin/manage/' . $this->organization_id . '/resources/' . $resourceId . '/');
                exit();
            } else {
                // What to do if no organisation could be found?
                if (count($this->administrableOrganizations) > 0) {
                    $selectedUbrId = $this->administrableOrganizations[0]->getUbrId();
                    if ($ubrId) {
                        foreach($this->administrableOrganizations as $administrableOrganizations) {
                            if ($ubrId == $administrableOrganizations->getUbrId()) {
                                $selectedUbrId = $ubrId;
                                break;
                            }
                        }
                    }
                    
                    header('Location: /admin/manage/' . $selectedUbrId . '/resources/' . $resourceId . '/');
                    exit();
                }
            }
        }
        
        // Handle invalid resource id or no organsiation was selected
        // return $response->withHeader('Location', '/admin');
        header('Location: /admin');
        exit();
    }
}
