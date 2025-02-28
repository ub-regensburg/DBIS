<?php

declare(strict_types=1);

namespace App\Infrastructure\Organizations;

use App\Domain\Organizations\Entities\Link;
use App\Domain\Organizations\Entities\Organization;
use App\Domain\Organizations\Exceptions\OrganizationWithDbisIdNotExistingException;
use App\Domain\Organizations\Repositories\OrganizationRepository;
use App\Domain\Organizations\Entities\ExternalOrganizationIdentifier;
use App\Domain\Organizations\Entities\ExternalOrganizationIdentifierNamespace;
use App\Domain\Organizations\Entities\DbisView;
use App\Domain\Organizations\Entities\DbisSettings;
use PDO;
use App\Infrastructure\Shared\Util;
use DateTime;

/**
 * UBROrganizationRepository
 *
 * Repository for Organizations in UBR Common Database
 *
 */
class UBROrganizationRepository implements OrganizationRepository
{
    private $pdo;
    private $logoLocation;

    public function __construct(PDO $pdo, string $logoLocation)
    {
        $this->pdo = $pdo;
        $this->logoLocation = $logoLocation;
    }

    //
    // IO
    //

    /**
     * Create assoc array for pdo
     * @param Organization $organization
     */
    private function entityToAssoc(Organization $organization)
    {
        $params = [
            ":ubr_id" => $organization->getUbrId(),
            ":dbis_id" => $organization->getDbisId() ?: null,
            ":name" => $organization->getName()["de"],
            ":name_en" => $organization->getName()["en"],
            ":city" => $organization->getCity()["de"],
            ":city_en" => $organization->getCity()["en"],
            ":zipcode" => $organization->getZipcode(),
            ":country_code" => $organization->getCountryCode(),
            ":color" => $organization->getColor(),
            ":is_fid" => $organization->getIsFID() ? 1 : 0,
            ":is_consortium" => $organization->getIsConsortium() ? 1 : 0,
            ":is_kfl" => $organization->getIsKfL() ? 1 : 0
        ];

        $region = $organization->getRegion();
        $params[":region"] = $region ? $region["de"] : null;
        $params[":region_en"] = $region ? $region["en"] : null;

        $street = $organization->getAdress();
        $params[":street"] = $street ? $street["de"] : null;
        $params[":street_en"] = $street ? $street["en"] : null;

        $homepage = $organization->getHomepage();
        $params[":homepage"] = $homepage ? $homepage["de"] : null;
        $params[":homepage_en"] = $homepage ? $homepage["en"] : null;

        $contact = $organization->getContact();
        $params[":contact_email"] = $contact ? $contact : null;

        $iconPath = $organization->getIconPath();
        $params[":iconpath"] = $iconPath ? $iconPath : null;

        return $params;
    }

