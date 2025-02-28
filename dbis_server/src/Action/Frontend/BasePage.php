<?php

namespace App\Action\Frontend;

use App\Domain\Resources\Constants\TrafficLight;
use App\Domain\Resources\Entities\License;
use Slim\Psr7\Stream;

abstract class BasePage {

    public function __construct() {

    }

    protected function build_filters($organization_id, $language, $queryParams): array
    {
        $filters = [];

        // SUBJECT AND COLLECTIONS FILTER
        if ($queryParams['filter-subjects'] ?? false) {
            // filters collections also
            $filterSubjects = $this->getSubjectsFromQueryArray($queryParams['filter-subjects']);

            $filters['all_subjects'] = array_map(function ($subject) use ($language)  {
                return $subject->toI18nAssocArray($language);
            }, $filterSubjects);
        } else {
            $filters['all_subjects'] = [];
        }

        // RESOURCE TYPE FILTER
        if ($queryParams['filter-resource-types'] ?? false) {
            $filterResourceTypes = $this->getResourceTypesFromQueryArray($queryParams['filter-resource-types']);

            $filters['resource-types'] = array_map(function ($resource_type) use ($language)  {
                return $resource_type->toI18nAssocArray($language);
            }, $filterResourceTypes);
        } else {
            $filters['resource-types'] = [];
        }

        // KEYWORD FILTER
        if ($queryParams['filter-keywords'] ?? false) {
            $keywords = $this->getKeywordsFromQueryArray($queryParams['filter-keywords']);

            $filters['keywords'] = array_map(function ($keyword) use ($language) {
                return $keyword->toI18nAssocArray($language);
            }, $keywords);

        } else {
            $filters['keywords'] = [];
        } 

        if ($queryParams['filter-license-forms'] ?? false) {
            // $liceneForms = $this->getLicenseFormsFromQueryArray($queryParams['filter-license-forms']);
            $filters['license-forms'] = array_map(function ($liceneForm) {    
                return $liceneForm;
            }, $queryParams['filter-license-forms']);
        } else {
            $filters['license-forms'] = [];
        }

        if ($queryParams['filter-license-types'] ?? false) {
            // $licenseTypes = $this->getLicenseTypesFromQueryArray($queryParams['filter-license-types']);
            $filters['license-types'] = array_map(function ($liceneType) {    
                return $liceneType;
            }, $queryParams['filter-license-types']);
        } else {
            $filters['license-types'] = [];
        }

        if ($queryParams['filter-access-forms'] ?? false) {
            // $accessForms = $this->getAccessFormsFromQueryArray($queryParams['filter-access-forms']);
            $filters['access-forms'] = array_map(function ($accessForm) {    
                return $accessForm;
            }, $queryParams['filter-access-forms']);
        } else {
            $filters['access-forms'] = [];
        }

        if ($queryParams['filter-access-labels'] ?? false) {
            $filters['access-labels'] = array_map(function ($accessForm) {    
                return $accessForm;
            }, $queryParams['filter-access-labels']);
        } else {
            $filters['access-labels'] = [];
        }
        
        // PUBLICATION FORM FILTER
        if ($queryParams['filter-publication-forms'] ?? false) {
            $publicationForms = $this->getPublicationFormsFromQueryArray($queryParams['filter-publication-forms']);

            $filters['publication-forms'] = array_map(function ($publicationForm) use ($language) {
                return $publicationForm->toI18nAssocArray($language);
            }, $publicationForms);

        } else {
            $filters['publication-forms'] = [];
        } 
        
        // HOST NAME FILTER
        // If host-ids is defined, we are working with js-enabled
        // host-text is the noscript fallback
        if ($queryParams['host-ids'] ?? false) {
            $filters['host-ids']  = array_map('intval', $queryParams['host-ids']);

            $filters['hosts'] = array_map(function ($id) use ($language) {
                $host = $this->service->getHostById($id);
                return $host->toAssocArray();
            }, $filters['host-ids']);
        } elseif ($queryParams['host-text'] ?? false) {
            $hosts = $this->getHostsFromQueryString($queryParams['host-text']);

            $filters['host-ids'] = array_map(function ($host) {
                return $host->getId();
            }, $hosts);
            $filters['hosts'] = array_map(function ($host) use ($language) {
                return $host->toI18nAssocArray($language);
            }, $hosts);
        } else {
            $filters['hosts'] = [];
        }

        // PUBLISHERS FILTER
        if ($queryParams['filter-publishers'] ?? false) {
            $filter_publishers = $this->getPublishersFromQueryArray($queryParams['filter-publishers']);
            $filters['publishers'] = array_map(function ($publisher) {
                return $publisher->toAssocArray();
            }, $filter_publishers);
        } else {
            $filters['publishers'] = [];
        } 

        // COUNTRIES FILTER
        // COUNTRY FILTER
        if ($queryParams['filter-countries'] ?? false) {
            $filter_countries = $this->getCountriesFromQueryArray($queryParams['filter-countries']);

            $filters['countries'] = array_map(function ($country) use ($language) {
                return $country->toI18nAssocArray($language);
            }, $filter_countries);

        } else {
            $filters['countries'] = [];
        } 

        // AUTHOR NAME FILTER
        // If author-ids is defined, we are working with js-enabled
        // author-text is the noscript fallback
        if ($queryParams['author-ids'] ?? false) {
            $filters['author-ids'] = array_map('intval', $queryParams['author-ids']);
            $filters['authors'] = array_map(function ($id) use ($language) {
                $author = $this->service->getAuthorById($id);
                return $author->toAssocArray();
            }, $filters['author-ids']);
        } elseif ($queryParams['author-text'] ?? false) {
            //$publishers = $this->getAuthorsFromQueryString($queryParams['author-text']);
            $filters['authors'] = array_map(function ($publisher) {    
                return $publisher;
            }, $queryParams['author-text']);

            /*$filters['author-ids'] = array_map(function ($host) {
                return $host->getId();
            }, $publishers);
            $filters['authors'] = array_map(function ($publisher) use ($language) {
                return $publisher->toI18nAssocArray($language);
            }, $publishers);*/
        } else {
            $filters['authors'] = [];
        }

        // AVAILABILITY FILTER
        // Here, we translate the availabilities (free, unavailabel etc.) into
        // respective license and access types
        $filterAvailability = [
            "free" => $queryParams['availability-filter-free'] ?? null,
            "local" => $queryParams['availability-filter-local'] ?? null,
            "none" => $queryParams['availability-filter-none'] ?? null,
        ];
        $filters['availability'] = $filterAvailability;
        // this will be used for filtering in database query
        $filters['access'] = [];
        if ($filterAvailability['free']) {
            // Only use free licenses
            //$filters['access'][] = ['license' => 1]; // For anniversary, since fake license is license=1 access=10
            $filters['access'][] = ['license' => 1, 'access' => '1'];
            $filters['access'][] = ['license' => 1, 'access' => '2'];
            $filters['access'][] = ['license' => 1, 'access' => '3'];
            $filters['access'][] = ['license' => 1, 'access' => '4'];
            $filters['access'][] = ['license' => 1, 'access' => '5'];
            $filters['access'][] = ['license' => 1, 'access' => '6'];
            $filters['access'][] = ['license' => 1, 'access' => '7'];
            $filters['access'][] = ['license' => 1, 'access' => '8'];
            $filters['access'][] = ['license' => 1, 'access' => '9'];
        }
        /*
        if ($filterAvailability['internet']) {
            // Use licenses "Campus", "National", "Pay per View"
            $filters['access'][] = ['license' => '2'];
            $filters['access'][] = ['license' => '3'];
            $filters['access'][] = ['license' => '4'];
        }
        if ($filterAvailability['local']) {
            // Use licenses "Einzelplatz"
            $filters['access'][] = ['license' => '5'];
            $filters['access'][] = ['license' => '2', 'access' => '9'];
            $filters['access'][] = ['license' => '4'];
        }
        */
        if ($filterAvailability['none']) {
            $filters['access'][] = ['access' => 'none'];
            if ($organization_id == null) {
                // For anniversary, to display fake license. Delete afterwards.
                // Also, the DBIs fake licenses should be included when no org is selected
                // and the "nicht verfügbar" filter is checked.
                $filters['access'][] = ['license' => 1, 'access' => '10'];
            }
        }
        // For anniversary, to display fake license. Delete afterwards.
        if ($filterAvailability['local']) {
            $filters['access'][] = ['license' => 1, 'access' => '10'];

            // Use licenses "Campus", "National", "Pay per View"
            $filters['access'][] = ['license' => '2'];
            $filters['access'][] = ['license' => '3'];
            $filters['access'][] = ['license' => '4'];

            // Use licenses "Einzelplatz"
            $filters['access'][] = ['license' => '5'];
            $filters['access'][] = ['license' => '2', 'access' => '9'];
            $filters['access'][] = ['license' => '4'];
        }

        $filters['entry-date'] = array('start', 'end');
        $filters['entry-date']['start'] = $queryParams['filter-entry-date-start'] ?? null;

        // TIME FILTERS
        $pubTimeStart = $queryParams['filter-publication-date-start'] ?? null;
        $pubTimeEnd = $queryParams['filter-publication-date-end'] ?? null;
        $filters['publication-time'] = [];
        $filters['publication-time']['start'] = $pubTimeStart;
        $filters['publication-time']['end'] = $pubTimeEnd;

        $reportTimeStart = $queryParams['filter-report-date-start'] ?? null;
        $reportTimeEnd = $queryParams['filter-report-date-end'] ?? null;
        $filters['report-time'] = [];
        $filters['report-time']['start'] = $reportTimeStart;
        $filters['report-time']['end'] = $reportTimeEnd;

        $filters['top-databases'] = array_key_exists('top-databases-filter', $queryParams) && $queryParams['top-databases-filter'] ? true: false;

        $filters['show-hidden-entries'] = false;

        return $filters;
    }

