<?php

namespace App\Action\Frontend\Users;

use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use Slim\Views\Twig;


class UsersWarpTo extends UsersBasePage
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $params = $request->getQueryParams();

        $resourceId = array_key_exists('titel_id', $request->getQueryParams()) && $request->getQueryParams()['titel_id'] ? (int) $request->getQueryParams()['titel_id'] : $params['resource_id'];
        $dbisId = $request->getQueryParams()['bib_id'] ?? null;
        $ubrId = $dbisId ? $this->organizationService->getUbrIdForDbisId($dbisId) : $params['ubr_id'];

        $accessId = array_key_exists('access_id', $params) && strlen($params['access_id'] > 0) ? (int)$params['access_id']: null;
        $licenseType = array_key_exists('license_type', $params) && strlen($params['license_type'] > 0) ? (int)$params['license_type']: null;

        if (is_null($accessId)) {
            $color = array_key_exists('color', $params) && strlen($params['color'] > 0) ? (int)$params['color']: null;
            
            if (is_null($licenseType) && $color) {
                $licenseInformation = $this->mapFromColors($color);
                $licenseType = $licenseInformation['licenseType'];
            }

            $accessId = $this->service->getAccessId($ubrId, $resourceId, $licenseType);
        }

        $licenseForm = array_key_exists('license_form', $params) && strlen($params['license_form'] > 0) ? (int)$params['license_form']: null;
        $accessType = array_key_exists('access_type', $params) && strlen($params['access_type'] > 0) ? (int)$params['access_type']: null;
        $accessForm = array_key_exists('access_form', $params) && strlen($params['access_form'] > 0) ? (int)$params['access_form']: null;

        // $safe = $this->service->isUrlContainedInDbis($urlDecoded);
        $urlFromDatabase = $this->service->isUrlSafe($accessId);
        
        if (!$urlFromDatabase) {
            $view = Twig::fromRequest($request);
            return $view->render(
                $response,
                'users/unsafe_link.twig',
                $this->params
            );
        }

        $colors = $this->mapToColors($licenseType, $licenseForm, $accessType, $accessForm);
        $color = $colors['color'];
        $ocolor = $colors['ocolor'];

        $success = $this->saveAccess($ubrId, $resourceId, $licenseType, $licenseForm, $accessType, $accessForm);

        if ($success) {
            $url = preg_replace('/%5C%22+target%3D%5C%22_top%5C%22$/', '', $urlFromDatabase);
            if ($ubrId !== 'ALL') {
                $dbisId = $this->organizationService->getDbisIdForUbrId($ubrId);
                if ($dbisId) {
                    if ($dbisId == "thzw" && $color > 1) {
                        $url = "https://login.ezproxy.hlb-wuppertal.de/login?url=".$url; // THZWs proxy only handles not-encoded urls
                    }
                    if ($dbisId == "ubol" && $color > 1)
                        {
                            $url = "http://e-res.bis.uni-oldenburg.de/redirect.php?url=".urlencode($url);
                        }
                    if ($dbisId == "bag" && $color > 1)
                        {
                            $url = "http://dssax.idm.oclc.org/login?qurl=" . urlencode($url);
                        }
                    if ($dbisId == "fhwn" && $color > 1)
                        {
                            $url = "https://wn.idm.oclc.org/login?url=" . urlencode($url);
                        }
                    if ($dbisId == "ubfm" && ($color > 1 || $ocolor >= 8))
                        {
                            $url = 'http://proxy.ub.uni-frankfurt.de/login?url='.$url;
                        }
                    if ($dbisId == "ubhe" && ($color > 1 || $ocolor == 32) && strpos($url,'http://www.ub.uni-heidelberg.de/')!==0)
                        {
                            $url = 'http://www.ub.uni-heidelberg.de/cgi-bin/edok?dok='.urlencode($url);
                        }
                    if ($dbisId == "ub_m" && (($color > 1 && $color != 3) || $ocolor == 32) && strpos($url,'http://emedia1.bib-bvb.de/NetManBin/') !== 0 && strpos($url,'proxy.nationallizenzen.de') !== 0) {
                        $url = "http://emedien.ub.uni-muenchen.de/login?url=$url";
                    }
                    if ($_SERVER["REMOTE_ADDR"] =='932.199.145.113') {
                        if ($dbisId == "ub_m" && (($color > 1 && $color != 3) || $ocolor == 32) && strpos($url,'http://emedia1.bib-bvb.de/NetManBin/') !== 0 && strpos($url,'proxy.nationallizenzen.de') !== 0) {
                            $url = "http://emedien.ub.uni-muenchen.de/login?url=$url";
                        }
                    }
                }
            }
            
            return $response->withHeader('Location', $url)->withStatus(302);
        } else {
            // TODO: Could not save access
        }
    }

    private function mapToColors($licenseType, $licenseForm, $accessType, $accessForm) {
        $color = 0;
        $ocolor = 0;

        if ($licenseType == 1) {
            $color = 1;
        }

        if ($licenseType == 2) {
            $color = 2;  // or 4
        }

        if ($licenseType == 3) {
            $ocolor = 32;  // or 4
        }

        if ($licenseType == 4) {
            $color = 8;  // or 4
        }

        $colors = array('color' => $color, 'ocolor' => $ocolor);

        return $colors;
    }

    public static function mapFromColors($color): array
    {
        $licenseForm = null;
        $licenseType = null;
        $accessType = null;
        $accessForm = null;

        if ($color == 0 || $color == 1 || $color == -1 || $color == 7 || $color == 9 || $color == 41 || $color == 42 || $color == 48 || $color == 85) {
            $licenseType = 1;
        }

        if ($color == 2 || $color == 4 || $color == 5 || $color == -2 || $color == -128 || $color == 6 || $color == 22 || $color == 3 || $color == 32 || $color == 35 || $color == 37 || $color == 64 || $color == 109 || $color == 127) {
            $licenseType = 2;
            if ($color == 2) {
                $accessType = 3;
                $accessForm = 31;
            }
        }

        if ($color == 8) {
            $licenseType = 4;
        }

        if ($color == 16) {
            $licenseType = 6;
        }

        if ($color == 56 || $color == 70) {
            $licenseType = 3;
        }

        return array('licenseType' => $licenseType, 'licenseForm' => $licenseForm, 'accessType' => $accessType, 'accessForm' => $accessForm);
    }

    private function saveAccess($ubrId, $resourceId, $licenseType, $licenseForm, $accessType, $accessForm) {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        }

        $anonymizedIpAddress = AddSlashes(preg_replace(["/\.\d*$/i", "/[\da-f]*:[\da-f]*$/i"], [".XXX", "XXXX:XXXX"], $ipAddress ));

        $this->service->saveAccess($ubrId, $resourceId, $anonymizedIpAddress, $licenseType, $licenseForm, $accessType, $accessForm);

        return true;
    }

    public static function buildUrl(?string $url, int $resourceId, $accessId, string $ubrId = null, int $licenseType = null, int $licenseForm = null, int $accessType = null, int $accessForm = null): string
    {
        if (!$url) {
            return '';
        }

        $result = 'warpto?ubr_id='.($ubrId?:'')
            .'&resource_id='.$resourceId
            .'&access_id='.$accessId;
        if ($licenseType) {
            $result .= '&license_type='.$licenseType;
        }
        if ($licenseForm) {
            $result .= '&license_form='.$licenseForm;
        }
        if ($accessType) {
            $result .= '&access_type='.$accessType;
        }
        if ($accessForm) {
            $result .= '&access_form='.$accessForm;
        }
        $result .= '&url='.urlencode($url);
        return $result;
    }
}
