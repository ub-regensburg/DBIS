<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Resources;

use App\Infrastructure\Resources\ResourceRepository;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use App\Infrastructure\Shared\XMLGenerator;

class GetLicenseAction extends ResourcesBaseAction
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $contentType = $request->getHeaderLine('Content-Type') ?? 'application/json';
        
        $format = $request->getQueryParams()['format'] ?? null;
        $lang = $request->getQueryParams()['lang'] ?? 'de';
        $organization_id = $request->getQueryParams()['organizationId'] ?? null;
        $licenseId = $request->getAttribute("licenseId") ? (int) $request->getAttribute("licenseId") : null;

        if ($licenseId) {
            $license  = null;

            if (is_null($organization_id)) {
                
            } else {
                
            }

            if (str_contains($contentType, 'application/json') || $format == "json") {   
                $data = json_encode($license);
    
                $response->getBody()->write($data);
                return $response->withHeader('Content-Type', 'application/json');
            }
        } else {
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }
}
