<?php

namespace App\Infrastructure\Shared;

use Exception;

/**
 * UsersResultsPage
 *
 */

error_reporting(E_ALL & ~E_DEPRECATED);

class SearchClient
{
    private $client;

    private ?string $organization_id;

    private string $lang;

    private $bool_for_filtered_subject_search;

    private $bool_for_filtered_authors_search;
    private $bool_for_filtered_all_subject_search;
    private $bool_for_filtered_publishers_search;
    private $bool_for_filtered_country_search;
    private $bool_for_filtered_keyword_search;
    private $bool_for_filtered_type_search;
    private $bool_for_filtered_license_type_search;
    private $bool_for_filtered_license_form_search;
    private $bool_for_filtered_all_license_form_search;
    private $bool_for_filtered_access_form_search;
    private $bool_for_filtered_access_label_search;
    private $bool_for_filtered_publication_form_search;
    private $bool_for_filtered_all_publication_form_search;

    private \ONGR\ElasticsearchDSL\Search $search;

    /**
     * @var array If not null then ElasticSearch's _source filtering is enabled, using the values from this field.
     */
    private array|null $sourceFilters = null;

    public function __construct(string $organization_id = null, string $lang = 'de')
    {
        $IS_PRODUCTIVE = filter_var(getenv('PRODUCTIVE'), FILTER_VALIDATE_BOOLEAN);

        if ($IS_PRODUCTIVE) {
            $hosts = [
                'https://127.0.0.1:9200',
            ];
            $this->client = \Elastic\Elasticsearch\ClientBuilder::create()
                ->setSSLVerification(false)
                ->setBasicAuthentication(getenv('ELASTICSEARCH_USER'), getenv('ELASTICSEARCH_PASSWORD'))
                ->setHosts($hosts)
                ->build();
        } else {
            $hosts = [
                'http://elasticsearch:9200',
            ];
            $this->client = \Elastic\Elasticsearch\ClientBuilder::create()
                ->setSSLVerification(false)
                ->setHosts($hosts)
                ->build();
        }

        $this->reinitializeBoolQuery();

        // Set null if 'ALL' (=Gesamtbestand) has been selected
        $this->organization_id = $organization_id === 'ALL' ? null : $organization_id;

        $this->lang = $lang;

        $this->search = new \ONGR\ElasticsearchDSL\Search();
        $this->search->setSize(25);
        $this->search->setFrom(0);
    }

    private function reinitializeBoolQuery() {
        $this->bool_for_filtered_subject_search = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();
        $this->bool_for_filtered_subject_search->addParameter("minimum_should_match", 1);

        $this->bool_for_filtered_authors_search = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();
        $this->bool_for_filtered_authors_search->addParameter("minimum_should_match", 1);

        $this->bool_for_filtered_all_subject_search = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();
        $this->bool_for_filtered_all_subject_search->addParameter("minimum_should_match", 1);

        $this->bool_for_filtered_publishers_search = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();
        $this->bool_for_filtered_publishers_search->addParameter("minimum_should_match", 1);

        $this->bool_for_filtered_country_search = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();
        $this->bool_for_filtered_country_search->addParameter("minimum_should_match", 1);

        $this->bool_for_filtered_keyword_search = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();
        $this->bool_for_filtered_keyword_search->addParameter("minimum_should_match", 1);

        $this->bool_for_filtered_type_search = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();
        $this->bool_for_filtered_type_search->addParameter("minimum_should_match", 1);

        $this->bool_for_filtered_license_type_search = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();
        $this->bool_for_filtered_license_type_search->addParameter("minimum_should_match", 1);

        $this->bool_for_filtered_license_form_search = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();
        $this->bool_for_filtered_license_form_search->addParameter("minimum_should_match", 1);

        $this->bool_for_filtered_all_license_form_search = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();
        $this->bool_for_filtered_all_license_form_search->addParameter("minimum_should_match", 1);        

        $this->bool_for_filtered_access_form_search = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();
        $this->bool_for_filtered_access_form_search->addParameter("minimum_should_match", 1);

        $this->bool_for_filtered_access_label_search = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();
        $this->bool_for_filtered_access_label_search->addParameter("minimum_should_match", 1);

        $this->bool_for_filtered_publication_form_search = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();
        $this->bool_for_filtered_publication_form_search->addParameter("minimum_should_match", 1);

        $this->bool_for_filtered_all_publication_form_search = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();
        $this->bool_for_filtered_all_publication_form_search->addParameter("minimum_should_match", 1);        
    } 

    public function addQuery($field, $value, $bool): void
    {
        /*
        If "or" it should be a bool minimum one and if "and" it should be concat with that bool minimum search
        */
        $term_query = new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery($field, $value);

        $bool = strtolower($bool);
        if ($bool == "and") {
            $this->search->addQuery($term_query);
        } elseif ($bool == "or") {
            $this->search->addQuery($term_query, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);
        } else {
            $this->search->addQuery($term_query, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::MUST_NOT);
        }
    }

