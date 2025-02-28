<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

namespace App\Action\Frontend\Admin;

use App\Domain\Shared\Exceptions\AuthenticationFailedException;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;
use App\Infrastructure\Shared\ResourceProvider;
use App\Domain\Shared\AuthService;
use App\Action\Frontend\Admin\AdminBasePage;

/**
 * Description of AdminLoginPage
 *
 */
class AdminLoginPage extends AdminBasePage
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        // simple auth guard
        if ($this->authService->isSessionAuthenticated()) {
            $user = $_SESSION['user'];
            $privileges = $user->getPrivileges();
            if (count($privileges) == 1) {
                $organizationId = $privileges[0]->getOrganizationId();
            }

            if (isset($organizationId) && $organizationId) {
                return $response->withHeader('Location', '/admin/manage/' . $organizationId . "/")->withStatus(302);
            } else {
                return $response->withHeader('Location', '/admin')->withStatus(302);
            }
        }

        if ($request->getMethod() == "GET") {
            return $this->handleGetRequest($request, $response);
        } elseif ($request->getMethod() == "POST") {
            return $this->handleLoginRequest($request, $response);
        }
    }

    private function handleGetRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        // phpinfo(INFO_VARIABLES);

        $params = $request->getQueryParams();
        $login = $params['login'] ?? null;
        $redirectTo = $params['redirect_to'] ?? null;
        $this->params['lang'] = $params['lang'] ?? "de";
        $this->params['i18n'] = $this->resourceProvider->getAssocArrayForLanguage($this->params['lang']);
        $this->params['isHidingNav'] = 'true';
        $this->params['login'] = $login;
        $this->params['redirect_to'] = $redirectTo;
        $this->params['pageTitle'] = 'DBIS - Login';
        $this->params['is_login_failed'] = array_key_exists(
            "loginFailed",
            $request->getQueryParams()
        );

        $view = Twig::fromRequest($request);
        return $view->render(
            $response,
            'admin/login.twig',
            $this->params
        );
    }

    private function handleLoginRequest(
        ServerRequestInterface $request,
        ResponseInterface $response
    ) {
        $body = $request->getParsedBody();
        $login = $body['login'];
        $password = $body['password'];
        
        if (strlen($password) < 1) {
            header("Location: /admin/login?loginFailed=1&login={$login}&lang={$this->language}");
            exit();
        }

        try {
            /*
            * $authResult should be true, if login was successfull.
            */
            $authResult = $this->authService->login($login, $password);

            $queryParams = $request->getQueryParams();

            $user = $_SESSION['user'];
            $privileges = $user->getPrivileges();
            if (count($privileges) == 1) {
                $organizationId = $privileges[0]->getOrganizationId();
            }
            if ($authResult) {
                if (array_key_exists('redirect_to', $_SESSION) && $_SESSION['redirect_to']) {
                    $redirect_to = $_SESSION['redirect_to'];
                    unset($_SESSION['redirect_to']);
                    header("Location: $redirect_to");
                } elseif (isset($queryParams['redirect_to']) && strlen($queryParams['redirect_to'] > 0)) {
                    $redirect_to = $queryParams['redirect_to'];
                    header("Location: $redirect_to");
                    exit();
                } else {
                    if (isset($organizationId) && $organizationId) {
                        header("Location: /admin/manage/" . $organizationId . "/");
                    } else {
                        header("Location: /admin");
                    }
                }
            } else {
                header("Location: /admin/login?loginFailed=1&login={$login}&lang={$this->language}");
            }
            
        } catch (AuthenticationFailedException $e) {
            header("Location: /admin/login?loginFailed=1&login={$login}&lang={$this->language}");
        }

        exit();
    }
}
