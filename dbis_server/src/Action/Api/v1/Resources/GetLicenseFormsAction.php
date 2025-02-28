<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Resources;

use App\Domain\Resources\Entities\LicenseForm;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;

class GetLicenseFormsAction extends ResourcesBaseAction
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $licenseForms = array_map(function (LicenseForm $licenseForm) {
            return $licenseForm->toAssocArray();
            //return [
            //    'id' => $licenseForm->getId(),
            //    'title' => $licenseForm->getTitle(),
            //    'description' => $licenseForm->getDescription(),
            //];
        }, $this->service->getLicenseForms());

        $data = json_encode($licenseForms);

        $response->getBody()->write($data);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}