    public function freeSearch(string $q): void
    {
        $q = $this->escapeElasticReservedChars($q);

        if (preg_match("/^\d+$/", $q)) {
            $idsQuery = new \ONGR\ElasticsearchDSL\Query\TermLevel\IdsQuery([$q]);
            $this->search->addQuery($idsQuery);
        } else {
            if ($this->organization_id) {
                // Search local title including wildcards
                $string_query_local = new \ONGR\ElasticsearchDSL\Query\FullText\SimpleQueryStringQuery($q);
                $string_query_local->addParameter('fields', ['resource_localisations.title^2', 'resource_localisations.description.*', 'resource_localisations.note.*']);
                $string_query_local->addParameter('analyze_wildcard', true);     
                
                
                $bool_term_query_local = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();
                $bool_term_query_local->add($string_query_local, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::MUST);
            }

            // Search global title including wildcards
            // $string_query = new \ONGR\ElasticsearchDSL\Query\FullText\QueryStringQuery("*" . $q . "*");
            $string_query = new \ONGR\ElasticsearchDSL\Query\FullText\SimpleQueryStringQuery($q);
            $string_query->addParameter('fields', ['resource_title^3', 'alternative_titles.title^2', 'description.*']);
            $string_query->addParameter('analyze_wildcard', true);
            $string_query->addParameter('boost', 2); 
            
            $bool = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();
            $bool->addParameter("minimum_should_match", 1);
            $bool->add($string_query, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);

            if ($this->organization_id) {
                $bool->add($bool_term_query_local, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);
            }
            
            $this->search->addQuery($bool);
        }
    }

    public function addDescription(string $q, string $bool): void
    {
        $q = $this->escapeElasticReservedChars($q);

        if ($this->organization_id) {
            // Search local title including wildcards
            $string_query_local = new \ONGR\ElasticsearchDSL\Query\FullText\SimpleQueryStringQuery($q);
            $string_query_local->addParameter('fields', ['resource_localisations.description']);
            $string_query_local->addParameter('analyze_wildcard', true);


            // Constrain to certain Organisations
            $term_filter =
                new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery('resource_localisations.organisation.keyword', $this->organization_id);
            $bool_term_query_local = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();

            
            $bool_term_query_local->add($term_filter, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::MUST);
            $bool_term_query_local->add($string_query_local, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::MUST);

        }

            $string_query_global = new \ONGR\ElasticsearchDSL\Query\FullText\SimpleQueryStringQuery($q);
            $string_query_global->addParameter('fields', ['description.*']);
            $string_query_global->addParameter('analyze_wildcard', true);

            $bool_term_query_local->add($term_filter, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::MUST);
            $bool_term_query_local->add($string_query_global, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::MUST);
        

        // Search global title including wildcards
        $boolQuery = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();
        $boolQuery->addParameter("minimum_should_match", 1);
        $boolQuery->add($string_query_global, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);

        if ($this->organization_id) {
            $boolQuery->add($bool_term_query_local, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);
        }
        
        $bool = strtolower($bool);
        if ($bool == "and") {
            $this->search->addQuery($boolQuery);
        } elseif ($bool == "or") {
            $this->search->addQuery($boolQuery, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);
        } else {
            $this->search->addQuery($boolQuery, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::MUST_NOT);
        }
    }

    public function addTitle(string $q, string $bool): void
    {
        $q = $this->escapeElasticReservedChars($q);

        // Search local title including wildcards
        if ($this->organization_id) {
            $string_query_local = new \ONGR\ElasticsearchDSL\Query\FullText\SimpleQueryStringQuery($q);
            $string_query_local->addParameter('fields', ['resource_localisations.title']);
            $string_query_local->addParameter('analyze_wildcard', true); 

            // Constrain to certain Organisations
            $term_filter =
            new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery('resource_localisations.organisation.keyword', $this->organization_id);

            $bool_term_query_local= new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();
            
            $bool_term_query_local->add($term_filter, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::MUST);
            $bool_term_query_local->add($string_query_local, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::MUST);
        }
        
        // Search global title including wildcards
        $string_query_global = new \ONGR\ElasticsearchDSL\Query\FullText\SimpleQueryStringQuery($q);
        $string_query_global->addParameter('fields', ['resource_title', 'alternative_titles']);
        $string_query_global->addParameter('analyze_wildcard', true);     

        $boolQuery = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();
        $boolQuery->addParameter("minimum_should_match", 1);
        $boolQuery->add($string_query_global, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);

        if ($this->organization_id) {
            $boolQuery->add($bool_term_query_local, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);
        }
        
        $bool = strtolower($bool);
        if ($bool == "and") {
            $this->search->addQuery($boolQuery);
        } elseif ($bool == "or") {
            $this->search->addQuery($boolQuery, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);
        } else {
            $this->search->addQuery($boolQuery, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::MUST_NOT);
        }
    }

