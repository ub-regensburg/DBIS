<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Resources;

use App\Infrastructure\Resources\ResourceRepository;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;

class GetLabelsAction extends ResourcesBaseAction
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $contentType = 'application/json';
        $ubrId = $request->getQueryParams()['organizationId'] ?? null;

        if ($ubrId) {
            $labels = $this->service->getLabels($ubrId);

            if (str_contains($contentType, 'application/json')) {   
                $data = json_encode($labels);
    
                $response->getBody()->write($data);
                return $response->withHeader('Content-Type', 'application/json');
            } 
        } else {
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    }
}
