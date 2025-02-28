<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Resources;

use App\Domain\Resources\Entities\Enterprise;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;

class GetPublishersAction extends ResourcesBaseAction
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $startTime = microtime(true);

        $contentType = $request->getHeaderLine('Content-Type');

        $q = $request->getQueryParams()['q'] ?? null;
        $ps = $request->getQueryParams()['ps'] ?? 10;
        $onlyGiven = $request->getQueryParams()['only-given'] ?? true;
        $language = $request->getQueryParams()['language'] ?? null;

        $publishers = $this->service->getEnterprises([
            "q" => $q,
            "ps" => $ps,
            "only-given" => $onlyGiven
        ]);
        $publishersAssoc = array_map(function (Enterprise $a)  {
            return $a->toAssocArray();
        }, $publishers);


        $data = array(
            'publishers' => $publishersAssoc,
            'total_nr' => count($publishersAssoc),
            'q' => $q,
            'only_given' => $onlyGiven,
            'ps' => $ps,
            'query_time' => microtime(true) - $startTime
        );
        $data = json_encode($data);

        $response->getBody()->write($data);
        return $response->withHeader('Content-Type', 'application/json');
    }
}