    public function matchAll(): void
    {
        $matchAll = new \ONGR\ElasticsearchDSL\Query\MatchAllQuery();

        $this->search->addQuery($matchAll);
    }

    public function addCountry(string $value): void
    {
        $field = "countries.title." . $this->lang . ".keyword";
        // $bool = "or";

        $term_query = new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery($field, $value);
        $this->bool_for_filtered_country_search->add($term_query, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);

        // $this->addQuery($field, $value, $bool);
    }

    public function addSubject(string $value, ?int $id = null): void
    {
        if ($id) {
            $field = "subjects.id";
            // $bool = "and";
            $term_query = new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery($field, $id);
        } else {
            $field = "subjects.title." . $this->lang . ".keyword";
            // $bool = "and";
            
            $term_query = new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery($field, $value);
        }
        
        $this->bool_for_filtered_subject_search->add($term_query, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);

        // $this->addQuery($field, $value, $bool);
    }

    public function addAuthor(string $value, ?int $id = null): void
    {
        if ($id) {
            $field = "authors.id";

            $term_query = new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery($field, $id);
        } else {
            $field = "authors.title.keyword";
            
            $term_query = new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery($field, $value);
        }
        
        $this->bool_for_filtered_authors_search->add($term_query, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);

    }    

    public function addAllSubject(string $value): void
    {

    $field = "subjects.title." . $this->lang . ".keyword";
        $term_query = new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery($field, $value);
        $this->bool_for_filtered_all_subject_search->add($term_query, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);

    }

    public function addPublisher($publisher_id): void
    {

    $field = "licenses.publisher";
        $term_query = new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery($field, $publisher_id);
        $this->bool_for_filtered_publishers_search->add($term_query, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);

    }    

    public function addTopDatabases(): void
    {
        $bool_top_database = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();

        $termQueryIsTopDatabaseOfSubjects = new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery("subjects.is_top_database", true);
        $termQueryIsTopDatabaseOfCollections= new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery("collections.is_top_database", true);
        
        $bool_top_database->add($termQueryIsTopDatabaseOfSubjects, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);
        $bool_top_database->add($termQueryIsTopDatabaseOfCollections, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);

        $this->search->addQuery($bool_top_database);
    }

    public function addTopDatabasesForSubject($subject_id): void
    {
        // OLD CODE
        $termQueryIsTopDatabaseOfSubjects = new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery("subjects.is_top_database", true);
        $this->search->addQuery($termQueryIsTopDatabaseOfSubjects);

        // TODO: Only the subject with the certain id should be checked for top_database
        /*
        $matchQuery = new \ONGR\ElasticsearchDSL\Query\FullText\MatchQuery('subjects.id', $subject_id);
        $nestedQuery = new \ONGR\ElasticsearchDSL\Query\Joining\NestedQuery('subjects', $matchQuery);
        $searchQuery = new \ONGR\ElasticsearchDSL\Search();
        $searchQuery->addQuery($matchQuery);
        $innerHit = new \ONGR\ElasticsearchDSL\InnerHit\NestedInnerHit('subject', 'subjects', $searchQuery);

        $matchQuery2 = new \ONGR\ElasticsearchDSL\Query\FullText\MatchQuery('subjects.is_top_database', true);
        $this->search->addQuery($matchQuery2);
        $this->search->addInnerHit($innerHit);
        */
    }

    public function addTopDatabasesForCollection(): void
    {
        $termQueryIsTopDatabaseOfCollections= new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery("collections.is_top_database", true);
        $this->search->addQuery($termQueryIsTopDatabaseOfCollections);
    }

    public function addCollection(string $value, ?int $id): void
    {
        if ($id) {
            $field = "collections.id";
            // $bool = "and";
            $term_query = new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery($field, $id);
        } else {
            $field = "collections.title." . $this->lang . ".keyword";
            // $bool = "and";
            
            $term_query = new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery($field, $value);
        }
        
        $this->bool_for_filtered_subject_search->add($term_query, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::MUST);
        /*
        $term_query = new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery($field, $value);
        $this->bool_for_filtered_subject_search->add($term_query, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);
        */

        // $this->addQuery($field, $value, $bool);
    }

    public function addKeyword(string $value): void
    {
        $field = "keywords.title." . $this->lang . ".keyword";
        // $bool = "and";
        
        $term_query = new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery($field, $value);
        $this->bool_for_filtered_keyword_search->add($term_query, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);

        // $this->addQuery($field, $value, $bool);
    }

