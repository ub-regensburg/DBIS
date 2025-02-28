<?php

declare(strict_types=1);

namespace App\Action\Api\v1\Legacy;

use App\Action\Api\v1\Resources\ResourcesBaseAction;
use App\Action\Frontend\Users\UsersWarpTo;
use App\Domain\Organizations\Exceptions\OrganizationWithDbisIdNotExistingException;
use App\Domain\Resources\Entities\Subject;
use App\Domain\Resources\Exceptions\CollectionNotFoundException;
use App\Infrastructure\Shared\SearchClient;
use Exception;
use DateTime;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as ServerRequestInterface;
use App\Infrastructure\Shared\XMLGenerator;

class DbListeAction2 extends ResourcesBaseAction
{
    const DEFAULT_PAGINATION_SIZE = 10000;

    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        $query_params = $request->getQueryParams();

        $dbis_id = $request->getQueryParams()['bib_id'] ?? null;
        if ($dbis_id && (str_starts_with(strtoupper($dbis_id), 'ALL'))) {
            $dbis_id = null;
        }
        if ($dbis_id) {
            try {
                $ubr_id = $this->orgService->getUbrIdForDbisId($dbis_id);
                // getUbrIdForDbisId is very "tolerant", so we need to normalize $dbis_id:
                $dbis_id = $this->orgService->getDbisIdForUbrId($ubr_id);
            } catch (OrganizationWithDbisIdNotExistingException $e) {
                $response->getBody()->write('No organization with bib_id ' . $dbis_id . ' found.');
                return $response->withHeader('Content-Type', 'text/plain')->withStatus(404);
            }
        } else {
            $ubr_id = null;
        }

        $lang = $query_params['lang'] ?? 'de';
$all = array_key_exists('all', $query_params) ? filter_var($query_params['all'], FILTER_VALIDATE_BOOLEAN) : false;
        $q = '';
        $search_client = new SearchClient($ubr_id, $lang);

        $legacy_query_params = Array();

        $nfnum = 0;
        if (isset($query_params['q'])){
            $q = $query_params['q'];
            $search_client->freeSearch($q);
        }
        else if (isset($query_params['Suchwort'])){

            $q = $query_params['Suchwort'];
            $search_client->freeSearch($q);

        } else {
        for ($fnum=1; isset($query_params['jq_term'.$fnum]); $fnum++) {

            $cur_term = $query_params['jq_term'.$fnum]; // aktuelles Suchfeld (Textfeld)  
            $legacy_query_params['jq_term'.$fnum] = $cur_term;
            
            $cur_type = $query_params['jq_type'.$fnum];
            $legacy_query_params['jq_type'.$fnum] = $cur_type;

            if (isset($query_params['jq_bool'.$fnum])){
                $cur_bool = $query_params['jq_bool'.($fnum)];
                $legacy_query_params['jq_bool'.$fnum] = $cur_bool;
            }

            if (isset($query_params['jq_not'.$fnum])){
                $cur_not = $query_params['jq_not'.($fnum)];
                $legacy_query_params['jq_not'.$fnum] = $cur_not;
            }


            $bool = 'should';
          
            if ($cur_term!='') {          // es wurde etwas in aktuelles Suchfeld geschrieben

              $nfnum++;

              if ($nfnum>1) {            // es gab schon ein belegtes Feld
                $cur_bool = $query_params['jq_bool'.($fnum)];  // Variablenname des aktuellen booleschen operators

                $cur_not  = $query_params['jq_not'.($fnum)];   // Variablenname des aktuellen Operators (ja/nein) 
                if ($cur_bool=='AND' && $cur_not == 1) {
                    $bool = 'must';
                }

                if ($cur_bool=='AND' && $cur_not == 'NOT') {
                    $bool = 'must_not';
                }
                
                if ($cur_bool=='OR' && $cur_not == '1') {
                    $bool = 'should';
                }                        

              }
              //$cur_type = $query_params['jq_type'.$fnum]; // aktuelle Klappbox mit dem Feldnamen (welches Suchfeld Titel, Titelanfang, eingabedatum)
              
            } else {
                $bool = 'must';
            }

            /*
            "AL" => "Suche über alle Felder",
            "KT" => "Titelwort(e)",
            "CO" => "Beschreibung",
            "KW" => "Schlagwort",
            "KS" => "Titelanfang",
            "PU" => "Verlag",
            "LD" => "Eingabedatum"*/

            switch ($cur_type) {

                case "AL": 
                    if ($cur_term !== null && $cur_term !== ''){
                        $search_client->freeSearch($cur_term);
                    } else {
                        $search_client->matchAll();
                    }
                    break;
                
                case "KT":
                    if ($cur_term !== null && $cur_term !== '') {
                        $search_client->addTitle($cur_term, $bool);
                    }
                    break;

                case "CO":
                    if ($cur_term !== null && $cur_term !== '') {
                        $search_client->addDescription($cur_term, $bool);
                    }
                    break;

                case "KW":
                    if ($cur_term !== null && $cur_term !== '') {
                        $search_client->addKeyword($cur_term);
                    }
                    break;                    

                case "KS":
                    break;

                case "PU":
                    break;

                case "LD":
                    if ($cur_term !== null && $cur_term !== '') {
                    $format = 'Y-m-d';
                    $d = DateTime::createFromFormat($format, $cur_term);
                    // Check if the date matches the format and is a valid calendar date
                        if ($d && $d->format($format) === $cur_term){
                            $search_client->addEntryDate($cur_term);
                        } else {
                            // TODO error handling
                        }

                    }
                    break;

                default:
                    $search_client->freeSearch($cur_term);
                    break;
            
            }

          }
        }
          

