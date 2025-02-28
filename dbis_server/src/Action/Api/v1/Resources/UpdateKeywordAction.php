<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Resources;

use App\Domain\Organizations\Exceptions\OrganizationWithUbrIdNotExistingException;
use App\Domain\Resources\Entities\Keyword;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;

class UpdateKeywordAction extends ResourcesBaseAction
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $params = $request->getParsedBody();

        $lang = $params['lang'];
        $keyword_id = (int) $params['id'];
        $resource_id = (int) $params['resource_id'];
        $title_de = $params['title_de'];
        $title_en = $params['title_en'];
        $external_id = $params['external_id'];
        $keyword_system = $params['keyword_system'];

        $title = array('de' => $title_de, 'en' => $title_en);
        $keyword = new Keyword($title);
        $keyword->setId($keyword_id);
        $keyword->setExternalId($external_id);
        $keyword->setKeywordSystem($keyword_system);

        $this->service->updateKeyword($keyword);

        // TODO: Return errors if they occur
        $data = array(
            'errors' => array(),
            'keyword' => $keyword->toI18nAssocArray($lang)
        );

        $data = json_encode($data);

        $response->getBody()->write($data);
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }
}