    public function addType(string $value): void
    {
        $field = "resource_types.title." . $this->lang . ".keyword";
        // $bool = "and";
        
        $term_query = new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery($field, $value);
        $this->bool_for_filtered_type_search->add($term_query, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);

        // $this->addQuery($field, $value, $bool);
    }


    public function addLicenseType($value): void {
        $field = 'licenses.type';

        // $bool = "and";

        $term_query = new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery($field, $value);
        $this->bool_for_filtered_license_type_search->add($term_query, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);

        // $this->addQuery($field, $value, $bool);
    }

    public function addLicenseForm($value): void {
        $field = 'licenses.form';

        // $bool = "and";

        $term_query = new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery($field, $value);
        $this->bool_for_filtered_license_form_search->add($term_query, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);

        // $this->addQuery($field, $value, $bool);
    }

    public function addAllLicenseForm($value): void {
        $field = 'licenses.form';

        // $bool = "and";

        $term_query = new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery($field, $value);
        $this->bool_for_filtered_all_license_form_search->add($term_query, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);

        // $this->addQuery($field, $value, $bool);
    }    

    public function addAccessForm($value): void {
        $field = 'licenses.accesses.form';

        // $bool = "and";

        $term_query = new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery($field, $value);
        $this->bool_for_filtered_access_form_search->add($term_query, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);

        // $this->addQuery($field, $value, $bool);
    }

    public function addAccessLabel($value): void {
        if (is_null($value)) {
            $term_exists = 
                new \ONGR\ElasticsearchDSL\Query\TermLevel\ExistsQuery('licenses.accesses.label_id');
            $bool_no_access_labels = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();
            $bool_no_access_labels->add($term_exists, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::MUST_NOT);

            $this->bool_for_filtered_access_form_search->add($bool_no_access_labels, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);
        } else {
            $field = 'licenses.accesses.label_id';

            $term_query = new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery($field, $value);
            $this->bool_for_filtered_access_form_search->add($term_query, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);
        }
    }

    public function addPublicationForm($value): void {
        $field = "licenses.publication_form.title." . $this->lang . ".keyword";

        // $bool = "and";

        $term_query = new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery($field, $value);
        $this->bool_for_filtered_publication_form_search->add($term_query, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);

        // $this->addQuery($field, $value, $bool);
    }

    public function addAllPublicationForm($value): void {
        $field = "licenses.publication_form.title." . $this->lang . ".keyword";

        $term_query = new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery($field, $value);
        $this->bool_for_filtered_all_publication_form_search->add($term_query, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);

    }

    public function addEntryDate(string $date): void{
        $rangeQuery = new \ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery(
            'created_at',
            [
                'gte' => $date,
                "format" => "yyyy-MM-dd"
            ]
        );
        
        $this->search->addQuery($rangeQuery);
    }

    public function addPublicationTimeStart(string $date): void{
        $rangeQuery = new \ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery(
            'publication_time_start',
            [
                'gte' => $date,
                "format" => "yyyy-MM-dd"
            ]
        );
        
        $this->search->addQuery($rangeQuery);
    }

    public function addPublicationTimeEnd(string $date): void{
        $rangeQuery = new \ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery(
            'publication_time_end',
            [
                'lte' => $date,
                "format" => "yyyy-MM-dd"
            ]
        );
        
        $this->search->addQuery($rangeQuery);
    }

    public function addReportTimeStart(string $date): void{
        $rangeQuery = new \ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery(
            'report_time_start',
            [
                'gte' => $date,
                "format" => "yyyy-MM-dd"
            ]
        );
        
        $this->search->addQuery($rangeQuery);
    }

    public function addReportTimeEnd(string $date): void{
        $rangeQuery = new \ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery(
            'report_time_end',
            [
                'lte' => $date,
                "format" => "yyyy-MM-dd"
            ]
        );
        
        $this->search->addQuery($rangeQuery);
    }

    public function addAvailability(
        bool $global = true,
        bool $licensed = false,
        bool $unlicensed = false
    ): void {
        $bool = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();

        if ($global) {
            $termQueryGlobal =
                new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery("licenses.is_global", $global);
            $bool->add($termQueryGlobal, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);
        }

        if ($licensed && !is_null($this->organization_id)) {
            $bool_licensed = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();

            $term_exists = 
                new \ONGR\ElasticsearchDSL\Query\TermLevel\ExistsQuery('licenses');
            $termQueryNotGlobal =
                new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery("licenses.is_global", false);

            $bool_licensed->add($term_exists, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::MUST);
            $bool_licensed->add($termQueryNotGlobal, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::MUST);

            if ($global || $unlicensed) {
                $boolQueryForTag1 = \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD;
            } else {
                $boolQueryForTag1 = \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::MUST;
            }
            $bool->add($bool_licensed, $boolQueryForTag1);
        }

        if ($unlicensed) {
            $term_exists = 
                new \ONGR\ElasticsearchDSL\Query\TermLevel\ExistsQuery('licenses');
            $bool_unlicensed = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();
            $bool_unlicensed->add($term_exists, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::MUST_NOT);


            if ($global || $licensed) {
                $boolQueryForTag1 = \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD;
            } else {
                $boolQueryForTag1 = \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::MUST;
            }

            $bool->add($bool_unlicensed, $boolQueryForTag1);
        }

        $this->search->addQuery($bool);
    }

