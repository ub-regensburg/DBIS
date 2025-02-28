<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Resources;

use App\Domain\Resources\Entities\Author;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;

class GetAuthorsAction extends ResourcesBaseAction
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

        $authors = $this->service->getAuthors([
            "q" => $q,
            "ps" => $ps,
            "only-given" => $onlyGiven
        ]);
        $authorsAssoc = array_map(function (Author $a) use ($language) {
            return $language ? $a->toI18nAssocArray($language) :  $a->toAssocArray();
        }, $authors);


        $data = array(
            'authors' => $authorsAssoc,
            'total_nr' => count($authorsAssoc),
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
