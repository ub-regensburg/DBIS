<?php

declare(strict_types=1);

namespace App\Infrastructure\Resources;

use PDO;

/**
 * GetResourcesQuery
 *
 * GetResources is currently the most complex query in all of DBIS. This class
 * helps build the complex query string bit by bit
 *
 */
class GetResourcesQuery
{
    private $pdo;

    // This is the result set to be searched
    private string $findQuery = "";

    // Filter applied to the resultset of findquery
    private string $filter = "";
    // Joins needed for the findquery
    private string $find_joins = "";



    private string $select = "";
    private string $join = "";
    private string $pagination = "";
    private string $sort = "";
    private int $sort_by = NO_SORTING;

    // whether the query will count total results (may take longer)
    private bool $isCounting = false;

    private bool $isExplaining = false;

    private ?string $orgId;

    private array $params = [];

    public function __construct(PDO $pdo, $orgId = null, $options = [])
    {
        if ($options['explain'] ?? false) {
            $this->isExplaining = true;
        }

        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true); // to use params like :q multiple times
        $this->params["orgId"] = $orgId;
        $this->orgId = $orgId;

        $this->findQuery = $this->getFindQuery();
        // initialize filter
        $this->filter = " TRUE ";

        // initialize select
        $this->select = "SELECT * ";
    }

    public function execute(): array
    {
        // The execute query runs in two stages:
        // 1. Filter, sort and query tables
        // 2. Fetch the full objects
        $result = [];
        $filter = "";


        $filter = $this->getFullFetchSql();


        // pack the complete result as json -
        // this way, we just need to decode the complete block,
        // instead of selected parts (e.g. authors, subjects etc.)
        $filter = "SELECT to_json(results.*) as data FROM (" . $filter . ") as results";

        if ($this->isExplaining) {
            $sql = "EXPLAIN ANALYZE $filter";
            $statement = $this->pdo->prepare($sql);

            $statement->execute($this->params);
            $result['analyze'] = $statement->fetchAll(PDO::FETCH_ASSOC);
        }

        if ($this->isCounting) {
            $result['total_nr'] = $this->countEntries();
        }

        $sql = "$filter";

        $statement = $this->pdo->prepare($sql);

        $statement->execute($this->params);

        if ($this->isExplaining) {
            print("</br>" . $statement->debugDumpParams() . "</br>");
        }

        $result['results'] = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    /**
     * Fetch the complete dataset:
     * - this may be very slow, especially for big result sets
     *
     * Use Cases:
     * - Get single Database en detail
     * - Full export of dbis databases
     *
     * @return string
     */
    private function getFullFetchSql(): string
    {
        $fetch = $this->getFetchQuery();
        $this->findQuery = $this->getFindQuery();

        $alternative_titles = "";
        $resource_localisation = "";

        /*
         * If results should be sorted by relevance, some things need to be done:
         * - *_tsvector columns store ts_vectors for searchable columns.
         * - setWeight can be used to weight the columns differently
         *  (see https://www.postgresql.org/docs/current/textsearch-features.html).
         * - ts_rank_cd computes a ranking with the help of the tokenized columns and the query.
         *  32 is used to scale the output between 0 and 1.
         */
        if ($this->sort_by == 0) {
            $alternative_titles = ", alternative_titles.resource ";

            if ($this->params["orgId"]) {
                $resource_localisation = ", resource_localisation.resource, resource_localisation.organisation ";
            }
        }

        $sql = "SELECT resource.*,"
            . " COALESCE(jsonb_agg(DISTINCT resource_localisation.*) FILTER (WHERE "
            . "     resource_localisation.organisation = :orgId), '[]') as resource_localisation, "
            . " COALESCE(jsonb_agg(DISTINCT external_resource_id.*) FILTER 
                    (WHERE external_resource_id.id IS NOT NULL), '[]') as external_resource_id, "
            . " COALESCE(jsonb_agg(DISTINCT resource_api.*) FILTER 
                    (WHERE resource_api.id IS NOT NULL), '[]') as api_urls, "
            . " COALESCE(jsonb_agg(DISTINCT licenses.*) FILTER (WHERE licenses.id IS NOT NULL), '[]') as licenses, "
            . " COALESCE(jsonb_agg(DISTINCT keywords.*) FILTER (WHERE keywords.id IS NOT NULL), '[]') as keywords, "
            . " COALESCE(jsonb_agg(DISTINCT keyword_for_resource.*) FILTER (WHERE keyword_for_resource.keyword "
            . "     IS NOT NULL AND (keyword_for_resource.organisation IS NULL "
            . "     OR keyword_for_resource.organisation = :orgId)), '[]') as keywords_for_resource,"
            . " COALESCE(jsonb_agg(DISTINCT subjects.*) FILTER (WHERE subjects.id IS NOT NULL), '[]') as subjects, "
            . " COALESCE(jsonb_agg(DISTINCT subject_for_resource.*) FILTER (WHERE subject_for_resource.subject "
            . "     IS NOT NULL AND (subject_for_resource.organisation IS NULL "
            . "     OR subject_for_resource.organisation = :orgId)), '[]') as subjects_for_resource,"
            . " COALESCE(jsonb_agg(DISTINCT authors.*) FILTER (WHERE authors.id IS NOT NULL), '[]') as authors, "
            . " COALESCE(jsonb_agg(DISTINCT author_for_resource.*) FILTER (WHERE author_for_resource.author "
            . "     IS NOT NULL AND (author_for_resource.organisation IS NULL "
            . "     OR author_for_resource.organisation = :orgId)), '[]') as authors_for_resource,"
            . " COALESCE(jsonb_agg(DISTINCT country.*) FILTER (WHERE country.id IS NOT NULL), '[]') as countries,"
            . " COALESCE(jsonb_agg(DISTINCT country_for_resource.*) FILTER (WHERE country_for_resource.country "
            . "     IS NOT NULL AND (country_for_resource.organisation IS NULL "
            . "     OR country_for_resource.organisation = :orgId)), '[]') as countries_for_resource,"
            . " COALESCE(jsonb_agg(DISTINCT update_frequency.*) FILTER (WHERE update_frequency.id IS NOT NULL), "
            . "     '[]') as update_frequencies, "
            . " COALESCE(jsonb_agg(DISTINCT alternative_titles.*) FILTER (WHERE alternative_titles.id IS NOT NULL),"
            . "     '[]') as alternative_titles, "
            . " COALESCE(jsonb_agg(DISTINCT resource_types.*) "
            . "     FILTER (WHERE resource_types.id IS NOT NULL), '[]') as resource_types, "
            . " COALESCE(jsonb_agg(DISTINCT resource_type_for_resource.*) FILTER (WHERE "
            . "     resource_type_for_resource.resource_type IS NOT NULL AND "
            . "     (resource_type_for_resource.organisation IS NULL OR "
            . "     resource_type_for_resource.organisation = :orgId)), '[]') as "
            . "     resource_types_for_resource,"
            . " COALESCE(jsonb_agg(DISTINCT top_resource_for_collection.*) "
            . "     FILTER (WHERE top_resource_for_collection.id IS NOT NULL), '[]') "
            . "         as top_resource_entries_of_collections, "
            . " COALESCE(jsonb_agg(DISTINCT top_resource_entries_for_subject.*) "
            . "     FILTER (WHERE top_resource_entries_for_subject.id IS NOT NULL), '[]') "
            . "         as top_resource_entries "
            . " FROM ($this->findQuery $this->filter GROUP BY resource.id) as ids $fetch "
            . " GROUP BY ids.id, resource.id $alternative_titles $resource_localisation "
            . " $this->sort $this->pagination";
        
        return $sql;
    }

    private function countEntries(): int
    {
        $countParams = array_merge(array(), $this->params);
        unset($countParams['offset']);
        unset($countParams['limit']);
        unset($countParams['lang']);

        $countSql = "SELECT COUNT(ids.*) as total_nr "
            . "FROM ($this->findQuery $this->filter "
            . "GROUP BY resource.id) as ids";

        $countStm = $this->pdo->prepare($countSql);
        $countStm->execute($countParams);
        $countResult = $countStm->fetch(PDO::FETCH_ASSOC);
        return $countResult['total_nr'];
    }

    public function setPagniation(int $offset, int $limit): void
    {
        if ($offset) {
            $this->pagination .= " OFFSET :offset ";
            $this->params['offset'] = $offset;
        }

        if ($limit) {
            $this->pagination .= " LIMIT :limit ";
            $this->params['limit'] = $limit;
        }
    }

    public function setSort(?int $sort_by, string $lang): void
    {
        $this->sort_by = $sort_by;

        switch ($sort_by) {
            case RELEVANCE_SORTING:
                // relevance
                // $this->sort .= " ORDER BY rank DESC ";
                break;
            case ALPHABETICAL_SORTING:
                // alphabetically
                $this->sort .= " ORDER BY resource.title ASC ";
                $this->params['lang'] = $lang;
                break;
        }
    }

    /**
     * Count total results
     * @return void
     */
    public function addResultCount(): void
    {
        $this->isCounting = true;
    }

    public function addGetById(int $id): void
    {
        $this->filter .= " AND resource.id = :id::integer ";
        $this->params["id"] = $id;
    }

    public function addFilterSubjects(array $subjectIds): void
    {
        $filter = "";
        foreach ($subjectIds as $index => $subjectId) {
            $name = "subject_" . $index;
            if ($index > 0) {
                $filter .= " OR ";
            }
            $filter .= "subject_for_resource.subject=:$name "
                . " AND (subject_for_resource.organisation IS NULL OR subject_for_resource.organisation = :orgId) ";
            $this->params[$name] = $subjectId;
        }
        $this->filter .= " AND ($filter) ";
        $this->find_joins .= " LEFT JOIN subject_for_resource ON subject_for_resource.resource = resource.id ";
    }

    public function addFilterCollections(array $collectionIds): void
    {
        $filter = "";
        // What happens here?
        // - Iterate all queried ids
        // -- Check, whether any of them is contained within any of the
        //    collection_ids of the resource
        foreach ($collectionIds as $index => $collectionId) {
            $name = "collection_" . $index;
            if ($index > 0) {
                $filter .= " OR ";
            }
            $filter .= " resource_for_collection.collection = :$name ";
            $this->params[$name] = $collectionId;
        }
        $this->filter .= " AND ($filter) ";
        $this->find_joins .= " LEFT JOIN resource_for_collection "
            . " ON resource_for_collection.resource = resource.id ";
    }

    public function addFilterResourceTypes(array $resourceTypes): void
    {
        $filter = "";
        // What happens here?
        // - Iterate all queried ids
        // -- Check, whether any of them is contained within any of the
        //    resource_type_ids of the resource
        foreach ($resourceTypes as $index => $resourceTypeId) {
            $name = "resource_type_" . $index;
            if ($index > 0) {
                $filter .= " OR ";
            }
            $filter .= " resource_type_for_resource.resource_type = :$name "
                . " AND (resource_type_for_resource.organisation IS NULL OR "
                . " resource_type_for_resource.organisation = :orgId) ";
            $this->params[$name] = $resourceTypeId;
        }
        $this->filter .= " AND ($filter) ";
        $this->find_joins .= " LEFT JOIN resource_type_for_resource "
            . " ON resource.id = resource_type_for_resource.resource ";
    }

    public function addFilterKeywords(array $keywordIds): void
    {
        $filter = "";
        // What happens here?
        // - Iterate all queried ids
        // -- Check, whether any of them is contained within any of the
        //    keyword_ids of the resource
        foreach ($keywordIds as $index => $keywordId) {
            $name = "kw_" . $index;
            if ($index > 0) {
                $filter .= " OR ";
            }
            $filter .= " keyword_for_resource.keyword = :$name "
                . " AND (keyword_for_resource.organisation IS NULL OR keyword_for_resource.organisation = :orgId) ";

            $this->params[$name] = $keywordId;
        }
        $this->filter .= " AND ($filter) ";
        $this->find_joins .= " LEFT JOIN keyword_for_resource ON keyword_for_resource.resource = resource.id ";
    }

    public function addFilterHosts(array $hostIds): void
    {
        $filter = "";
        // What happens here?
        // - Iterate all queried ids
        // -- Check, whether any of them is contained within any of the
        //    host_ids of the resource
        foreach ($hostIds as $index => $hostId) {
            $name = "host_" . $index;
            if ($index > 0) {
                $filter .= " OR ";
            }
            $filter .= " accesses.host = :$name  ";
            $this->params[$name] = $hostId;
        }
        $this->filter .= " AND ($filter) ";
    }

    public function addFilterAuthors(array $authorIds): void
    {
        $filter = "";
        // What happens here?
        // - Iterate all queried ids
        // -- Check, whether any of them is contained within any of the
        //    author_ids of the resource
        foreach ($authorIds as $index => $authorId) {
            $name = "author_" . $index;
            if ($index > 0) {
                $filter .= " OR ";
            }
            $filter .= " author_for_resource.author = :$name "
                . " AND (author_for_resource.organisation IS NULL OR author_for_resource.organisation = :orgId) ";
            $this->params[$name] = $authorId;
        }
        $this->filter .= " AND ($filter) ";
        $this->find_joins .= " LEFT JOIN author_for_resource "
            . " ON resource.id = author_for_resource.resource ";
    }

    public function addFilterCountries(array $countryIds): void
    {
        $filter = "";
        // What happens here?
        // - Iterate all queried ids
        // -- Check, whether any of them is contained within any of the
        //    country_ids of the resource
        foreach ($countryIds as $index => $countryId) {
            $name = "kw_" . $index;
            if ($index > 0) {
                $filter .= " OR ";
            }
            $filter .= " country_for_resource.country = :$name "
                . " AND (country_for_resource.organisation IS NULL OR country_for_resource.organisation = :orgId) ";

            $this->params[$name] = $countryId;
        }
        $this->filter .= " AND ($filter) ";
        $this->find_joins .= " LEFT JOIN country_for_resource ON country_for_resource.resource = resource.id ";
    }

    public function addFilterReportTime(array $reportTime): void
    {
        if ($reportTime["start"] ?? false) {
            $this->filter .= " AND date_part('year', resource.report_time_start::date) >= :report_time_start ";
            $this->params["report_time_start"] = $reportTime["start"];
        }
        if ($reportTime["end"] ?? false) {
            $this->filter .= " AND date_part('year', resource.report_time_end::date) <= :report_time_end ";
            $this->params["report_time_end"] = $reportTime["end"];
        }
    }

    public function addFilterPublicationTime(array $pubTime): void
    {
        if ($pubTime["start"] ?? false) {
            $this->filter .= " AND date_part('year', resource.publication_time_start::date) >= :pub_time_start ";
            $this->params["pub_time_start"] = $pubTime["start"];
        }
        if ($pubTime["end"] ?? false) {
            $this->filter .= " AND date_part('year', resource.publication_time_end::date) <= :pub_time_end ";
            $this->params["pub_time_end"] = $pubTime["end"];
        }
    }

    public function addFilterVisibility(bool $showHiddenEntries): void
    {
        if (!$showHiddenEntries) {
            $filter = " resource.is_visible = true ";
            $this->filter .= " AND ($filter) ";
        }
    }

    public function addFields(array $fields): void
    {
        foreach ($fields as $i => $field) {
            // Since and/or/not can not be prepared, only allow one of the three legal values
            $bool = in_array($field['bool'], ["not", "and", "or"]) ? ($field['bool'] == "not")
                    ? "and not" : $field['bool'] : "and";

            if (isset($field['search']) && trim($field['search']) != "") {
                if ($field['field'] == "title") {
                    $this->filter .= " $bool ("
                        . "LOWER(resource.title) LIKE LOWER(CONCAT('%', :search$i::text, '%')) "
                        . "OR LOWER(resource.title) LIKE LOWER(CONCAT('%', :search$i::text, '%'))) ";
                } elseif ($field['field'] == "description") {
                    $this->filter .= " $bool ("
                        . "LOWER(resource.description->>'de') LIKE LOWER(CONCAT('%', :search$i::text, '%')) "
                        . "OR LOWER(resource.description_short->>'de') LIKE LOWER(CONCAT('%', :search$i::text, '%')) "
                        . "OR LOWER(resource.description->>'en') LIKE LOWER(CONCAT('%', :search$i::text, '%')) "
                        . "OR LOWER(resource.description_short->>'en') LIKE LOWER(CONCAT('%', :search$i::text, '%'))) ";
                }
                $this->params["search$i"] = $field['search'];
            }
        }
    }

    public function addAvailiabilityFilter(array $filterAvailability)
    {
        // We can query jsonb (such as the aggregated licenses) with a
        // special syntax!
        //
        // This is just an example:
        // - search in licenses jsonb-array
        // - the json object defines the query
        // -- find all licenses with type_id == 2
        // -- find all licenses, where access_type_ids contains the value 1
        $currFilter = "";
        $filterObjects = [];
        $isIncludingUnavailableResources = false;
        foreach ($filterAvailability as $i => $filter) {
            $filterObjects = [];
            $licenseType = intval($filter['license'] ?? null);
            $accessType = intval($filter['access'] ?? null);

            // Handle including unavailable databases here
            // This also means (kind of) disabling the other filters
            if (isset($filter['access']) && $filter['access'] == "none") {
                $isIncludingUnavailableResources = true;
                continue;
            }

            if ($i > 0 && $currFilter != "") {
                $currFilter .= " OR ";
            }

            if ($licenseType) {
                $name = "licensetype_" . $i;
                array_push($filterObjects, " licenses.type = :$name ");
                array_push($filterObjects, " accesses.id IS NOT NULL ");
                $this->params[$name] = $licenseType;
            }

            if ($licenseType && $licenseType != 1 && $this->orgId) {
                array_push($filterObjects, " licenses.organisation = :orgId ");
            }

            if ($accessType) {
                $name = "accesstype_" . $i;
                array_push($filterObjects, " accesses.type = :$name ");
                $this->params[$name] = $accessType;
            }

            $mergedFilters = implode(" AND ", $filterObjects);
            $currFilter .= " $mergedFilters ";
        }

        if ($isIncludingUnavailableResources) {
            if ($currFilter != "") {
                $currFilter .= " OR ";
            }
            $currFilter .= " licenses.id IS NULL ";
        }

        $this->filter .= " AND ($currFilter) ";
    }

    /**
     * This is a "minimal" result set with just the most imported
     * relations merged. It just returns the ids of matching
     * entries.
     *
     * It can be used for sorting, filtering, searching etc.
     */
    private function getFindQuery(): string
    {
        // Lessons Learned:
        // - Model M:N relations as LEFT JOIN+INNER JOIN constellations!
        //      Using two nested LEFT JOINs will result in terrible performance!
        //
        $sql = " SELECT resource.id as id "
            . " FROM resource "
            . " "
            . " LEFT JOIN resource_localisation "
            . "     ON resource_localisation.resource = resource.id "
            . "     AND resource_localisation.organisation = :orgId "
            . " "
            . " LEFT JOIN alternative_title as alternative_titles "
            . "     ON alternative_titles.resource = resource.id ";

        $sql .= $this->find_joins;

        $sql .= " "
            . " LEFT JOIN top_resource_for_subject "
            . "     ON top_resource_for_subject.resource = resource.id "
            . "     AND (top_resource_for_subject.organization IS NULL "
            . "         OR top_resource_for_subject.organization = :orgId) "
            . " "
            . " LEFT JOIN top_resource_for_collection "
            . "     ON top_resource_for_collection.resource = resource.id "
            . "     AND (top_resource_for_collection.organization IS NULL "
            .  "        OR top_resource_for_collection.organization = :orgId) "
            . " "
            . " WHERE ";

        return $sql;
    }

    private function getFetchQuery(): string
    {
        // Lessons Learned:
        // - Model M:N relations as LEFT JOIN+INNER JOIN constellations!
        //      Using two nested LEFT JOINs will result in terrible performance!
        //
        $sql =  <<<EOD
                LEFT JOIN resource ON resource.id = ids.id
                
                LEFT JOIN (
                    SELECT license.id as license_id,
                        license.*,
                        TO_JSONB(license_type.*) as license_type,
                        TO_JSONB(license_form.*) as form,
                        JSONB_AGG(accesses.*) as accesses,
                        COUNT(accesses.id) as n_accesses,
                        TO_JSONB(e_vendor.*) AS vendor,
                        TO_JSONB(e_publisher.*) AS publisher,
                        ARRAY_AGG(license_for_organization.organization) as organizations
                    FROM license
                    LEFT JOIN license_type ON license_type.id = license.type
                    LEFT JOIN license_form ON license_form.id = license.form
                    LEFT JOIN enterprise as e_vendor ON e_vendor.id = license.vendor
                    LEFT JOIN enterprise as e_publisher ON e_publisher.id = license.publisher
                    LEFT JOIN license_for_organization on license_for_organization.license = license.id
                    LEFT JOIN publication_form ON publication_form.id = license.publication_form
                    LEFT JOIN (
                        SELECT 
                            TO_JSONB(access_type.*) as access_type,
                            TO_JSONB(host.*) as access_host,
                            access.*
                        FROM access
                        LEFT JOIN access_type ON access_type.id = access.type
                        LEFT JOIN host ON host.id = access.host
                    ) AS accesses ON accesses.license = license.id
                    WHERE (license_for_organization.organization = :orgId OR license_type.is_global IS TRUE)
                    GROUP BY license.id, license_type.id, license_form.id, license_for_organization.organization, 
                        e_vendor.*, e_publisher.*
                ) AS licenses ON licenses.resource = resource.id

                LEFT JOIN resource_localisation 
                    ON resource_localisation.resource = resource.id
                    AND resource_localisation.organisation = :orgId
                    
                LEFT JOIN external_resource_id
                    ON external_resource_id.resource = resource.id
                    
                LEFT JOIN resource_api
                    ON resource_api.resource = resource.id

                LEFT JOIN keyword_for_resource ON keyword_for_resource.resource = resource.id
                    LEFT JOIN keyword AS keywords ON keyword_for_resource.keyword = keywords.id

                LEFT JOIN subject_for_resource ON subject_for_resource.resource = resource.id
                    LEFT JOIN subject AS subjects ON subject_for_resource.subject = subjects.id

                LEFT JOIN resource_type_for_resource ON resource.id = resource_type_for_resource.resource
                    LEFT JOIN resource_type as resource_types 
                        ON resource_types.id = resource_type_for_resource.resource_type

                LEFT JOIN author_for_resource ON resource.id = author_for_resource.resource
                    LEFT JOIN author as authors ON authors.id = author_for_resource.author    

                LEFT JOIN update_frequency 
                    ON resource.update_frequency = update_frequency.id
                    OR resource_localisation.update_frequency = update_frequency.id

                LEFT JOIN alternative_title as alternative_titles 
                    ON alternative_titles.resource = resource.id                
                                
                LEFT JOIN country_for_resource
                    ON resource.id = country_for_resource.resource
                        LEFT JOIN country
                            ON country.id = country_for_resource.country
                
                LEFT JOIN (
                    SELECT 
                        top_resource_for_subject.*,
                        TO_JSONB(subject.*) as top_subject
                    FROM top_resource_for_subject
                    LEFT JOIN subject 
                        ON subject.id = top_resource_for_subject.subject
                ) AS top_resource_entries_for_subject
                    ON top_resource_entries_for_subject.resource = resource.id
                        AND (top_resource_entries_for_subject.organization = :orgId 
                            OR top_resource_entries_for_subject.organization IS NULL)

                LEFT JOIN top_resource_for_collection 
                    ON top_resource_for_collection.resource = resource.id
                    AND (top_resource_for_collection.organization IS NULL
                        OR top_resource_for_collection.organization = :orgId)
        EOD;
        return $sql;
    }
}