    public function addGlobalFacet(): void
    {
        $termQueryGlobal = new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery("licenses.is_global", true);

        $filterAggregation = new \ONGR\ElasticsearchDSL\Aggregation\Bucketing\FilterAggregation('global', $termQueryGlobal);

        $this->search->addAggregation($filterAggregation);
    }

    public function addLicensedFacet(): void
    {
        $bool_licensed = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();

        $term_exists = 
            new \ONGR\ElasticsearchDSL\Query\TermLevel\ExistsQuery('licenses');
        $termQueryNotGlobal =
            new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery("licenses.is_global", false);

        $bool_licensed->add($term_exists, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::MUST);
        $bool_licensed->add($termQueryNotGlobal, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::MUST);

        $filterAggregation =
            new \ONGR\ElasticsearchDSL\Aggregation\Bucketing\FilterAggregation('licensed', $bool_licensed);

        $this->search->addAggregation($filterAggregation);
    }

    public function addUnlicensedFacet(): void
    {      
        $term_exists = 
            new \ONGR\ElasticsearchDSL\Query\TermLevel\ExistsQuery('licenses');
        $bool_unlicensed = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();
        $bool_unlicensed->add($term_exists, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::MUST_NOT);

        $filterAggregation = new \ONGR\ElasticsearchDSL\Aggregation\Bucketing\FilterAggregation(
            'unlicensed', $bool_unlicensed);

        $this->search->addAggregation($filterAggregation);
    }

    public function addSubjectFacet(): void
    {
        $field = 'subjects.title.' . $this->lang . '.keyword';



        $termsAggregation =
            new \ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation('subjects', $field);
        $termsAggregation->addParameter('size', 1000);
        $termsAggregation->addParameter('order', array('_key' => 'asc'));


        $this->search->addAggregation($termsAggregation);
    }

    public function addAllSubjectFacet(): void
    {
        $field = 'subjects.title.' . $this->lang . '.keyword';

        $globalAggregation = new \ONGR\ElasticsearchDSL\Aggregation\Bucketing\GlobalAggregation('all_subjects');

        $termsAggregation =
            new \ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation('subjects', $field);
        $termsAggregation->addParameter('size', 1000);
        $termsAggregation->addParameter('order', array('_key' => 'asc'));
        $globalAggregation->addAggregation($termsAggregation);

                // Step 3: For each filter option, add a sub-aggregation that applies the current search query
                $filterAggregation = new \ONGR\ElasticsearchDSL\Aggregation\Bucketing\FilterAggregation(
                    'subjects_sorted',
                    $this->search->getQueries() // Use the main query to filter results
                );
        
                // Add the terms aggregation under the filter aggregation
                $filterAggregation->addAggregation($termsAggregation);
        
                // Add the TermsAggregation to the GlobalAggregation
                $globalAggregation->addAggregation($filterAggregation);

        //$globalAggregation->addAggregation($termsAggregation);
        $this->search->addAggregation($globalAggregation);
        
    }

    public function addKeywordFacet(): void
    {
        $field = 'keywords.title.' . $this->lang . '.keyword';

        $termsAggregation =
            new \ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation('keywords', $field);
        $termsAggregation->addParameter('size', 1000);
        $termsAggregation->addParameter('order', array('_key' => 'asc'));
        $this->search->addAggregation($termsAggregation);
    }

    public function addAllPublicationFormFacet(): void
    {
        $field = 'licenses.publication_form.title.' . $this->lang . '.keyword';

        $globalAggregation = new \ONGR\ElasticsearchDSL\Aggregation\Bucketing\GlobalAggregation('all_licenses');
        
        // Create the TermsAggregation to bucket by the specified field
        $termsAggregation = new \ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation('publication_forms', $field);
        $termsAggregation->addParameter('size', 1000);
        $termsAggregation->addParameter('order', array('_key' => 'asc'));
        $globalAggregation->addAggregation($termsAggregation);
        

        // Step 3: For each filter option, add a sub-aggregation that applies the current search query
        $filterAggregation = new \ONGR\ElasticsearchDSL\Aggregation\Bucketing\FilterAggregation(
            'publication_forms_sorted',
            $this->search->getQueries() // Use the main query to filter results
        );

        // Add the terms aggregation under the filter aggregation
        $filterAggregation->addAggregation($termsAggregation);

        // Add the TermsAggregation to the GlobalAggregation
        $globalAggregation->addAggregation($filterAggregation);

        //var_dump($globalAggregation->toArray());
        
        // Add the GlobalAggregation to the search object
        $this->search->addAggregation($globalAggregation);
    }

