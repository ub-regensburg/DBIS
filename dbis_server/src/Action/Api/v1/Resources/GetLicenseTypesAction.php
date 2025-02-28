<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Resources;

use App\Domain\Resources\Entities\LicenseType;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;

class GetLicenseTypesAction extends ResourcesBaseAction
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $licenseTypes = array_map(function (LicenseType $licenseType) {
            return $licenseType->toAssocArray();
            //return [
            //    'id' => $licenseType->getId(),
            //    'title' => $licenseType->getTitle(),
            //    'description' => $licenseType->getDescription(),
            //    'isGlobal' => $licenseType->isGlobal(),
            //];
        }, $this->service->getLicenseTypes());

        $data = json_encode($licenseTypes);

        $response->getBody()->write($data);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}
