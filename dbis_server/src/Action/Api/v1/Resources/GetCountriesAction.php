<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Resources;

use App\Domain\Resources\Entities\Country;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;

class GetCountriesAction extends ResourcesBaseAction
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

        $countries = $this->service->getCountries([
            "q" => $q
        ]);

        $countriesAssoc = array_map(function (Country $country) use ($language) {
            return $language ? $country->toI18nAssocArray($language) :  $country->toAssocArray();
        }, $countries);

        $data = array(
            'countries' => $countriesAssoc,
            'total_nr' => count($countriesAssoc),
            'q' => $q,
            //'only_given' => $onlyGiven,
            'ps' => $ps,
            'language' => $language,
            'query_time' => microtime(true) - $startTime
        );
        $data = json_encode($data);

        $response->getBody()->write($data);
        return $response->withHeader('Content-Type', 'application/json');
    }
}