    private function assocToEntity($entry): Organization
    {
        $org = new Organization(
            $entry['ubr_id'],
            [
                'de' => $entry['name'],
                'en' => $entry['name_en']
            ],
            $entry['country_code']
        );

        $org->setPublicIconFolder($this->logoLocation);

        if (array_key_exists('zipcode', $entry) && $entry['zipcode']) {
            $org->setZipcode($entry['zipcode']);
        }
        if (array_key_exists('contact_mail', $entry) && $entry['contact_mail']) {
            $org->setContact($entry['contact_mail']);
        }
        if (array_key_exists('contact_email', $entry) && $entry['contact_email']) {
            $org->setContact($entry['contact_email']);
        }
        if (array_key_exists('region', $entry) && $entry['region']) {
            $org->setRegion([
                "de" => $entry['region'],
                "en" => $entry['region_en']
            ]);
        }
        if (array_key_exists('city', $entry) && $entry['city']) {
            $org->setCity([
                "de" => $entry['city'],
                "en" => $entry['city_en']
            ]);
        }
        if (array_key_exists('street', $entry) && $entry['street']) {
            $org->setAdress([
                "de" => $entry['street'],
                "en" => $entry['street_en']
            ]);
        }
        if (array_key_exists('homepage', $entry) && $entry['homepage']) {
            $org->setHomepage([
                "de" => $entry['homepage'],
                "en" => $entry['homepage_en']
            ]);
        }
        if (array_key_exists('dbis_id', $entry) && $entry['dbis_id']) {
            $org->setDbisId($entry['dbis_id']);
        }

        if (array_key_exists('iconpath', $entry) && $entry['iconpath']) {
            $org->setIconPath($entry['iconpath']);
        }

        if (array_key_exists('color', $entry) && $entry['color']) {
            $org->setColor($entry['color']);
        }

        if (array_key_exists('is_fid', $entry) && $entry['is_fid'] === 1) {
            $org->setIsFID(true);
        }

        if (array_key_exists('is_consortium', $entry) && $entry['is_consortium'] === 1) {
            $org->setIsConsortium(true);
        }

        if (array_key_exists('is_kfl', $entry) && $entry['is_kfl'] === 1) {
            $org->setIsKfL(true);
        }

        if (array_key_exists('created_at_date', $entry) && $entry['created_at_date']) {
            $org->setCreatedAtDate(
                date_create_from_format("Y-m-j", $entry['created_at_date'])
            );
        }

        $links = [];
        if (array_key_exists('linklist', $entry)) {
            $links = array_map(
                function ($a) {
                    return new Link(
                        [
                            "de" => $a['url']['url_de'],
                            "en" => $a['url']['url_en']
                        ],                        
                        [
                            "de" => $a['text']['text_de'],
                            "en" => $a['text']['text_en']
                        ]
                    );
                },
                $this->decodeLinkJsonArray($entry['linklist'])
            );
        }
        $org->setLinks($links);
        $externalIds = [];
        if(array_key_exists('extIds', $entry)) {
            $externalIds = array_map(
                function ($a) {
                    return new ExternalOrganizationIdentifier(
                        $a['identifier'],
                        new ExternalOrganizationIdentifierNamespace(
                            $a['namespace']['id'],
                            [
                            "de" => $a['namespace']['name_de'],
                            "en" => $a['namespace']['name_en']
                            ]
                        )
                    );
                },
                $this->decodeIdentifierJsonArray($entry['extIds'])
            );
        }
        $org->setExternalIds($externalIds);

        // Parse DBIS View
        if (array_key_exists('dbis_view_id', $entry) && $entry && strlen((string)$entry['dbis_view_id']) > 0) {
            $org->setDbisView(
                new DbisView()
            );
        }

        $dbisSettings = new DbisSettings();
        if (array_key_exists('autoaddflag', $entry) && $entry['autoaddflag'] === 1) {
            $dbisSettings->setAutoAddFlag(true);
        } else {
            $dbisSettings->setAutoAddFlag(false);
        }

        $org->setDbisSettings($dbisSettings);

        return $org;
    }

    private function decodeIdentifierJsonArray(string $json): array
    {
        $parsedAssoc = json_decode($json, true);
        // remove any empty objects
        return array_filter(
            $parsedAssoc,
            function ($a) {
                return ($a['identifier'] != null);
            }
        );
    }

    private function decodeLinkJsonArray(string $json): array
    {
        $parsedAssoc = json_decode($json, true);
        // remove any empty objects
        return array_filter(
            $parsedAssoc,
            function ($a) {
                return (count($a['url']) != 0 );
            }
        );
    }

