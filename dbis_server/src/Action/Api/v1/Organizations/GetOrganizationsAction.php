<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Organizations;

use App\Domain\Organizations\Entities\Organization;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;

class GetOrganizationsAction extends OrganizationsBaseAction
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $language = $query_params['language'] ?? "de";

        $organizations = array_map(function (Organization $o) use ($language) {
            return $o->toI18nAssocArray($language);
        }, $this->service->getOrganizations());

        $data = array(
            'organizations' => $organizations,
        );

        $data = json_encode($data);
        $response->getBody()->write($data);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}