       // $q = isset($query_params['Suchwort']) && strlen($query_params['Suchwort']) > 0 ? $query_params['Suchwort'] : (
      //  isset($query_params['jq_term1']) && strlen($query_params['jq_term1']) > 0 ? $query_params['jq_term1'] : null);

        if ( isset($query_params['gebiete']) && is_countable($query_params['gebiete'])){
            $gebiete_id = array();
            foreach ($query_params['gebiete'] as &$id){
                $gebiete_id[] = (int)$id;
            }
        } else {
            $gebiete_id = isset($query_params['gebiete']) && strlen($query_params['gebiete']) > 0 ? (int)$query_params['gebiete'] : null;
        }

        $lett = $request->getQueryParams()['lett'] ?? null;
        $legacy_query_params['sort'] = $query_params['sort'] ?? null;
        switch($query_params['sort'] ?? null)
        {
            case 'alph':
                $sort_by = ALPHABETICAL_SORTING;
                break;
            case 'type':
                $sort_by = GROUP_BY_TYPE;
                break;
            default;
                $legacy_query_params['sort'] = 'alph';
                $sort_by = null;
                break;
        }

        $pagination_offset = isset($query_params['offset']) && strlen($query_params['offset']) > 0 ? (int)$query_params['offset'] : 0;
        $pagination_size = isset($query_params['hits_per_page']) && strlen($query_params['hits_per_page']) > 0 ? (int)$query_params['hits_per_page'] : self::DEFAULT_PAGINATION_SIZE;

        $db_type_ids = $query_params['db_type'] ?? null;
        if (is_string($db_type_ids)) {
            $db_type_ids = array($db_type_ids);
        }

        $color = isset($query_params['colors']) && strlen($query_params['colors']) > 0 ? (int)$query_params['colors'] : null;
        $licenseType = null;
        $licenseForm = null;
        $accessType = null;
        $accessForm = null;
        if ($color) {
            ['licenseType' => $licenseType, 'licenseForm' => $licenseForm,
                'accessType' => $accessType, 'accessForm' => $accessForm]
                = UsersWarpTo::mapFromColors($color);
            // debug:
            // die('color '.$color.' licenseType '.$licenseType.' licenseForm '.$licenseForm.' accessType '.$accessType.' accessForm '.$accessForm);
        }

        if ($sort_by == GROUP_BY_TYPE && $pagination_size != self::DEFAULT_PAGINATION_SIZE) {
            $response->getBody()->write('Wrong query parameters: Pagination and sort=type are mutually exclusive.');
            return $response->withHeader('Content-Type', 'text/plain')->withStatus(400);
        }

        $xmloutput = $request->getQueryParams()['xmloutput'] ?? false;
        if (!$xmloutput) {
            // LEGACY ROUTE(s): redirect regular queries (i.e. for non-XML) to ...

            // the current way of accessing a subject list entry
            // (we cannot reliably translate old to new subject ids, so this is best-effort)
            if ($gebiete_id) {
                if(isset(Subject::$oldIdToNewId[$gebiete_id])) {
                    $subject_id = Subject::$oldIdToNewId[$gebiete_id];
                    return $response->withHeader('Location', '/'.$ubr_id.'/browse/subjects/'.$subject_id.'/')->withStatus(301);
                } else {
                    return $response->withHeader('Location', '/'.$ubr_id.'/browse/subjects/')->withStatus(301);
                }
            } else if ($lett == 'c') {
                // query the DB using ubr_id and collid (which maps to collection.notation) for a collection ID to forward to

                if (isset($query_params['collid']) && strlen($query_params['collid']) > 0) {
                    $notation = $query_params['collid'];
                    $collectionId = $this->service->getCollectionIdByOrgAndNotation($ubr_id, $notation);
                } else {
                    $response->getBody()->write('Missing query parameter collid=');
                    return $response->withHeader('Content-Type', 'text/plain')->withStatus(404);
                }

                if ($collectionId) {
                    return $response->withHeader('Location', '/' . $ubr_id . '/browse/collections/' . $collectionId . '/')->withStatus(301);
                } else {
                    return $response->withHeader('Location', '/' . $ubr_id . '/browse/collections/')->withStatus(301);
                }
            } else {
                return $response->withHeader('Location',
                    ($ubr_id ? ('/'.$ubr_id) : '')
                    . '/results?availability-filter-free=on&availability-filter-local=on'
                    . ($lett == 'a' ? '&sort_by=1' : '')
                )->withStatus(301);
            }
        } // else: xmloutput=1