    protected function getKeywordsFromQueryString($q): array
    {
        $keywordStrings = explode(",", $q);
        $keywords = array_map(function ($keyword) {
            $entries = $this->service->getKeywords(["q" => trim($keyword)]);
            return count($entries) > 0 ? $entries[0] : null;
        }, $keywordStrings);
        // remove null entries
        return array_filter($keywords);
    }

    protected function getPublishersFromQueryArray($q){
        $publishers = array_map(function ($publisher_id) {
            return $this->service->getEnterpriseById(trim($publisher_id));
        }, $q);
        // remove null entries
        return array_filter($publishers);
    }

    protected function getKeywordsFromQueryArray($q): array
    {
        $keywords = array_map(function ($keyword) {
            return $this->service->getKeywordByText(trim($keyword));
        }, $q);
        // remove null entries
        return array_filter($keywords);
    }

    protected function getResourceTypesFromQueryArray($q): array
    {
        $resource_types = array_map(function ($resource_type) {
            return $this->service->getTypeByText(trim($resource_type));
        }, $q);
        // remove null entries
        return array_filter($resource_types);
    }   
    
    protected function getSubjectsFromQueryArray($q): array
    {
        $subjects = array_map(function ($subject) {
            $subject_hit = $this->service->getSubjectByText(trim($subject));
            return $subject_hit !== null ? $subject_hit : $this->service->getCollectionByText(trim($subject));        
        }, $q);
        // remove null entries
        return array_filter($subjects);
    }      

