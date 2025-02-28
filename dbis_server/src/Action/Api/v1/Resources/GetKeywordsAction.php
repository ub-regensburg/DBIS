<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Resources;

use App\Domain\Resources\Entities\Keyword;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;

class GetKeywordsAction extends ResourcesBaseAction
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

        $keywords = $this->service->getKeywords([
            "q" => $q
        ]);

        /*$keywordsAssoc = array_map(function (Keyword $kw) use ($q) {
            //return $language ? $kw->toI18nAssocArray($language) :  $kw->toAssocArray();
            return $kw-> toAssocArrayWithHighlight($q);
        }, $keywords);*/

        $keywordsAssoc = array_reduce(
            $keywords,
            function ($carry, Keyword $kw) use ($q) {
                return array_merge($carry, $kw->toAssocArrayWithHighlight($q)); // Merge new results into the flat array
            },
            []
        );

        $data = array(
            'keywords' => $keywordsAssoc,
            'total_nr' => count($keywordsAssoc),
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