        $organization = null;
        if ($dbis_id) {
            $organization = $this->orgService->getOrganizationByUbrId($ubr_id);
            $access_mappings = $this->service->getAllAccessMappingsForDbisId($dbis_id);
        } else {
            $access_mappings = $this->service->getAllAccessMappingsForDbisId('alle_test'); // i have been told Gerald Schupfner is to be thanked for this...
        }

        $match_all = true;

        $maybe_subject = null;
        $maybe_collection = null;
        if ($gebiete_id) {
            // $subject_id = Subject::oldIdToNewId($gebiete_id);
            // automatic mapping of old and new ids does *not* work for all ids, so we cannot do it here (or it crashes)

            $gebiete_ids = is_array($gebiete_id) ? $gebiete_id : [$gebiete_id];

            foreach ($gebiete_ids as $id) {
                $subject_id = $id;
                $maybe_subject = $this->service->getSubjectById($subject_id);
                if (!$maybe_subject) {
                    $response->getBody()->write('No subject with ID ' . $id . ' found (from query param gebiete=' . $id . ').');
                    return $response->withHeader('Content-Type', 'text/plain')->withStatus(404);
                }
                $subject_title = $maybe_subject->getTitle()[$lang];
                $search_client->addSubject($subject_title);
            }


        } else if ($lett == 'c') {
            if (isset($query_params['collid']) && strlen($query_params['collid']) > 0) {
                $notation = $query_params['collid'];
                $collectionId = $this->service->getCollectionIdByOrgAndNotation($ubr_id, $notation);

                if ($collectionId) {
                    try {
                        $maybe_collection = $this->service->getCollectionById($collectionId, $ubr_id);
                        $search_client->addCollection('', $collectionId);
                    } catch (CollectionNotFoundException $e) {
                        $response->getBody()->write('No collection found for collection id '.$collectionId.' AND ubr_id='.$ubr_id);
                        return $response->withHeader('Content-Type', 'text/plain')->withStatus(404);
                    }
                } else {
                    $response->getBody()->write('No collection found for (collid='.$notation.' AND bib_id='.$dbis_id.')');
                    return $response->withHeader('Content-Type', 'text/plain')->withStatus(404);
                }
            } else {
                $response->getBody()->write('Specified collection mode (lett=c), but missing parameter collid=');
                return $response->withHeader('Content-Type', 'text/plain')->withStatus(404);
            }
        } // else: neither a specific subject nor a specific collection was requested

        /*if ($q && strlen($q) > 0) {
            $search_client->freeSearch($q);
            $match_all = false;
        }

        if ($match_all) {
            $search_client->matchAll();
        }*/

        if (!$all) {
            $global = true;
            $licensed = (bool)$ubr_id;
            $unlicensed = false;
            $search_client->addAvailability($global, $licensed, $unlicensed);
        }

        $search_client->setFrom($pagination_offset);
        $search_client->setSize($pagination_size);

        if ($sort_by == ALPHABETICAL_SORTING) {
            $search_client->sortAlphabetically();
        }

        // only return dbs with specific db_types
        if ($db_type_ids) {
            $types = $this->service->getTypes();
            foreach ($types as $resource_type) {
                foreach ($db_type_ids as $db_type_id) { // this has quadratic runtime, but #types will stay small
                    if ($resource_type->getId() == $db_type_id) {
                        $search_client->addType($resource_type->getTitle()[$lang]);
                    }
                }
            }
        }

        try {
            $results = $search_client->searchViaDsl();
        } catch (Exception $e) {
            $response->getBody()->write($e->getMessage());
            return $response->withHeader('Content-Type', 'text/plain')->withStatus(($e->getCode() >= 100 && $e->getCode() <= 599) ? $e->getCode() : 500);
        }

        $search_hits = $search_client->transformResults($results);
        $total_hits = $results['hits']['total']['value'];

        $xml_generator = new XMLGenerator();

        $data = $xml_generator->generateLegacyDbListePage($search_hits, $organization, $access_mappings, $maybe_subject, $maybe_collection, $lang, $sort_by, $q, $total_hits, $db_type_ids, $legacy_query_params, $lett);

        $response->getBody()->write($data);

        //$search_hits_dump = print_r($results, true);
        //$search_hits_dump = "\n".$search_hits_dump."\n";
        //$response->getBody()->write($search_hits_dump);

        return $response->withHeader('Content-Type', 'application/xml')->withStatus(200);
    }
}
