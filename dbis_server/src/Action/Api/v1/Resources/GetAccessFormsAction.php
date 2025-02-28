<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Resources;

use App\Domain\Resources\Entities\AccessForm;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;

class GetAccessFormsAction extends ResourcesBaseAction
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $accessForms = array_map(function (AccessForm $accessForm) {
            return $accessForm->toAssocArray();
            //return [
            //    'id' => $accessForm->getId(),
            //    'title' => $accessForm->getTitle(),
            //];
        }, $this->service->getAccessForms());

        $data = json_encode($accessForms);

        $response->getBody()->write($data);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}