    protected function getCountriesFromQueryArray($q)
    {
        $countries = array_map(function ($country) {
            return $this->service->getCountryByText(trim($country));
        }, $q);
        // remove null entries
        return array_filter($countries);
    }

    protected function getCountriesFromQueryString($q): array
    {
        $countryStrings = explode(",", $q);
        $countries = array_map(function ($country) {
            $entries = $this->service->getCountries(["q" => trim($country)]);
            return count($entries) > 0 ? $entries[0] : null;
        }, $countryStrings);
        // remove null entries
        return array_filter($countries);
    }

    
    protected function getPublicationFormsFromQueryArray($q): array
    {
        $publicationForms = array_map(function ($publicationForm) {
            return $this->service->getPublicationFormByText(trim($publicationForm));
        }, $q);
        // remove null entries
        return array_filter($publicationForms);
    }       

    protected function getHostsFromQueryString($q): array
    {
        $hostStrings = explode(",", $q);
        $hosts = array_map(function ($q) {
            $entries = $this->service->getHosts(["q" => trim($q)]);
            return count($entries) > 0 ? $entries[0] : null;
        }, $hostStrings);
        // Removes null entries
        return array_filter($hosts);
    }

    protected function getAuthorsFromQueryString($q): array
    {
        $authorsStrings = explode(",", $q);
        $authors = array_map(function ($q) {
            $entries = $this->service->getAuthors(["q" => trim($q)]);
            return count($entries) > 0 ? $entries[0] : null;
        }, $authorsStrings);
        // Removes null entries
        return array_filter($authors);
    }