    public function addPublicationFormFacet(): void
    {
        $field = 'licenses.publication_form.title.' . $this->lang . '.keyword';

        $termsAggregation =
            new \ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation('publication_forms', $field);
        $termsAggregation->addParameter('size', 1000);
        $termsAggregation->addParameter('order', array('_key' => 'asc'));
        $this->search->addAggregation($termsAggregation);
    }

    public function addTypeFacet(): void
    {
        $field = 'resource_types.title.' . $this->lang . '.keyword';

        $termsAggregation =
            new \ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation(
                'resource_types',
                $field
            );
        $termsAggregation->addParameter('size', 1000);
        $termsAggregation->addParameter('order', array('_key' => 'asc'));
        $this->search->addAggregation($termsAggregation);
    }

    public function addAllTypeFacet(): void
    {
        $field = 'resource_types.title.' . $this->lang . '.keyword';

        $globalAggregation = new \ONGR\ElasticsearchDSL\Aggregation\Bucketing\GlobalAggregation('all_resource');


        $termsAggregation =
            new \ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation(
                'resource_types',
                $field
            );
        $termsAggregation->addParameter('size', 1000);
        $termsAggregation->addParameter('order', array('_key' => 'asc'));
        $globalAggregation->addAggregation($termsAggregation);
        

        // Step 3: For each filter option, add a sub-aggregation that applies the current search query
        $filterAggregation = new \ONGR\ElasticsearchDSL\Aggregation\Bucketing\FilterAggregation(
            'all_resources_sorted',
            $this->search->getQueries() // Use the main query to filter results
        );

        // Add the terms aggregation under the filter aggregation
        $filterAggregation->addAggregation($termsAggregation);

        // Add the TermsAggregation to the GlobalAggregation
        $globalAggregation->addAggregation($filterAggregation);

        //$globalAggregation->addAggregation($termsAggregation);
        $this->search->addAggregation($globalAggregation);
    }

    public function addLicenseTypeFacet() {
        // TODO
    }

    public function addCountryFacet(): void
    {
        $field = 'countries.title.' . $this->lang . '.keyword';

        $termsAggregation =
            new \ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation(
                'countries',
                $field
            );
        $termsAggregation->addParameter('size', 1000);
        $termsAggregation->addParameter('order', array('_key' => 'asc'));
        $this->search->addAggregation($termsAggregation);
    }    

    public function addPublisherFacet(): void
    {
        $field = 'licenses.publisher';

        $termsAggregation =
            new \ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation(
                'publisher',
                $field
            );
        $termsAggregation->addParameter('size', 1000);
        $termsAggregation->addParameter('order', array('_key' => 'asc'));
        $this->search->addAggregation($termsAggregation);
    }

    public function addTopDatabaseFacet(): void
    {
        $bool_top_database = new \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery();

        $termQueryIsTopDatabaseOfSubjects = new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery("subjects.is_top_database", true);
        $termQueryIsTopDatabaseOfCollections= new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery("collections.is_top_database", true);
        
        $bool_top_database->add($termQueryIsTopDatabaseOfSubjects, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);
        $bool_top_database->add($termQueryIsTopDatabaseOfCollections, \ONGR\ElasticsearchDSL\Query\Compound\BoolQuery::SHOULD);

        $filterAggregation =
            new \ONGR\ElasticsearchDSL\Aggregation\Bucketing\FilterAggregation('top_databases', $bool_top_database);

        $this->search->addAggregation($filterAggregation);
    }

    public function addTopDatabaseFacetForCollection(): void
    {
        $termQueryIsTopDatabaseOfCollections= new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery("collections.is_top_database", true);

        $filterAggregation =
            new \ONGR\ElasticsearchDSL\Aggregation\Bucketing\FilterAggregation('top_databases', $termQueryIsTopDatabaseOfCollections);

        $this->search->addAggregation($filterAggregation);
    }

    public function addTopDatabaseFacetForSubject(): void
    {
        $termQueryIsTopDatabaseOfSubjects = new \ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery("subjects.is_top_database", true);

        $filterAggregation =
            new \ONGR\ElasticsearchDSL\Aggregation\Bucketing\FilterAggregation('top_databases', $termQueryIsTopDatabaseOfSubjects);

        $this->search->addAggregation($filterAggregation);
    }

