<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Resources;

use App\Domain\Resources\Entities\Host;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;

class GetHostsAction extends ResourcesBaseAction
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

        $hosts = $this->service->getHosts([
            "q" => $q,
            "ps" => $ps,
            "only-given" => $onlyGiven
        ]);
        $hostsAssoc = array_map(function (Host $p) use ($language) {
            return $language ? $p->toI18nAssocArray($language) :  $p->toAssocArray();
        }, $hosts);


        $data = array(
            'hosts' => $hostsAssoc,
            'total_nr' => count($hostsAssoc),
            'q' => $q,
            'only_given' => $onlyGiven,
            'ps' => $ps,
            'language' => $language,
            'query_time' => microtime(true) - $startTime
        );
        $data = json_encode($data);

        $response->getBody()->write($data);
        return $response->withHeader('Content-Type', 'application/json');
    }
}