    // PUBLISHERS Aggregations
    protected function buildPublisherAggregations(array $publisherAggs){

            $publisher_aggs_new = $publisherAggs['publisher']['buckets'];
  

            foreach ($publisher_aggs_new as &$publisherAgg){
            
                $publisherAgg['name'] = $this->getPublishersFromQueryArray([$publisherAgg['key']])[0]->getTitle();


            }

            $publisherAggs['publisher']['buckets'] = $publisher_aggs_new;

            //var_dump($publisherAggs);

        return $publisherAggs;
    }

    protected function transformResourcesToAssoc(array $resources, string $lang): array
    {
        $arrays = [];
        foreach ($resources as $res) {
            $res_assoc = $res->toI18nAssocArray($lang);
            // Truncate description string as Twig can't do it without decoding html entities
            $res_assoc['description'] = $res->truncateDescription($lang);
            $arrays[] = $res_assoc;
        }
        return $arrays;
    }

    protected function transformAccessTypesToAssoc(array $accessTypes, string $lang): array
    {
        $arrays = [];
        foreach ($accessTypes as $acc) {
            $arrays[] = $acc->toI18nAssocArray($lang);
        }
        return $arrays;
    }

    protected function determineTopDatabasesForSubject(array $resources, $subject) {
        $id = $subject->getId();

        foreach ($resources as &$resource) {
            $resource['is_top_database_for_subject'] = false;
            foreach ($resource['subjects'] as $subjectsOfResource) {
                if ($subjectsOfResource['id'] == $id && $subjectsOfResource['is_top_database'] == true) {
                    $resource['is_top_database_for_subject'] = true;
                }
            }
        }

        return $resources;
    }

    protected function determineTopDatabasesForCollection(array $resources, $collection) {
        $id = $collection->getId();

        foreach ($resources as &$resource) {
            $resource['is_top_database_for_subject'] = false;
            foreach ($resource['collections'] as $collectionOfResource) {
                if ($collectionOfResource['is_subject'] == true) {
                    if ($collectionOfResource['id'] == $id && $collectionOfResource['is_top_database'] == true) {
                        $resource['is_top_database_for_subject'] = true;
                    }
                }
            }
        }

        return $resources;
    }

    protected function groupOrganizationsByCity(
        array $organizations = []
    ): array {
        $groupedOrganizations = [];

        foreach ($organizations as $org) {
            if (!key_exists(mb_strtoupper($org["city"]), $groupedOrganizations)) {
                $groupedOrganizations[mb_strtoupper($org["city"])] = [$org];
            } else {
                array_push($groupedOrganizations[mb_strtoupper($org["city"])], $org);
            }
        }
        ksort($groupedOrganizations);

        return $groupedOrganizations;
    }

    protected function generateHash($url) {
        $secretKey = $this->service->getSecret();

        return hash_hmac('sha256', $url, $secretKey);
    }

    protected function determineTrafficLights(array $resources): array
    {
        $array = [];
        foreach ($resources as $resource) {
            $is_free = $resource['is_free'];
            $resource["traffic_light"] = $this->extractTrafficLight($is_free, $resource["licenses"]);

            $array[] = $resource;
        }
        return $array;
    }

    protected function determineTrafficLight(array $resource, bool $isElasticResult = true): array
    {
        $is_free = $resource['is_free'];
        $resource["traffic_light"] = $this->extractTrafficLight($is_free, $resource["licenses"]);
        return $resource;
    }