    public function addDateFacet($from = null, $to = null): void
    {
        $dateRangeAggregation = new \ONGR\ElasticsearchDSL\Aggregation\Bucketing\DateRangeAggregation('from_range');
        $dateRangeAggregation->setField('publication_time_start');
        $dateRangeAggregation->setFormat('yyyy-MM-DD');
        $dateRangeAggregation->addRange(null, $to);
        $dateRangeAggregation->addRange($from, null);

        $this->search->addAggregation($dateRangeAggregation);

        $dateRangeAggregation = new \ONGR\ElasticsearchDSL\Aggregation\Bucketing\DateRangeAggregation('to_range');
        $dateRangeAggregation->setField('publication_time_end');
        $dateRangeAggregation->setFormat('yyyy-MM-DD');
        $dateRangeAggregation->addRange(null, $to);
        $dateRangeAggregation->addRange($from, null);

        $this->search->addAggregation($dateRangeAggregation);
    }

    public function setSize($size): void
    {
        $this->search->setSize($size);
    }

    public function setFrom($from): void
    {
        $this->search->setFrom($from);
    }

    public function sortAlphabetically(): void
    {
        $this->search->addSort(new \ONGR\ElasticsearchDSL\Sort\FieldSort('resource_title.keyword', 'ASC'));
    }

    public function sortByRelevance(): void 
    {
        $this->search->addSort(new \ONGR\ElasticsearchDSL\Sort\FieldSort('_score', 'desc')); 
    }

    public function sortByTopDatabasesOrder(): void
    {
        //$this->search->addSort(new \ONGR\ElasticsearchDSL\Sort\FieldSort('subjects.sort_order', 'ASC'));
        
        // 1. Sort by is_top_database (true first, then false or missing)
        $isTopDatabaseSort = new \ONGR\ElasticsearchDSL\Sort\FieldSort('is_top_database', 'desc', ['missing' => '_last']);
        $this->search->addSort($isTopDatabaseSort);

        // 2. Then sort alphabetically by the "resource_title" field
        $this->search->addSort(new \ONGR\ElasticsearchDSL\Sort\FieldSort('resource_title.keyword', 'ASC'));
    }

    /**
     * See https://www.elastic.co/guide/en/elasticsearch/reference/current/search-fields.html#source-filtering
     *
     * Example:
     * $search_client->enableSourceFiltering([
     *     'includes' => ['resource_id', 'resource_title', 'subjects'],
     *     'excludes' => ['*.is_visible', '*.resource_ids', 'subjects.is_top_database'],
     * ]);
 * @param $sourceFilters
     * @return void
     */
    public function enableSourceFiltering($sourceFilters): void
    {
        $this->sourceFilters = $sourceFilters;
        $this->search->setSource($this->sourceFilters);
    }

    public function showOnlyVisibleResources($organizationId): void
    {
        $bool = "and";
        
        if ($organizationId) {
            $this->addQuery("is_visible_locally", true, $bool);
        } else {
            $this->addQuery("is_visible", true, $bool);
        }
    }

    public function showOnlyFreeResources(): void
    {
        $this->addQuery("is_free", true, "and");
    }