    private function persistExternalIds(Organization $org): void
    {
        $sql = <<<EOD
                INSERT INTO ExternalOrganizationIdentifiers (organization, identifier, namespace)
                VALUES (:ubr_id, :identifier, :nsId);
                EOD;
        $this->clearExternalIds($org);
        foreach ($org->getExternalIds() as $externalIdentifier) {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                ":ubr_id" => $org->getUbrId(),
                ":identifier" => $externalIdentifier->getKey(),
                ":nsId" => $externalIdentifier->getNamespace()->getId()
            ]);
        }
    }

    private function clearExternalIds(Organization $org): void
    {
        $sql = <<<EOD
                DELETE FROM ExternalOrganizationIdentifiers
                    WHERE organization=:ubr_id;
                EOD;
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            ":ubr_id" => $org->getUbrId()
        ]);
    }

    private function persistDbisView(Organization $org): void
    {
        if ($org->getDbisView() == null) {
            $this->clearDbisView($org);
        } else {
            $this->clearDbisView($org);
            $sql = <<<EOD
                    INSERT INTO DbisViews (organization) VALUES (:ubr_id);
                    EOD;
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                ":ubr_id" => $org->getUbrId()
            ]);
        }
    }

    private function persistDbisSettings(Organization $org): void
    {
        $this->clearDbisSettings($org);

        $sql = <<<EOD
                INSERT INTO DbisSettings (ubr_id, autoaddflag) VALUES (:ubr_id, :autoaddflag);
                EOD;
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            ":ubr_id" => $org->getUbrId(),
            ":autoaddflag" => $org->getDbisSettings()->getAutoAddFlag() == true ? "1" : "0"
        ]);
    }

    private function clearDbisSettings(Organization $org): void
    {
        $sql = <<<EOD
                DELETE FROM DbisSettings WHERE ubr_id=:ubr_id
            EOD;
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            ":ubr_id" => $org->getUbrId()
        ]);
    }

    private function clearDbisView(Organization $org): void
    {
        $sql = <<<EOD
                DELETE FROM DbisViews WHERE organization=:ubr_id
            EOD;
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            ":ubr_id" => $org->getUbrId()
        ]);
    }

    private function clearLinks(Organization $org): void
    {
        $sql = <<<EOD
                DELETE FROM OrganisationLinks
                    WHERE ubr_id=:ubr_id;
                EOD;
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            ":ubr_id" => $org->getUbrId()
        ]);
    }

    private function persistLinks(Organization $org): void
    {
        $sql = <<<EOD
                INSERT INTO OrganisationLinks (ubr_id, url_de, url_en, product, text_de, text_en)
                VALUES (:ubr_id, :url_de, :url_en, :product, :text_de, :text_en);
                EOD;

        $this->clearLinks($org);

        foreach ($org->getLinks() as $link) {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                ":ubr_id" => $org->getUbrId(),
                ":url_de" => $link->getUrl()["de"],
                ":url_en" => $link->getUrl()["en"],
                ":product" => "dbis",
                ":text_de" => $link->getText()["de"],
                ":text_en" => $link->getText()["en"]
            ]);
        }
    }

    public function createOrganization(Organization $organization): void
    {

        $sql = <<<EOD
            INSERT INTO Organisations (
                ubr_id,
                dbis_id,
                name,
                name_en,
                city,
                city_en,
                region,
                region_en,
                zipcode,
                country_code,
                street,
                street_en,
                homepage,
                homepage_en,
                contact_email,
                iconpath,
                color,
                is_fid,
                is_consortium,
                is_kfl
            )  VALUES (
                :ubr_id,
                :dbis_id,
                :name,
                :name_en,
                :city,
                :city_en,
                :region,
                :region_en,
                :zipcode,
                :country_code,
                :street,
                :street_en,
                :homepage,
                :homepage_en,
                :contact_email,
                :iconpath,
                :color,
                :is_fid,
                :is_consortium,
                :is_kfl
            );
        EOD;

        $params = $this->entityToAssoc($organization);
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        // also persist external IDs
        $this->persistExternalIds($organization);
        $this->persistDbisView($organization);
        $this->persistDbisSettings($organization);
        $this->persistLinks($organization);
    }

    public function getOrganizations(array $options = null): array
    {
        // search for words
        $q = $options['q'] ?? null;
        // only get organizations with ids
        $ids = $options['ids'] ?? null;
        $autoaddflag = !is_null($options) && array_key_exists('autoaddflag', $options) && $options['autoaddflag'] == true ? true: null;

        $onlyWithView = $options['hasDbisView'] ?? null;

        $params = [];
        # inspired by https://stackoverflow.com/questions/21106975/php-pdo-prepare-statement-dynamic-filter
        # array_agg double-encodes results over here!
        # May be a bug in mysql/pdo - that is, why we "manually" encode the
        # array here

        $joinDbisViews = " LEFT JOIN DbisViews AS DV ON DV.organization = Organisations.ubr_id ";
        if ($onlyWithView) {
            $joinDbisViews = " INNER JOIN DbisViews AS DV ON DV.organization = Organisations.ubr_id ";
        } 
        
        $sql = <<<EOD
                    SELECT
                        Organisations.*,
                        DS.autoaddflag,
                        DV.id as 'dbis_view_id',
                        IF(
                            COUNT(EIDNS.id) = 0,
                            JSON_ARRAY(), CONCAT(
                                '[',
                                GROUP_CONCAT(
                                    JSON_OBJECT(
                                        'identifier', EID.identifier,
                                        'namespace', JSON_OBJECT(
                                            'id', EIDNS.id,
                                            'name_de', EIDNS.name_de,
                                            'name_en', EIDNS.name_en
                                        )
                                    )
                                ), ']'
                            )) AS extIds,
                        CONCAT('[',
                            GROUP_CONCAT(
                                JSON_OBJECT(
                                    'url', JSON_OBJECT(
                                        'url_de', OrganisationLinks.url_de,
                                        'url_en', OrganisationLinks.url_en
                                    ),
                                    'text', JSON_OBJECT(
                                        'text_de', OrganisationLinks.text_de,
                                        'text_en', OrganisationLinks.text_en
                                    )
                                )
                            ), ']'
                        ) AS linklist
                        FROM
                            Organisations
                        LEFT JOIN ExternalOrganizationIdentifiers AS EID
                            ON
                                EID.organization = Organisations.ubr_id
                        LEFT JOIN ExternalOrganizationIdentifierNamespace AS EIDNS
                            ON
                                EIDNS.id = EID.namespace
                        LEFT JOIN OrganisationLinks
                            ON 
                                OrganisationLinks.ubr_id = Organisations.ubr_id
                        $joinDbisViews 
                        LEFT JOIN DbisSettings AS DS
                            ON DS.ubr_id = Organisations.ubr_id
                        WHERE
                            Organisations.is_enabled = 1
        EOD;

        // apply textsearch
        if ($q) {
            $sql .= " AND (Organisations.name LIKE CONCAT('%', :q, '%') "
                    . "OR  Organisations.name_en LIKE CONCAT('%', :q, '%') "
                    . "OR  Organisations.city LIKE CONCAT('%', :q, '%') "
                    . "OR  Organisations.city_en LIKE CONCAT('%', :q, '%') "
                    . "OR Organisations.ubr_id LIKE CONCAT('%', :q, '%') )";
            $params['q'] = $q;
        }

        // apply search by ids
        if ($ids) {
            $strIds = array_reduce($ids, function ($str, $id) {
                return $str . ", '" . $id . "'";
            });
            // remove leading comma
            $strIds = ltrim($strIds, ',');
            $sql .= " AND Organisations.ubr_id IN ( " . $strIds . ") ";
        }

        if ($autoaddflag) {
            $sql .= " AND DS.autoaddflag = 1 ";
        }

        $sql .= ' GROUP BY Organisations.ubr_id '
                . ' ORDER BY Organisations.city;';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        return $this->parseDBResults($results);
    }

    public function getFIDs(): array {
        $params = [];

        $sql = <<<EOD
                    SELECT
                        Organisations.*,
                        DS.autoaddflag,
                        DV.id as 'dbis_view_id',
                        IF(
                            COUNT(EIDNS.id) = 0,
                            JSON_ARRAY(), CONCAT(
                                '[',
                                GROUP_CONCAT(
                                    JSON_OBJECT(
                                        'identifier', EID.identifier,
                                        'namespace', JSON_OBJECT(
                                            'id', EIDNS.id,
                                            'name_de', EIDNS.name_de,
                                            'name_en', EIDNS.name_en
                                        )
                                    )
                                ), ']'
                            )) AS extIds,
                        CONCAT('[',
                            GROUP_CONCAT(
                                JSON_OBJECT(
                                    'url', JSON_OBJECT(
                                        'url_de', OrganisationLinks.url_de,
                                        'url_en', OrganisationLinks.url_en
                                    ),
                                    'text', JSON_OBJECT(
                                        'text_de', OrganisationLinks.text_de,
                                        'text_en', OrganisationLinks.text_en
                                    )
                                )
                            ), ']'
                        ) AS linklist
                        FROM
                            Organisations
                        LEFT JOIN ExternalOrganizationIdentifiers AS EID
                            ON
                                EID.organization = Organisations.ubr_id
                        LEFT JOIN ExternalOrganizationIdentifierNamespace AS EIDNS
                            ON
                                EIDNS.id = EID.namespace
                        LEFT JOIN OrganisationLinks
                            ON 
                                OrganisationLinks.ubr_id = Organisations.ubr_id
                        LEFT JOIN DbisViews AS DV ON DV.organization = Organisations.ubr_id
                        LEFT JOIN DbisSettings AS DS
                            ON DS.ubr_id = Organisations.ubr_id
                        WHERE
                            Organisations.is_enabled = 1 and Organisations.is_fid = 1
        EOD;

        $sql .= ' GROUP BY Organisations.ubr_id '
                . ' ORDER BY Organisations.city;';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        return $this->parseDBResults($results);
    }

    public function getOrganizationByUbrId(string $id): Organization
    {
        # Note to future developers: ARRAYAGG in combination with JSON_OBJECT
        # seems to cause some troubles with double encoding, if there is more
        # than one result returned - this may be a badly documented bug.
        # LIMIT 1 attempts to avoid this issue, but if there are any future
        # Issues arising, please refer to the workaround developed in
        # "getOrganizations"

        if ($id == "ALL") {
            // Quick fix for Gesamtbestand
            $entry = array('ubr_id' => $id, 'name' => 'Gesamtbestand', 'name_en' => 'Total inventory', 'country_code' => '', 'created_at_date' => date('Y-m-j'));
            return $this->assocToEntity($entry);
        } else {
            $sql = <<<EOD
            SELECT Organisations.*,
                    DV.id as 'dbis_view_id',
                    DS.autoaddflag,
                IF(
                    COUNT(EIDNS.id)=0,
                    JSON_ARRAY(),
                    JSON_ARRAYAGG(JSON_OBJECT(
                        "identifier", EID.identifier,
                        "namespace", JSON_OBJECT(
                            "id", EIDNS.id,
                            "name_de", EIDNS.name_de,
                            "name_en", EIDNS.name_en)
                    ))
                ) as extIds,
                CONCAT('[',
                        GROUP_CONCAT(
                            JSON_OBJECT(
                                'url', JSON_OBJECT(
                                    'url_de', OrganisationLinks.url_de,
                                    'url_en', OrganisationLinks.url_en
                                ),
                                'text', JSON_OBJECT(
                                    'text_de', OrganisationLinks.text_de,
                                    'text_en', OrganisationLinks.text_en
                                )
                            )
                        ), ']'
                    ) AS linklist
            FROM Organisations
            LEFT JOIN ExternalOrganizationIdentifiers as EID
                ON EID.organization=Organisations.ubr_id
            LEFT JOIN ExternalOrganizationIdentifierNamespace as EIDNS
                ON EIDNS.id=EID.namespace
            LEFT JOIN DbisViews AS DV
                ON DV.organization = Organisations.ubr_id
            LEFT JOIN OrganisationLinks
                ON OrganisationLinks.ubr_id = Organisations.ubr_id
            LEFT JOIN DbisSettings AS DS
                ON DS.ubr_id = Organisations.ubr_id
            WHERE Organisations.ubr_id =:id 
                AND Organisations.is_enabled=1
            GROUP BY Organisations.ubr_id
            LIMIT 1;
            EOD;

            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                ":id" => $id
            ]);
            $results = $statement->fetchAll(PDO::FETCH_ASSOC);

            return $this->parseDBResults($results)[0];
        }
    }

    public function getOrganizationByDbisId(string $id): Organization
    {
        # Note to future developers: ARRAYAGG in combination with JSON_OBJECT
        # seems to cause some troubles with double encoding, if there is more
        # than one result returned - this may be a badly documented bug.
        # LIMIT 1 attempts to avoid this issue, but if there are any future
        # Issues arising, please refer to the workaround developed in
        # "getOrganizations"

        if ($id == "ALL") {
            $entry = array('ubr_id' => $id, 'dbis_id' => $id, 'name' => 'Gesamtbestand', 'name_en' => 'Gesamtbestand', 'country_code' => '', 'created_at_date' => date('Y-m-j'));
            return $this->assocToEntity($entry);
        } else {
            $sql = <<<EOD
            SELECT Organisations.*,
                    DV.id as 'dbis_view_id',
                    DS.autoaddflag,
                IF(
                    COUNT(EIDNS.id)=0,
                    JSON_ARRAY(),
                    JSON_ARRAYAGG(JSON_OBJECT(
                        "identifier", EID.identifier,
                        "namespace", JSON_OBJECT(
                            "id", EIDNS.id,
                            "name_de", EIDNS.name_de,
                            "name_en", EIDNS.name_en)
                    ))
                ) as extIds,
                CONCAT('[',
                        GROUP_CONCAT(
                            JSON_OBJECT(
                                'url', JSON_OBJECT(
                                    'url_de', OrganisationLinks.url_de,
                                    'url_en', OrganisationLinks.url_en
                                ),
                                'text', JSON_OBJECT(
                                    'text_de', OrganisationLinks.text_de,
                                    'text_en', OrganisationLinks.text_en
                                )
                            )
                        ), ']'
                    ) AS linklist
            FROM Organisations
            LEFT JOIN ExternalOrganizationIdentifiers as EID
                ON EID.organization=Organisations.ubr_id
            LEFT JOIN ExternalOrganizationIdentifierNamespace as EIDNS
                ON EIDNS.id=EID.namespace
            LEFT JOIN DbisViews AS DV
                ON DV.organization = Organisations.ubr_id
            LEFT JOIN OrganisationLinks
                ON OrganisationLinks.ubr_id = Organisations.ubr_id
            LEFT JOIN DbisSettings AS DS
                ON DS.ubr_id = Organisations.ubr_id
            WHERE Organisations.dbis_id =:id
                AND Organisations.is_enabled=1
            GROUP BY Organisations.ubr_id
            LIMIT 1;
            EOD;

            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                ":id" => $id
            ]);
            $results = $statement->fetchAll(PDO::FETCH_ASSOC);

            return $this->parseDBResults($results)[0];
        }
    }

    /**
     * Implementation note: This method is rather lenient in its search - all columns of table Organisations
     * are searched, not just dbis_id. We do this so that the queries from USB_K return meaningful data.
     * @throws OrganizationWithDbisIdNotExistingException
     */
    public function getUbrIdForDbisId(string $dbisId): string
    {
        $sql = <<<EOD
        SELECT Organisations.*
        FROM Organisations
        WHERE Organisations.dbis_id =:id OR Organisations.ezb_id =:id
        LIMIT 1;
        EOD;

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            ":id" => $dbisId
        ]);
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        $results = $this->parseDBResults($results);

        if (count($results) === 0) {
            throw new OrganizationWithDbisIdNotExistingException($dbisId);
        }

        return $results[0]->getUbrId();
    }

    public function getDbisIdForUbrId(string $ubrId): ?string
    {
        $sql = <<<EOD
            SELECT Organisations.*
            FROM Organisations
            WHERE Organisations.ubr_id =:id
            LIMIT 1;
        EOD;

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            ":id" => $ubrId
        ]);
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        $results = $this->parseDBResults($results);

        if (count($results) === 0) {
            return null;
        }

        return $results[0]->getDbisId();
    }

    public function getOrganizationById(string $id): Organization
    {
        # Note to future developers: ARRAYAGG in combination with JSON_OBJECT
        # seems to cause some troubles with double encoding, if there is more
        # than one result returned - this may be a badly documented bug.
        # LIMIT 1 attempts to avoid this issue, but if there are any future
        # Issues arising, please refer to the workaround developed in
        # "getOrganizations"
        $sql = <<<EOD
                SELECT Organisations.*,
                        DV.id as 'dbis_view_id',
                        DS.autoaddflag,
                    IF(
                        COUNT(EIDNS.id)=0,
                        JSON_ARRAY(),
                        JSON_ARRAYAGG(JSON_OBJECT(
                            "identifier", EID.identifier,                        
                            "namespace", JSON_OBJECT(
                                "id", EIDNS.id, 
                                "name_de", EIDNS.name_de,
                                "name_en", EIDNS.name_en)
                        ))                 
                    ) as extIds ,
                    CONCAT('[',
                            GROUP_CONCAT(
                                JSON_OBJECT(
                                    'url', JSON_OBJECT(
                                        'url_de', OrganisationLinks.url_de,
                                        'url_en', OrganisationLinks.url_en
                                    ),
                                    'text', JSON_OBJECT(
                                        'text_de', OrganisationLinks.text_de,
                                        'text_en', OrganisationLinks.text_en
                                    )
                                )
                            ), ']'
                        ) AS linklist
                FROM Organisations 
                LEFT JOIN ExternalOrganizationIdentifiers as EID 
                    ON EID.organization=Organisations.ubr_id
                LEFT JOIN ExternalOrganizationIdentifierNamespace as EIDNS
                    ON EIDNS.id=EID.namespace
                LEFT JOIN DbisViews AS DV
                    ON DV.organization = Organisations.ubr_id
                LEFT JOIN OrganisationLinks
                    ON OrganisationLinks.ubr_id = Organisations.ubr_id
                LEFT JOIN DbisSettings AS DS
                    ON DS.ubr_id = Organisations.ubr_id
                WHERE Organisations.ubr_id =:id 
                    AND Organisations.is_enabled=1
                GROUP BY Organisations.ubr_id
                LIMIT 1;
                EOD;

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            ":id" => $id
        ]);
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $this->parseDBResults($results)[0];
    }

    private function is_known_proxy(string $ip) {
        // TODO
        return false;
    }

    public function getOrganizationByIp(string $ip): ?Organization
    {
        if ($ip == '') {
            return null;
        }

        // Spezielle Proxies
        if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER) && $_SERVER['HTTP_X_FORWARDED_FOR'] != '' && $this->is_known_proxy($ip)) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
                 
        $sql = <<<EOD
                SELECT Organisations.ubr_id, Organisations.dbis_id, Bibips.bibid, Bibips.ip_pattern, LENGTH(Bibips.ip_pattern) AS l
                FROM Bibips JOIN Organisations ON Bibips.bibid = Organisations.ezb_id
                AND LOCATE(Bibips.ip_pattern, :ip) = 1
                ORDER BY l DESC LIMIT 1;
                EOD;

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            ":ip" => $ip
        ]);
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        if (count($results) > 0) {
            $ubrId = $results[0]["ubr_id"];
            return $this->getOrganizationById($ubrId);
        } else {
            return null;
        }
    }

    public function updateOrganization(Organization $org): void
    {
        $sql = <<<EOD
            UPDATE Organisations SET 
                ubr_id =    :ubr_id,
                dbis_id =   :dbis_id,
                name =      :name,
                name_en =   :name_en,
                city =      :city,
                city_en =   :city_en,
                region =    :region,
                region_en =:region_en,
                zipcode=    :zipcode,
                country_code =  :country_code,
                street =    :street,
                street_en = :street_en,
                homepage =  :homepage,
                homepage_en = :homepage_en,
                contact_email = :contact_email,
                iconpath = :iconpath,
                color = :color,
                is_fid = :is_fid,
                is_consortium = :is_consortium,
                is_kfl = :is_kfl
            WHERE ubr_id=:ubr_id 
                AND is_enabled=1;
        EOD;
        $params = $this->entityToAssoc($org);
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        // if no icon path is set, delete all possible icons
        if (!$org->getIconPath()) {
            $this->deleteIconForOrganization($org);
        }

        // also persist external IDs
        $this->persistExternalIds($org);
        $this->persistDbisView($org);
        $this->persistDbisSettings($org);
        $this->persistLinks($org);
    }

    public function storeIconForOrganization(Organization $org, $logoFile): string
    {
        $this->deleteIconForOrganization($org);
        $filePath = $logoFile['tmp_name'];
        $extension = pathinfo($logoFile['name'], PATHINFO_EXTENSION);
        $newPath = $this->logoLocation . $org->getUbrId() . '.' . $extension;

        move_uploaded_file(
            $filePath,
            $newPath
        );
        // return $newPath;
        return $org->getUbrId() . '.' . $extension;
    }

    private function deleteIconForOrganization(Organization $org): void
    {
        array_map('unlink', glob($this->logoLocation . $org->getUbrId() . '.*'));
    }

    public function deleteOrganizationById(string $ubrId): void
    {
        // first properly delete organization links
        $sqlDelete = <<<EOD
            DELETE FROM OrganisationLinks 
            WHERE ubr_id=:ubr_id;
        EOD;
        $statement = $this->pdo->prepare($sqlDelete);
        $statement->execute([
            ':ubr_id' => $ubrId
        ]);

        // then properly delete organization settings
        $sqlDelete = <<<EOD
            DELETE FROM DbisSettings 
            WHERE ubr_id=:ubr_id;
        EOD;
        $statement = $this->pdo->prepare($sqlDelete);
        $statement->execute([
            ':ubr_id' => $ubrId
        ]);

        // then properly delete organization external identifiers
        $sqlDelete = <<<EOD
            DELETE FROM ExternalOrganizationIdentifiers 
            WHERE organization=:ubr_id;
        EOD;
        $statement = $this->pdo->prepare($sqlDelete);
        $statement->execute([
            ':ubr_id' => $ubrId
        ]);

        // then properly delete organization external identifier namespaces
        $sqlDelete = <<<EOD
            DELETE FROM ExternalOrganizationIdentifierNamespace 
            WHERE id=:ubr_id;
        EOD;
        $statement = $this->pdo->prepare($sqlDelete);
        $statement->execute([
            ':ubr_id' => $ubrId
        ]);

        // then properly delete dbis view of the organization
        $sqlDelete = <<<EOD
            DELETE FROM DbisViews 
            WHERE organization=:ubr_id;
        EOD;
        $statement = $this->pdo->prepare($sqlDelete);
        $statement->execute([
            ':ubr_id' => $ubrId
        ]);
        
        // then properly delete organizations
        $sqlDelete = <<<EOD
            DELETE FROM Organisations 
            WHERE ubr_id=:ubr_id;
        EOD;
        $statement = $this->pdo->prepare($sqlDelete);
        $statement->execute([
            ':ubr_id' => $ubrId
        ]);

        // then insert a "placeholder" with correct ubr_id
        $sqlInsertDummy = <<<EOD
                INSERT INTO Organisations(ubr_id, is_enabled, name, city)
                    VALUES (:ubr_id, 0, "REDACTED", "REDACTED");
                EOD;
        $statement = $this->pdo->prepare($sqlInsertDummy);
        $statement->execute([
            ':ubr_id' => $ubrId
        ]);
    }

    public function existsOrganizationwithUbrId(string $ubrId, bool $isIncludingDeleted = false): bool
    {
        $sql = <<<EOD
            SELECT 1 FROM Organisations 
                WHERE ubr_id=:ubr_id AND is_enabled=1;
        EOD;
        if ($isIncludingDeleted) {
            $sql = <<<EOD
             SELECT 1 FROM Organisations 
                WHERE ubr_id=:ubr_id;                   
            EOD;
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            ':ubr_id' => $ubrId
        ]);
        return ($statement->fetchColumn() != null);
    }

    public function existsOrganizationWithIp(string $ip): bool
    {
        $sql = <<<EOD
            SELECT 1 FROM IPRanges 
                WHERE INSTR(`ip_pattern`, :ip) > 0;
        EOD;
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            ':ip' => $ip
        ]);

        return ($statement->fetchColumn() != null);
    }

    public function existsOrganizationwithDbisId(string $dbisId): bool
    {
        $sql = <<<EOD
            SELECT 1 FROM Organisations 
                WHERE dbis_id=:dbis_id AND is_enabled=1;
        EOD;
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            ':dbis_id' => $dbisId
        ]);
        return ($statement->fetchColumn() != null);
    }

    public function getIdentifierNamespaces(): array
    {
        $sql = <<<EOD
                SELECT * FROM ExternalOrganizationIdentifierNamespace;
                EOD;
        $statement = $this->pdo->prepare($sql);
        $statement->execute();
        $namespaces = array_map(function ($a) {
            return new ExternalOrganizationIdentifierNamespace(
                $a['id'],
                [
                "de" => $a['name_de'],
                "en" => $a['name_en']
                    ]
            );
        }, $statement->fetchAll(PDO::FETCH_ASSOC));
        return $namespaces;
    }

    public function getSettings(): array
    {
        $sql = <<<EOD
                SELECT * FROM Settings;
                EOD;
        $statement = $this->pdo->prepare($sql);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateSettings($settings): void
    {
        $sql = <<<EOD
            UPDATE Settings SET 
                translate_url = :translate_url
            WHERE id=:id;
        EOD;
        $statement = $this->pdo->prepare($sql);
        $statement->execute($settings);
    }

    /**
     *
     * @param type $cursor
     * @return array
     */
    private function parseDBResults(array $entries): array
    {
        $organizations = [];
        foreach ($entries as $entry) {
            $organizations[] = $this->assocToEntity($entry);
        }
        return $organizations;
    }
}