    protected static function extractTrafficLight($is_free, mixed $licenses): ?string
    {
        if ($is_free) {
            // Always return green light, if resource is free.
            return TrafficLight::GREEN->value;
        }
        
        $traffic_light = null;

        foreach ($licenses as $lic) {
            if ($lic instanceof License) {
                $license_type = $lic->getType()?->getId();
                $license_form = $lic->getForm()?->getId();
                if (!$lic->isActive()) {
                    continue;
                }
            } else {
                $license_type = (int) ($lic["type"]["id"] ?? $lic["type"]);
                // Global licenses have no license form
                $license_form = (int) ($lic["form"]["id"] ?? $lic["form"] ?? -1);
                if ((isset($lic['isActive']) && !$lic['isActive']) || (isset($lic['is_active']) && !$lic['is_active'])) {
                    continue;
                }
            }

            switch ($license_type) {
                case 1: // Freely available
                    // Global
                    if (is_null($traffic_light)) {
                        $traffic_light = TrafficLight::GREEN->value;
                    }
                    
                    break;
                    // Lokal, national, consortial
                case 2: // Local Licence
                    return TrafficLight::YELLOW->value;
                case 3: // National License
                case 5: // Consortial License
                    $traffic_light = TrafficLight::YELLOW->value;
                    break;
                case 4: // FID License
                    if (is_null($traffic_light) || $traffic_light == TrafficLight::GREEN->value) {
                        if ($license_form === 41 || $license_form === 43) { // FID National License || FID Campus License
                            $traffic_light = TrafficLight::YELLOW->value;
                        } else {
                            $traffic_light = TrafficLight::RED->value;
                        }
                    }
                    // return $traffic_light;
                    break;
                case 6: // Remote Access
                    if (is_null($traffic_light) || $traffic_light == TrafficLight::GREEN->value) {
                        if ($license_form === 61) { // ZB MED
                            // $traffic_light = null;
                            // If ZB Zugriff exists return null, because no traffic light should be displayed
                            // TODO: why
    
                            $traffic_light = TrafficLight::RED->value;
                            // return $traffic_light;
                        }
                    }
                    break;
            }
        }

        if (is_null($traffic_light)) {
            // If no license is stated, then return the red light
            return TrafficLight::RED->value;
        } else {
            return $traffic_light;
        }
    }

    /**
     * I/O security functions
     */

     protected function decode_safe($input): ?string 
     {
         // Step 1: Decode all valid entities to their character equivalents
         $decoded = html_entity_decode($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
         
         // Step 2: Strip any remaining HTML tags
         $cleaned = strip_tags($decoded);
         
         // Step 3: Optionally trim or normalize whitespace (if needed)
         $cleanOutput = trim($cleaned);
         
         return $cleanOutput;
     }      
 
     protected function redirectToCsvOutput($request, $response, $resources, $organization_id) {
         $headers = ['DBIS-Ressource_ID', 'Titel', 'Frontdoor-URL'];
 
         $csvData = array();
 
         foreach ($resources as $resource) {
             $resourceId = $resource['resource_id'];
             $resourceTitle = $resource['resource_title'];
             $fields['resource'] = $resourceId;
 
             $resourceTitle = str_replace('"', '""', $resourceTitle); // Escape double quotes
             if (strpos($resourceTitle, ',') !== false || strpos($resourceTitle, '"') !== false || strpos($resourceTitle, "\n") !== false) {
                 $resourceTitle = '"' . $resourceTitle . '"'; // Wrap in double quotes if resourceTitle
             }
             $fields['title'] = $resourceTitle;
 
             $fields['frontdoor'] = "https://dbis.ur.de/$organization_id/resources/$resourceId";
             $csvData[] = $fields;
         }
 
         $currentDate = date('Y-m-d');
         $csvFile = "DBIS_Export_$currentDate.csv";
 
         $stream = fopen('php://temp', 'r+');
 
         fputcsv($stream, $headers);
 
         foreach ($csvData as $row) {
             fputcsv($stream, [
                 $row['resource'],
                 $row['title'],
                 $row['frontdoor']
             ]);
         }
 
         rewind($stream);
 
         $body = new Stream($stream);
 
         return $response->withHeader('Content-Type', 'text/csv; charset=UTF-8')
             ->withHeader('Content-Disposition', "attachment; filename=$csvFile")
             ->withAddedHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
             ->withHeader('Cache-Control', 'post-check=0, pre-check=0')
             ->withHeader('Pragma', 'no-cache')
             ->withBody($body);
    } 
}