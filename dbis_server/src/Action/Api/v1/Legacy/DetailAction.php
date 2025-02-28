<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Legacy;

use App\Action\Api\v1\Resources\ResourcesBaseAction;
use App\Domain\Organizations\Exceptions\OrganizationWithDbisIdNotExistingException;
use App\Domain\Organizations\Exceptions\OrganizationWithIpNotExistingException;
use App\Infrastructure\Resources\ResourceRepository;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use App\Infrastructure\Shared\XMLGenerator;

class DetailAction extends ResourcesBaseAction
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $xmloutput = $request->getQueryParams()['xmloutput'] ?? false;
        $lang = $request->getQueryParams()['lang'] ?? 'de';
        $resource_id = $request->getQueryParams()['titel_id'] ? (int) $request->getQueryParams()['titel_id'] : null;
        $ubr_id = $this->extractUbrIdFromQueryParams($request);

        // LEGACY ROUTE, part 2:
        // redirect regular queries (i.e. for non-XML) to the current way of accessing a resource entry
        if (!$xmloutput) {

            // if organization was not specified directly, try to auto-detect organization by IP
            if (!$ubr_id) {
                $ip = $_SERVER["REMOTE_ADDR"];
                if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                    $ip = $_SERVER['HTTP_CLIENT_IP'];
                } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
                }
                try {
                    $organization = $this->orgService->getOrganizationByIp($ip);
                    $ubr_id = $organization->getUbrId();
                    unset($organization);
                } catch (OrganizationWithIpNotExistingException $e) {
                    // IP not recognized
                }
            }
            // maybe we have an $ubr_id now

            $query_params = '?lang='.$lang;
            if ($ubr_id) {
                return $response->withHeader('Location', '/'.$ubr_id.'/resources/'.$resource_id.$query_params)->withStatus(301);
            } else {
                return $response->withHeader('Location', '/resources/'.$resource_id.$query_params)->withStatus(301);
            }
        } // else: xmloutput=1

        $access_mapping = null;
        if ($resource_id && $ubr_id) {
            $dbis_id = $this->orgService->getDbisIdForUbrId($ubr_id);
            $access_mapping = $this->service->getAccessMapping($dbis_id, $resource_id);
        }

        if ($resource_id) {
            $resourceGlobal = $this->service->getResourceById_NEW($resource_id, null);
            // if still no ubr_id, we return the global data
            $resourceLocal = $ubr_id != null ? $this->service->getResourceById_NEW($resource_id, $ubr_id) : null;

            if (!$resourceGlobal) {
                $response->getBody()->write('No resource with id '.$resource_id.' found.');
                return $response->withHeader('Content-Type', 'text/plain')->withStatus(404);
            }

            $organization = null;
            if ($ubr_id) {
                $organization = $this->orgService->getOrganizationByUbrId($ubr_id);
                $organization = $organization->toI18nAssocArray($lang);
            }

            $xml_generator = new XMLGenerator();

            $data = $xml_generator->generateResourceForLegacyDetailPage($resourceGlobal, $resourceLocal, $organization, $access_mapping, $lang);
    
            $response->getBody()->write($data);
            return $response->withHeader('Content-Type', 'application/xml');
        } else {
            return $response->withHeader('Content-Type', 'application/xml')->withStatus(400);
        }
    }
}
