<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Resources;

use App\Domain\Resources\Entities\AccessType;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;

class GetAccessTypesAction extends ResourcesBaseAction
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $accessTypes = array_map(function (AccessType $accessType) {
            return $accessType->toAssocArray();
            //return [
            //    'id' => $accessType->getId(),
            //    'title' => $accessType->getTitle(),
            //    'description' => $accessType->getDescription(),
            //    'isGlobal' => $accessType->isGlobal(),
            //];
        }, $this->service->getAccessTypes());

        $data = json_encode($accessTypes);

        $response->getBody()->write($data);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}