    public function searchViaDsl($explain = false)
    {   
        if ($this->bool_for_filtered_subject_search->getQueries() > 0) {
            $this->search->addQuery($this->bool_for_filtered_subject_search);
        }

        if ($this->bool_for_filtered_authors_search->getQueries() > 0) {
            $this->search->addQuery($this->bool_for_filtered_authors_search);
        }        

        if ($this->bool_for_filtered_all_subject_search->getQueries() > 0) {
            $this->search->addQuery($this->bool_for_filtered_all_subject_search);
        }
        
        if ($this->bool_for_filtered_publishers_search->getQueries() > 0) {
            $this->search->addQuery($this->bool_for_filtered_publishers_search);
        }         

        if ($this->bool_for_filtered_country_search->getQueries() > 0) {
            $this->search->addQuery($this->bool_for_filtered_country_search);
        }

        if ($this->bool_for_filtered_keyword_search->getQueries() > 0) {
            $this->search->addQuery($this->bool_for_filtered_keyword_search);
        }

        if ($this->bool_for_filtered_type_search->getQueries() > 0) {
            $this->search->addQuery($this->bool_for_filtered_type_search);
        }

        if ($this->bool_for_filtered_license_type_search->getQueries() > 0) {
            $this->search->addQuery($this->bool_for_filtered_license_type_search);
        }

        if ($this->bool_for_filtered_license_form_search->getQueries() > 0) {
            $this->search->addQuery($this->bool_for_filtered_license_form_search);
        }

        if ($this->bool_for_filtered_all_license_form_search->getQueries() > 0) {
            $this->search->addQuery($this->bool_for_filtered_all_license_form_search);
        }        

        if ($this->bool_for_filtered_access_form_search->getQueries() > 0) {
            $this->search->addQuery($this->bool_for_filtered_access_form_search);
        }

        if ($this->bool_for_filtered_access_label_search->getQueries() > 0) {
            $this->search->addQuery($this->bool_for_filtered_access_label_search);
        }

        if ($this->bool_for_filtered_publication_form_search->getQueries() > 0) {
            $this->search->addQuery($this->bool_for_filtered_publication_form_search);
        }

        if ($this->bool_for_filtered_all_publication_form_search->getQueries() > 0) {
            $this->search->addQuery($this->bool_for_filtered_all_publication_form_search);
        }        

        $index_name = $this->organization_id ? mb_strtolower($this->organization_id) . "_index" : "dbis_index";

        // add search highlights
        // Create a highlight object
        $highlight = new \ONGR\ElasticsearchDSL\Highlight\Highlight();

        // Add a wildcard to highlight all fields ('*' means all fields)
        $highlight->addField('*');

        // encode special chars within highlighted content
        $highlight->addParameter('encoder', "html");

        // Optionally, you can specify highlighting options like number_of_fragments, fragment_size, etc.
        $highlight->addParameter('number_of_fragments', 3);  // Adjust the number of fragments to display
        $highlight->addParameter('fragment_size', 40);      // Adjust the size of the fragments
        $highlight->addParameter('pre_tags', ['<strong>']);  // Custom pre-tag for highlighted terms
        $highlight->addParameter('post_tags', ['</strong>']);  // Custom post-tag for highlighted terms

        $this->search->addHighlight($highlight);

        $queryArray = $this->search->toArray();
        $queryArray['track_total_hits'] = true;

        $params = [
            'index' => $index_name,
            'body' => $queryArray
        ];

        $explain = false;

        if ($explain) {
            echo('<details class="p-2">');
            echo('<summary>Suchanfrage</summary>');
            echo('<pre>');
            print_r(json_encode($this->arrayFilterRecursive($params), JSON_PRETTY_PRINT));
            echo('</pre>');
            echo('</details>');
            echo('</br>');
        }

        $results = $this->client->search($params);

        $this->reinitializeBoolQuery();

        if ($explain) {
            echo('<details class="p-2">');
            echo('<summary>Ergebnisse</summary>');
            echo('<pre>');
            print_r($this->arrayFilterRecursive($results->asArray()));
            echo('</pre>');
            echo('</details>');
            echo('</br>');
        }

        $resultsArray = $results->asArray();

        // $this->transformResults($resultsArray);

        return $resultsArray;
    }

    /**
     * Extracts the actual search results from ElasticSearch's response data structure.
     * @param array $results Supply the value from searchViaDsl() here.
     * @return array An array that contains the extracted _source fields from the searchViaDsl() result.
     */
    public function transformResults(array $results): array
    {
        $transformed_results = [];

        if ($this->sourceFilters) {
            // if _source filtering was used, do no transforms and just extract _source
            // (see also method enableSourceFiltering())
            foreach ($results['hits']['hits'] as &$result) {
                $source = $result['_source'];
                if (count($this->sourceFilters) == 1) {
                    $transformed_results[] = $source[$this->sourceFilters[0]];
                } else {
                    $transformed_results[] = $source;
                }
            }
        } else {
            // this code runs when no _source filtering was used
            foreach ($results['hits']['hits'] as &$result) {
                $result = $result['_source'];
                $result['id'] = $result['resource_id'];
                $result['title'] = $result['resource_title'];

                $transformed_results[] = $result;
            }
        }
        return $transformed_results;
    }

    private function arrayFilterRecursive($haystack)
    {
        foreach ($haystack as $key => $value) {
            if (is_array($value)) {
                $haystack[$key] = $this->arrayFilterRecursive($value);
            }

            if (empty($haystack[$key])) {
                unset($haystack[$key]);
            }
        }

        return $haystack;
    }

    private function escapeElasticReservedChars($q) {
        // Reserverd characters are: + - = && || > < ! ( ) { } [ ] ^ " ~ * ? : \ / but not the bool &, | ones and truncation * and ?

        $reservedCharactersExceptBoolAndTrunc = [
            '+', '=', '>', '-', '<', '(', ')', '{', '}', '[', ']', '^', '"', '~', ':', '\\', '/', '!'
        ];

        $allowedWhiteList = [
            '+', '|', '-', '"', '*', '~', '(', ')' 
        ];        

        // Escape each special character
        foreach ($reservedCharactersExceptBoolAndTrunc as $char) {
            if(in_array($char,$allowedWhiteList) == false){
                $input = str_replace($char, '\\' . $char, $q);
            }
        }

        return $input;
    }
}
