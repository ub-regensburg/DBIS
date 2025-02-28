<?php

declare(strict_types=1);

namespace App\Infrastructure\Resources;

use App\Domain\Resources\Entities\AccessMapping;
use PDO;
use DateTime;
use App\Domain\Resources\Entities\Collection;
use App\Domain\Resources\Entities\ExternalID;
use App\Domain\Resources\Entities\SortType;
use App\Domain\Resources\Entities\Author;
use App\Domain\Resources\Entities\Keyword;
use App\Domain\Resources\Entities\Resource;
use App\Domain\Resources\Entities\ResourcePreview;
use App\Domain\Resources\Entities\Subject;
use App\Domain\Resources\Entities\ResourceAggregate;
use App\Domain\Resources\Entities\Type;
use App\Domain\Resources\Entities\PublicationForm;
use App\Domain\Resources\Entities\Host;
use App\Domain\Resources\Entities\UpdateFrequency;
use App\Domain\Resources\Entities\License;
use App\Domain\Resources\Entities\LicenseType;
use App\Domain\Resources\Entities\LicenseForm;
use App\Domain\Resources\Entities\Access;
use App\Domain\Resources\Entities\AccessType;
use App\Domain\Resources\Entities\AccessForm;
use App\Domain\Resources\Entities\Url;
use App\Domain\Resources\Exceptions\CollectionNotFoundException;
use App\Domain\Resources\Exceptions\ResourceNotFoundException;
use App\Domain\Resources\Entities\TopResourceEntry;
use App\Domain\Resources\Entities\AlternativeTitle;
use App\Domain\Resources\Entities\Enterprise;
use App\Domain\Resources\Entities\Country;
use App\Domain\Resources\Entities\LicenseLocalisation;
use App\Infrastructure\Resources\GetResourcesQuery;
use App\Infrastructure\Shared\MailClient;

/**
 * ResourceRepository
 *
 * Repository for Resources in the DBIS database
 *
 */
class ResourceRepository
{
    public const LOCAL = 'local';
    public const GLOBAL = 'global';
    public const COMBINED = 'combined';

    private PDO $pdo;
    private MailClient $mailClient;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->mailClient = new MailClient();
    }

    private function getLabelIdOfFreeResources() {
        $labelId = null;

        $sql = "select id from labels_for_organisation where is_for_free_resources is true";

        $statement = $this->pdo->prepare($sql);
        $statement->execute();

        $freeLabel = $statement->fetch(PDO::FETCH_ASSOC);

        if ($freeLabel) {
            $labelId = (int) $freeLabel['id'];
        }
        
        return $labelId;
    }

    // ------------ Licenses -------------

    private function prepareAccessParams($access, $license, $licenseType, $labelIdOfFreeResources) {
        return [
            'licenseId' => $license->getId(),
            'type' => $access->getType() && $licenseType !== 1 ? $access->getType()->getId() : null,
            'form' => $access->getForm() && $licenseType !== 1 ? $access->getForm()->getId() : null,
            'host' => $access->getHost() ? $access->getHost()->getId() : null,
            'accessUrl' => $access->getAccessUrl(),
            'manualUrl' => $access->getManualUrl(),
            'label' => json_encode($access->getLabel()),
            'labelLong' => $access->getLongLabel() ? json_encode($access->getLongLabel()) : null,
            'labelLongest' => $access->getLongestLabel() ? json_encode($access->getLongestLabel()) : null,
            'description' => json_encode($access->getDescription()),
            'requirements' => json_encode($access->getRequirements()),
            'isLinkDead' => null,
            'organization' => $access->getOrganizationId(),
            'shelfmark' => $access->getShelfmark()
            // 'label_id' => $licenseType == 1 && $access->getOrganizationId() == null ? $labelIdOfFreeResources : $access->getLabelId()
        ];
    }

    private function handleMainAccess($access, $accessId, $license, $localOrganizationId) {
        if ($access->isMainAccess()) {
            // Delete existing main access
            $sql = "DELETE FROM main_access_for_organization WHERE organization=:organization AND resource=:resource";
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                ":organization" => $localOrganizationId,
                ":resource" => $license->getResourceId()
            ]);
    
            // Insert new main access
            $params = [
                'resource' => $license->getResourceId(),
                'organization' => $localOrganizationId,
                'access' => $accessId
            ];

            $sql = <<<EOD
                INSERT INTO main_access_for_organization (
                    resource,
                    organization,
                    access
                ) VALUES (
                    :resource,
                    :organization,
                    :access
                ) ON CONFLICT (organization, resource) DO NOTHING;
            EOD;

            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);
        }
    }

    private function handleVisibility($access, $accessId, $localOrganizationId) {
        if (!$access->getVisibility()) {
            $params = [
                'organization' => $localOrganizationId,
                'access' => $accessId
            ];
    
            $sql = <<<EOD
                INSERT INTO access_hidden_for_organisation (
                    organisation,
                    access
                ) VALUES (
                    :organization,
                    :access
                ) ON CONFLICT (organisation, access) DO NOTHING;
            EOD;
    
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);
        }
    }

    private function persistAccessesForLicense(
        License $license,
        $localOrganizationId
    ): void {
        $accesses = $license->getAccesses();

        $labelIdOfFreeResources = $this->getLabelIdOfFreeResources();

        $resourceId = $license->getResourceId();

        /*
        * Get all current accesses that belong to the organisation or that are global accesses
        */
        $sql = <<<EOD
                select
                    TO_JSONB(access_type.*) as access_type,
                    TO_JSONB(access_form.*) as access_form,
                    TO_JSONB(host.*) as access_host,
                    access.*,
                    labels_for_organisation.label as org_label,
                    labels_for_organisation.label_long as org_label_long,
                    labels_for_organisation.label_longest as org_label_longest,
                    case
                        when main_access_for_organization.access is not null then true
                        else false
                    end as is_main_access,
                    case
                        when access_hidden_for_organisation.access is null then true
                        else false
                    end as is_visible
                from
                    access
                left join labels_for_organisation
                                        on
                    access.label_id = labels_for_organisation.id
                left join access_hidden_for_organisation
                                        on
                    access.id = access_hidden_for_organisation.access
                    and access_hidden_for_organisation.organisation = :organizationId
                left join access_type on
                    access_type.id = access.type
                left join access_form on
                    access_form.id = access.form
                left join main_access_for_organization on
                    access.id = main_access_for_organization.access
                    and main_access_for_organization.resource = :resourceId
                    and main_access_for_organization.organization = :organizationId
                left join host on
                    host.id = access.host
                where
                    license =:licesenId
                    and (access.organization =:organizationId
                        or access.organization is null);
                EOD;
        $params = [];
        $params[':licesenId'] = $license->getId();
        $params[':organizationId'] = $localOrganizationId;
        $params[':resourceId'] = $resourceId;
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        $oldAccesses = array_map(function ($row) {
            $row['access_type'] = is_string($row['access_type']) ? json_decode($row['access_type'], true) : null;
            $row['access_form'] = is_string($row['access_form']) ? json_decode($row['access_form'], true) : null;
            $row['access_host'] = is_string($row['access_host']) ? json_decode($row['access_host'], true) : null;
            $row['label'] = is_string($row['label']) ? json_decode($row['label'], true) : null;
            $row['label_long'] = is_string($row['label_long']) ? json_decode($row['label_long'], true) : null;
            $row['label_longest'] = is_string($row['label_longest']) ? json_decode($row['label_longest'], true) : null;
            $row['org_label'] = is_string($row['org_label']) ? json_decode($row['org_label'], true) : null;
            $row['org_label_long'] = is_string($row['org_label_long']) ? json_decode($row['org_label_long'], true) : null;
            $row['org_label_longest'] = is_string($row['org_label_longest']) ? json_decode($row['org_label_longest'], true) : null;
            $row['description'] = is_string($row['description']) ? json_decode($row['description'], true) : null;
            $row['requirements'] = is_string($row['requirements']) ? json_decode($row['requirements'], true) : null;
            return $row;
        }, $statement->fetchAll(PDO::FETCH_ASSOC));

        // Transform the associative arrays to access objects
        $oldAccesses = array_map(
            function ($entry) {
                return $this->assocToEntityAccess($entry);
            },
            $oldAccesses
        );

        // Map the accesses based on their id
        $oldAccessMap = [];
        foreach ($oldAccesses as $old) {
            $oldAccessMap[$old->getId()] = $old;
        }
        $newAccessMap = [];
        foreach ($accesses as $new) {
            $newAccessMap[$new->getId()] = $new;
        }

        // Find accesses to DELETE (In oldAccesses but NOT in access)
        $toDelete = array_diff_key($oldAccessMap, $newAccessMap);

        // Find accesses to INSERT (In access but NOT in oldAccesses)
        $toInsert = array_diff_key($newAccessMap, $oldAccessMap);

        // Find accesses to UPDATE (same ID)
        // TODO: Add a comparision, if the accesses are the same/different
        $toUpdate = [];
        foreach ($newAccessMap as $id => $newAccess) {
            if (isset($oldAccessMap[$id])) {
                $toUpdate[$id] = $newAccess;
            }
        }

        // Delete accesses
        foreach ($toDelete as $del) {
            // Delete the accesses from main_access_for_organization and access_hidden_for_organisation
            $sql = "DELETE FROM main_access_for_organization WHERE access=:access and resource=:resource";
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                ":access" => $del->getId(),
                ":resource" => $resourceId
            ]);

            $sql_hidden_accesses = "DELETE FROM access_hidden_for_organisation WHERE access=:access and organisation=:organization";
            $statement_hidden_accesses = $this->pdo->prepare($sql_hidden_accesses);
            $statement_hidden_accesses->execute([
                ":access" => $del->getId(),
                ":organization" => $localOrganizationId
            ]);
        }

        $licenseType = (int) $license->getType()->getId();

        /*
        * Insert all accesses that are new ones
        */
        foreach ($toInsert as $ins) {
            if ($ins->getHost() && !$ins->getHost()->getId()) {
                $host = $this->createHost($ins->getHost());
                $ins->setHost($host);
            }
            
            $params = $this->prepareAccessParams($ins, $license, $licenseType, $labelIdOfFreeResources);

            $sql = <<<EOD
                INSERT INTO access (
                    license, type, form, access_url, label, label_long, label_longest, 
                    manual_url, description, requirements, is_link_dead, host, organization, 
                    shelfmark
                ) VALUES (
                    :licenseId, :type, :form, :accessUrl, :label, :labelLong, :labelLongest, 
                    :manualUrl, :description, :requirements, :isLinkDead, :host, :organization, 
                    :shelfmark
                );
            EOD;

            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);

            $accessId = (int) $this->pdo->lastInsertId();
            
            $this->handleMainAccess($ins, $accessId, $license, $localOrganizationId);
            $this->handleVisibility($ins, $accessId, $localOrganizationId);
        }

        foreach ($toUpdate as $upd) {
            if ($upd->getHost() && !$upd->getHost()->getId()) {
                $host = $this->createHost($upd->getHost());
                $upd->setHost($host);
            }

            $params = $this->prepareAccessParams($upd, $license, $licenseType, $labelIdOfFreeResources);
            $accessId = $upd->getId();
            $params['id'] = $upd->getId();  // Add ID for the WHERE condition

            $sql = <<<EOD
                UPDATE access SET
                    license = :licenseId,
                    type = :type,
                    form = :form,
                    access_url = :accessUrl,
                    label = :label,
                    label_long = :labelLong,
                    label_longest = :labelLongest,
                    manual_url = :manualUrl,
                    description = :description,
                    requirements = :requirements,
                    is_link_dead = :isLinkDead,
                    host = :host,
                    organization = :organization,
                    shelfmark = :shelfmark
                WHERE id = :id;
            EOD;

            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);

            $this->handleMainAccess($upd, $accessId, $license, $localOrganizationId);
            $this->handleVisibility($upd, $accessId, $localOrganizationId);
        }
    }

    private function removeAccessesFromLicense(License $license): void {
        $accesses = $license->getAccesses();

        foreach ($accesses as $access) {
            $sql = <<<EOD
                DELETE FROM access
                    WHERE id=:accessId;
                EOD;
            $params = [];
            $params[':accessId'] = $access->getId();
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);
        }
    }

    private function persistFIDForLicense(License $license): void {
        $this->removeFIDFromLicense($license);

        $sql = "INSERT INTO fid_for_license (license, fid, hosting_privilege) VALUES (:licenseId, :fidId, :hostingPrivilege);";
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            "fidId" => $license->getFID(),
            "licenseId" => $license->getId(),
            "hostingPrivilege" => $license->getHostingPrivilege() ? 1 : 0
        ]);
    }

    private function removeLicenseLocalisationForAllOrganizations(License $license) {
        $sql = <<<EOD
                DELETE FROM license_localisation
                    WHERE license=:licenseId
                EOD;
        $params = [];
        $params[':licenseId'] = $license->getId();
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }

    private function persistLicenseLocalisation(License $license, $localOrganizationId): void {
        $this->removeLicenseLocalisation($license, $localOrganizationId);

        $aquired = null;
        if ($license->getLicenseLocalisation()->getAquired()) {
            $aquired = $license->getLicenseLocalisation()->getAquired();
        }
        $cancelled = null;
        if ($license->getLicenseLocalisation()->getCancelled()) {
            $cancelled = $license->getLicenseLocalisation()->getCancelled();
        }
        $last_check = null;
        if ($license->getLicenseLocalisation()->getLastCheck()) {
            $last_check = $license->getLicenseLocalisation()->getLastCheck();
        }

        $sql = "INSERT INTO license_localisation (license, organisation, internal_notes, external_notes, aquired, cancelled, last_check) VALUES (:licenseId, :organisation, :internal_notes, :external_notes, :aquired, :cancelled, :last_check);";
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            "organisation" => $localOrganizationId,
            "licenseId" => $license->getId(),
            "internal_notes" => json_encode($license->getLicenseLocalisation()->getInternalNotes()),
            "external_notes" => json_encode($license->getLicenseLocalisation()->getExternalNotes()),
            "aquired" => $aquired,
            "cancelled" => $cancelled,
            "last_check" => $last_check
        ]);
    }

    public function updateLicenseLocalisation($licenseLocalisation) {
        $licenseId = $licenseLocalisation->getLicenseId();
        $ubrId = $licenseLocalisation->getOrganisation();

        $sql = "UPDATE license_localisation SET internal_notes=:internal_notes, external_notes=:external_notes, aquired=:aquired, cancelled=:cancelled, last_check=:last_check WHERE organisation = :organisation AND license = :licenseId;";

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            "organisation" => $ubrId,
            "licenseId" => $licenseId,
            "internal_notes" => json_encode($licenseLocalisation->getInternalNotes()),
            "external_notes" => json_encode($licenseLocalisation->getExternalNotes()),
            "aquired" => $licenseLocalisation->getAquired(),
            "cancelled" => $licenseLocalisation->getCancelled(),
            "last_check" => $licenseLocalisation->getLastCheck()
        ]);

        $affectedRows = $statement->rowCount();

        if ($affectedRows < 1) {
            $sql = "INSERT INTO license_localisation (license, organisation, internal_notes, external_notes, aquired, cancelled, last_check) VALUES (:licenseId, :organisation, :internal_notes, :external_notes, :aquired, :cancelled, :last_check);";
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                "organisation" => $ubrId,
                "licenseId" => $licenseId,
                "internal_notes" => json_encode($licenseLocalisation->getInternalNotes()),
                "external_notes" => json_encode($licenseLocalisation->getExternalNotes()),
                "aquired" => $licenseLocalisation->getAquired(),
                "cancelled" => $licenseLocalisation->getCancelled(),
                "last_check" => $licenseLocalisation->getLastCheck()
            ]);
        }
    }

    private function removeFIDFromLicense(License $license): void {
        $sql = <<<EOD
                DELETE FROM fid_for_license
                    WHERE license=:licenseId and fid=:fidId
                EOD;
        $params = [];
        $params[':licenseId'] = $license->getId();
        $params[':fidId'] = $license->getFID();
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }

    private function removeLicenseLocalisation(License $license, $localOrganizationId): void {
        $sql = <<<EOD
                DELETE FROM license_localisation
                    WHERE license=:licenseId and organisation=:localOrganizationId
                EOD;
        $params = [];
        $params[':licenseId'] = $license->getId();
        $params[':localOrganizationId'] = $localOrganizationId;
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }

    private function removeLicenseLocalisationForAll(License $license): void {
        $sql = <<<EOD
                DELETE FROM license_localisation
                    WHERE license=:licenseId
                EOD;
        $params = [];
        $params[':licenseId'] = $license->getId();
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }

    private function createHost(Host $host): Host
    {
        $sql = "INSERT INTO host (title) VALUES (:title);";
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            "title" => $host->getTitle()
        ]);
        $id = (int)$this->pdo->lastInsertId();
        $host->setId($id);
        return $host;
    }

    public function persistLicenseForOrganization($licenseId, string $forOrganizationWithId)
    {   
        try {
            $sql = "INSERT INTO license_for_organization (license, organization) "
            . " VALUES (:license, :organization);";
            $statement = $this->pdo->prepare($sql);

            $statement->execute([
                ":license" => $licenseId,
                ":organization" => $forOrganizationWithId
            ]);
        } catch(\Exception $e) {

        }
    }

    public function createLicense(Resource $resource, License $license, ?string $localOrganizationId, array $organizations): License
    {
        $sql = <<<EOD
                    INSERT INTO license (
                        resource,
                        type,
                        form,
                        number_of_concurrent_users,
                        valid_from_date,
                        valid_to_date,
                        is_active,
                        is_allowing_data_mining,
                        is_oa,
                        is_allowing_walking,
                        internal_notes,
                        external_notes,
                        vendor,
                        publisher,
                        last_check,
                        aquired,
                        cancelled
                    ) VALUES (
                        :resourceId,
                        :typeId,
                        :formId,
                        :nConcurrentUsers,
                        :validFrom,
                        :validTo,
                        :isActive,
                        :isAllowingDataMining,
                        :isOA,
                        :isAllowingWalking,
                        :internalNotes,
                        :externalNotes,
                        :vendor,
                        :publisher,
                        :last_check,
                        :aquired,
                        :cancelled
                    ) RETURNING id;
                    EOD;

        $license_type = $license->getType();

        $params = [];
        $params['resourceId'] = $resource->getId();
        $params['typeId'] = $license_type->getId();
        $params['formId'] = $license->getForm() ? $license->getForm()->getId() : null;
        $params['nConcurrentUsers'] = $license->getNumberOfConcurrentUsers();
        $params['validFrom'] = $license->getValidFromDate();
        $params['validTo'] = $license->getValidToDate();
        $params['isActive'] = $license->isActive() ? 1 : 0;
        $params['isAllowingDataMining'] = $license->textMiningAllowed() ? 1 : 0;
        $params['isOA'] = $license->isOA() ? 1 : 0;
        $params['isAllowingWalking'] = $license->isAllowingWalking() ? 1 : 0;
        $params['internalNotes'] = json_encode($license->getInternalNotes());
        $params['externalNotes'] = json_encode($license->getExternalNotes());
        $params['last_check'] = $license->getLastCheck();
        $params['aquired'] = $license->getAquired();
        $params['cancelled'] = $license->getCancelled();

        if ($license->getVendor()) {
            if (!$license->getVendor()->getId() && $license->getVendor()->getTitle()) {
                $newVendor = $this->createEnterprise($license->getVendor());
                $license->setVendor($newVendor);
            }
        }
        $params['vendor'] = $license->getVendor() ? $license->getVendor()->getId() : null;

        if ($license->getPublisher() != null) {
            if (!$license->getPublisher()->getId() && $license->getPublisher()->getTitle()) {
                $newPublisher = $this->createEnterprise($license->getPublisher());
                $license->setPublisher($newPublisher);
            }
        }
        $params['publisher'] = $license->getPublisher() ? $license->getPublisher()->getId() : null;

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        $license->setId($result['id']);

        $this->persistAccessesForLicense($license, $localOrganizationId);

        $this->persistExternalIdsForLicense($license);

        if ($license_type->getId() == 4 && $license->getFID()) {
            $this->persistFIDForLicense($license);
        }

        if (($license_type->getId() == 1 || $license_type->getId() == 3 || $license_type->getId() == 4 || $license_type->getId() == 5 || $license_type->getId() == 6) && $license->getLicenseLocalisation() && !is_null($localOrganizationId)) {
            /*
            * Insert license localisation
            */
            $this->persistLicenseLocalisation($license, $localOrganizationId);
	    }

        if ($license_type->getID() == 1) {
            // A cronjob runs every night to inform of newly created free licenses
        }

        /*
         * Insert license for organization
         */
        if (!is_null($localOrganizationId)) {
            $this->persistLicenseForOrganization($license->getId(), $localOrganizationId);
            /*
            * If type is euqal to 1, then add all organisazions that have activated global database for the specific subject (autoaddflag)
            */
            if ($license_type->getId() == 1) {
                foreach($organizations as &$organizaiton) {
                    $ubrId = $organizaiton->getUbrId();

                    if (mb_strtolower($ubrId) !== mb_strtolower($localOrganizationId)) {
                        $this->persistLicenseForOrganization($license->getId(), $ubrId);
                    }
                }
            }
        } 

        return $license;
    }

    public function updateAccesses(License $license, ?string $localOrganizationId): void
    {
        $this->persistAccessesForLicense($license, $localOrganizationId);
    }

    public function updateLicense(License $license, ?License $oldLicense, ?string $localOrganizationId, $organizations = array()): void
    {
        $license_type = $license->getType();

        $licenseTypeId = $license_type->getId();

        $licenseId = $license->getId();

        $sql = <<<EOD
                    UPDATE license SET 
                        type=:typeId,
                        form=:formId,
                        number_of_concurrent_users=:nConcurrentUsers,
                        valid_from_date=:validFrom,
                        valid_to_date=:validTo,
                        is_active=:isActive,
                        is_allowing_data_mining=:isAllowingDataMining,
                        is_oa=:isOA,
                        is_allowing_walking=:isAllowingWalking,
                        internal_notes=:internalNotes,
                        external_notes=:externalNotes,
                        vendor=:vendor,
                        publisher=:publisher,
                        last_check=:last_check,
                        aquired=:aquired,
                        cancelled=:cancelled,
                        publication_form=:publicationForm
                    WHERE id=:licenseId;
                    EOD;
        $params = [];
        $params['licenseId'] = $licenseId;
        $params['typeId'] = $license_type->getId();
        $params['formId'] = $license->getForm() ? $license->getForm()->getId() : null;
        $params['nConcurrentUsers'] = $license->getNumberOfConcurrentUsers();
        $params['validFrom'] = $license->getValidFromDate();
        $params['validTo'] = $license->getValidToDate();
        $params['isActive'] = $license->isActive() ? 1 : 0;
        $params['isAllowingDataMining'] = $license->textMiningAllowed() ? 1 : 0;
        $params['isOA'] = $license->isOA() ? 1 : 0;
        $params['isAllowingWalking'] = $license->isAllowingWalking() ? 1 : 0;
        $params['internalNotes'] = json_encode($license->getInternalNotes());
        $params['externalNotes'] = json_encode($license->getExternalNotes());
        $params['last_check'] = $license->getLastCheck();
        $params['aquired'] = $license->getAquired();
        $params['cancelled'] = $license->getCancelled();
        $params['publicationForm'] = $license->getPublicationForm() ? $license->getPublicationForm()->getId() : null;

        if ($license->getVendor()) {
            if (!$license->getVendor()->getId() && $license->getVendor()->getTitle()) {
                $newVendor = $this->createEnterprise($license->getVendor());
                $license->setVendor($newVendor);
            }
        }
        $params['vendor'] = $license->getVendor() ? $license->getVendor()->getId() : null;

        if ($license->getPublisher() != null) {
            if (!$license->getPublisher()->getId() && $license->getPublisher()->getTitle()) {
                $newPublisher = $this->createEnterprise($license->getPublisher());
                $license->setPublisher($newPublisher);
            }
        }
        $params['publisher'] = $license->getPublisher() ? $license->getPublisher()->getId() : null;

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        $oldLicenseType = $oldLicense->getType()->getId();

        // If switching form fid lciense to any other license, the associated fid should be removed from the license.
        if ($oldLicenseType == 4 && $licenseTypeId !== 4) {
            $this->removeFIDFromLicense($oldLicense);
        }

        if ($oldLicenseType == 2 && $licenseTypeId == 1 || $oldLicenseType == 1 && $licenseTypeId == 2) {
            $this->mailToUpdateMailingListBecauseOfLicenseChange($license);
        }

        // If switching form any global license to local license, the should be removed for all organisations and the local information should be removed.
        if (($oldLicenseType == 1 || $oldLicenseType == 3 || $oldLicenseType == 4 || $oldLicenseType == 5) && $licenseTypeId == 2) {
            $this->clearLicenseForAllOrganizations($oldLicense);
            $this->persistLicenseForOrganization($licenseId, $localOrganizationId);

            $this->removeLicenseLocalisationForAllOrganizations($oldLicense);
        }

        // Only update local license information if the license has a global type 
        if ($license->getLicenseLocalisation() && $licenseTypeId !== 2) {
            /*
            * Update license localisation
            */
            $this->persistLicenseLocalisation($license, $localOrganizationId);
        }

        $this->persistExternalIdsForLicense($license);

        $this->persistAccessesForLicense($license, $localOrganizationId);

        if ($license->getFID() && $licenseTypeId == 4) {
            $this->persistFIDForLicense($license);
        }

        if ($licenseTypeId == 1) {
            /*
            * If type is euqal to 1, then add all organisazions that have activated global database for the specific subject (autoaddflag)
            */
            foreach($organizations as &$organizaiton) {
                $ubrId = $organizaiton->getUbrId();

                if (mb_strtolower($ubrId) !== mb_strtolower($localOrganizationId)) {
                    $this->persistLicenseForOrganization($license->getId(), $ubrId);
                }
            }
        }
    }

    public function removeLicense(License $license, ?string $localOrganizationId): void
    {
        $this->clearExternalIdsForLicense($license);

        $this->clearLicenseForAllOrganizations($license);

        /*
        * Remove access
        */
        $this->removeAccessesFromLicense($license);
        
        /*
        * Remove fid
        */
        $this->removeFIDFromLicense($license);

        /*
        * Delete license localisation for all organisations
        */
        $this->removeLicenseLocalisationForAll($license);

        $sql = <<<EOD
                DELETE FROM license
                    WHERE id=:licenseId
                EOD;
        $params = [];
        $params[':licenseId'] = $license->getId();
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }

    public function removeLicenseForOrganization(
        License $license,
        ?string $localOrganizationId
    ): void {
        $this->clearLicenseForOrganization($license, $localOrganizationId);
    }

    private function mailToUpdateMailingListBecauseOfLicenseChange(License $license): void
    {
        $licenseType = $license->getType()->getId();

        $resourceId = $license->getResourceId();
        $resource = $this->getResourceById_NEW($resourceId);
        $resourceTitle = $resource ? $resource->getTitle(): "";

        if ($licenseType == 1) {
            $this->mailClient->informDatabaseChangedFromPaidToFree($resourceId, $resourceTitle);
        } else {
            $this->mailClient->informDatabaseChangedFromFreeToPaid($resourceId, $resourceTitle);
        }
    }

    private function mailToUpdateMailingListBecauseOfVisiblityChange(Resource $resource): void
    {
        $isVisible = $resource->isVisible();
        $resourceId = $resource->getId();
        $resourceTitle = $resource->getTitle();

        if ($isVisible) {
            $this->mailClient->informDatabaseIsVisible($resourceId, $resourceTitle);
        } else {
            $this->mailClient->informDatabaseIsHidden($resourceId, $resourceTitle);
        }
    }

    private function clearLicenseForAllOrganizations(License $license): void
    {
        $sql = <<<EOD
                DELETE FROM license_for_organization
                    WHERE license=:licenseId
                EOD;
        $params = [];
        $params[':licenseId'] = $license->getId();
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }

    private function clearLicenseForOrganization(License $license, ?string $localOrganizationId)
    {
        if ($localOrganizationId) {
            $sql = <<<EOD
                DELETE FROM license_for_organization
                    WHERE license=:licenseId AND organization = :organizationId
                EOD;
            $params = [];
            $params[':licenseId'] = $license->getId();
            $params[':organizationId'] = $localOrganizationId;
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);

            if ($license->getType()->getId() == 2) {
                /*
                * Remove access when local license
                */
                $this->removeAccessesFromLicense($license);
            }

            /*
            * Delete license localisation
            */
            $this->removeLicenseLocalisation($license, $localOrganizationId);
        }
    }

    public function reuseLicense(int $license_id, $organization_id): void
    {
        $this->persistLicenseForOrganization($license_id, $organization_id);
    }

    public function getAdditionalLicenses(Resource $resource, string $organization_id): array
    {
        $resourceId = $resource->getId();
        /*
        $sql = "SELECT license.*, 
                        TO_JSONB(license_type.*) as license_type,
                        TO_JSONB(license_form.*) as form FROM license 
                    LEFT JOIN license_for_organization ON license.id = license_for_organization.license 
                    LEFT JOIN license_type ON license_type.id = license.type 
                    LEFT JOIN license_form ON license_form.id = license.form 
                    WHERE license.resource = :resourceId AND 
                        (license.type = 1 OR license.type = 3 OR license.type = 4 OR license.type = 5 OR license.type = 6) 
                        AND NOT EXISTS (select license_for_organization.*, license.resource 
                            from license_for_organization join license on license_for_organization.license = license.id  
                            where organization = :orgId and license.resource = :resourceId)
                    GROUP BY license.id, license_type.id, license_form.id;";
        */

        $sql = "SELECT license.*, 
                        TO_JSONB(license_type.*) as license_type,
                        TO_JSONB(license_form.*) as form,
                        TO_JSONB(enterprise_vendor.*) as vendor_obj,
                        TO_JSONB(enterprise_publisher.*) as publisher_obj,
                        TO_JSONB(publication_form.*) as publication_form_obj
                    FROM license 
                    LEFT JOIN license_for_organization ON license.id = license_for_organization.license 
                    LEFT JOIN license_type ON license_type.id = license.type 
                    LEFT JOIN license_form ON license_form.id = license.form 
                    left join enterprise as enterprise_vendor on
                        enterprise_vendor.id = license.vendor
                    left join enterprise as enterprise_publisher on
                        enterprise_publisher.id = license.publisher
                    left join publication_form on
                        publication_form.id = license.publication_form
                    WHERE license.resource = :resourceId AND 
                        (license.type = 1 OR license.type = 3 OR license.type = 4 OR license.type = 5 OR license.type = 6) 
                    GROUP BY license.id, license_type.id, license_form.id, enterprise_vendor.*, enterprise_publisher.*, publication_form.*;";

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            "resourceId" => $resourceId
        ]);

        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        $results_processed = [];

        foreach ($results as $license) {
            if ($license['form']) {
                $license['form'] = json_decode($license['form'], true);
            }

            if ($license['license_type']) {
                $license['license_type'] = json_decode($license['license_type'], true);
            }

            $license['vendor_obj'] = isset($license['vendor_obj']) && $license['vendor_obj'] ?
                json_decode($license['vendor_obj'], true) : null;
            $license['publisher_obj'] = isset($license['publisher_obj']) && $license['publisher_obj'] ?
                json_decode($license['publisher_obj'], true) : null;

            if ($license['publication_form_obj']) {
                $license['publication_form_obj'] = json_decode($license['publication_form_obj'], true);
            }
            
            $results_processed[] = $license;
        }

        $sql = "select license_for_organization.*, license.resource 
                    from license_for_organization join license on license_for_organization.license = license.id  
                    where organization = :orgId and license.resource = :resourceId";

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            "resourceId" => $resourceId,
            "orgId" => $organization_id
        ]);

        $licensedByOwnOrganisation = $statement->fetchAll(PDO::FETCH_ASSOC);

        // Filter out the licenses the org owns.
        $finalResults = [];
        foreach ($results_processed as $license) {
            $licenseId = (int)$license['id'];
            $isAdditionalLicense = True;

            foreach ($licensedByOwnOrganisation as $licenseByOwnedLicense) {
                $licenseIdByOwnedLicense = (int)$licenseByOwnedLicense['license'];
                if ($licenseId == $licenseIdByOwnedLicense) {
                    $isAdditionalLicense = False;
                    break;
                }
            }

            if ($isAdditionalLicense) {
                $finalResults[] = $license;
            }
        }

        return $finalResults;
    }

    //
    //
    // ------------ Resources -------------

    public function createResource(
        Resource $resourceGlobal,
        string $localOrganizationId = null,
        Resource $resourceLocal = null
    ): int {
        $globalId = $this->createResourceGlobal($resourceGlobal, $localOrganizationId);
        if (isset($localOrganizationId)) {
            $this->createResourceLocal($resourceLocal, $localOrganizationId, $globalId);
        }
        return $globalId;
    }

    public function createResourceGlobal(Resource $resource, $localOrganizationId): int
    {
        $sql = <<<EOD
            INSERT INTO resource (
                title,
                description_short,
                description,
                report_time_start,
                report_time_end,
                publication_time_start,
                publication_time_end,
                is_still_updated,
                update_frequency,
                shelfmark,
                note,
                isbn_issn,
                instructions,
                is_visible,
                created_by,
                is_free
            )  VALUES (
                :title,
                :description_short,
                :description,
                :report_time_start,
                :report_time_end,
                :publication_time_start,
                :publication_time_end,
                :is_still_updated,
                :update_frequency,
                :shelfmark,
                :note,
                :isbn_issn,
                :instructions,
                :is_visible,
                :created_by,
                :is_free
            );
        EOD;

        $params = $this->entityToAssocResource($resource);
        $params[':created_by'] = $localOrganizationId;

        // Unset the local note, because the global resource does not need it
        unset($params[':local_note']);

        $statement = $this->pdo->prepare($sql);

        $statement->execute($params);

        $resource_id = (int)$this->pdo->lastInsertId();
        $resource->setId($resource_id);

        $this->persistKeywordsForResource($resource);

        $this->persistAuthorsForResource($resource);

        $this->persistSubjectsForResource($resource);

        $this->persistTypesForResource($resource);

        $this->persistAlternativeTitlesForResource($resource);

        $this->persistApiUrlsForResource($resource);

        $this->persistCountriesForResource($resource);

        $this->persistExternalIdsForResource($resource);

        return $resource->getId();
    }

    public function createResourceLocal(Resource $resource, string $localOrganizationId, int $globalId): int
    {
        $sql = <<<EOD
            INSERT INTO resource_localisation (
                title,
                description_short,
                description,
                report_time_start,
                report_time_end,
                publication_time_start,
                publication_time_end,
                is_still_updated,
                update_frequency,
                shelfmark,
                note,
                isbn_issn,
                local_note,
                instructions,
                is_visible,
                resource,
                organisation
            )  VALUES (
                :title,
                :description_short,
                :description,
                :report_time_start,
                :report_time_end,
                :publication_time_start,
                :publication_time_end,
                :is_still_updated,
                :update_frequency,
                :shelfmark,
                :note,
                :isbn_issn,
                :local_note,
                :instructions,
                :is_visible,
                :resource,
                :organisation
            );
        EOD;

        $is_local = true;
        $params = $this->entityToAssocResource($resource, $is_local);
        unset($params[':is_free']);
        $params['organisation'] = $localOrganizationId;
        $params['resource'] = $globalId;
        $params = array_map(function ($param) {
            $result = null;
            if (isset($param) && $param != "" && $param != '{"de":"","en":""}') {
                $result = $param;
            }
            return $result;
        }, $params);
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        $resource->setId($globalId);

        // store dependent licenses
        // $this->updateLicenses($resource, $localOrganizationId);

        $this->persistKeywordsForResource($resource, $localOrganizationId);

        $this->persistAuthorsForResource($resource, $localOrganizationId);

        $this->persistSubjectsForResource($resource, $localOrganizationId);

        $this->persistTypesForResource($resource, $localOrganizationId);

        $this->persistCountriesForResource($resource, $localOrganizationId);

        return $resource->getId();
    }

    public function removeResource(
        Resource $resource,
        string $localOrganizationId = null,
        bool $isSuperAdmin = false
    ) {
        $sql = <<<EOD
            DELETE FROM resource_type_for_resource
                WHERE resource=:resourceId;
            EOD;
        $params = [];
        $params[':resourceId'] = $resource->getId();
        $statement = $this->pdo->prepare($sql);
        $success = $statement->execute($params);
        if ($success) {
            
        } else {
            
        }

        $sql = <<<EOD
            DELETE FROM subject_for_resource
                WHERE resource=:resourceId;
            EOD;
        $params = [];
        $params[':resourceId'] = $resource->getId();
        $statement = $this->pdo->prepare($sql);
        $success = $statement->execute($params);
        if ($success) {
            
        } else {
            
        }

        $sql = <<<EOD
            DELETE FROM author_for_resource
                WHERE resource=:resourceId;
            EOD;
        $params = [];
        $params[':resourceId'] = $resource->getId();
        $statement = $this->pdo->prepare($sql);
        $success = $statement->execute($params);
        if ($success) {
            
        } else {
            
        }

        $sql = <<<EOD
            DELETE FROM keyword_for_resource
                WHERE resource=:resourceId;
            EOD;
        $params = [];
        $params[':resourceId'] = $resource->getId();
        $statement = $this->pdo->prepare($sql);
        $success = $statement->execute($params);
        if ($success) {
            
        } else {
            
        }

        $sql = <<<EOD
            DELETE FROM country_for_resource
                WHERE resource=:resourceId;
            EOD;
        $params = [];
        $params[':resourceId'] = $resource->getId();
        $statement = $this->pdo->prepare($sql);
        $success = $statement->execute($params);
        if ($success) {
            
        } else {
           
        }

        $sql = <<<EOD
            DELETE FROM external_resource_id
                WHERE resource=:resourceId;
            EOD;
        $params = [];
        $params[':resourceId'] = $resource->getId();
        $success = $statement->execute($params);
        if ($success) {
            
        } else {
            
        }

        $sql = <<<EOD
            DELETE FROM alternative_title
                WHERE resource=:resourceId;
            EOD;
        $params = [];
        $params[':resourceId'] = $resource->getId();
        $statement = $this->pdo->prepare($sql);
        $success = $statement->execute($params);
        if ($success) {
           
        } else {
            
        }

        $sql = <<<EOD
            DELETE FROM top_resource_for_subject
                WHERE resource=:resourceId;
            EOD;
        $params = [];
        $params[':resourceId'] = $resource->getId();
        $statement = $this->pdo->prepare($sql);
        $success = $statement->execute($params);
        if ($success) {
           
        } else {
            
        }

        $sql = <<<EOD
            DELETE FROM top_resource_for_collection
                WHERE resource=:resourceId;
            EOD;
        $params = [];
        $params[':resourceId'] = $resource->getId();
        $statement = $this->pdo->prepare($sql);
        $success = $statement->execute($params);
        if ($success) {
            
        } else {
           
        }

        $sql = <<<EOD
            DELETE FROM resource_for_collection
                WHERE resource=:resourceId;
            EOD;
        $params = [];
        $params[':resourceId'] = $resource->getId();
        $statement = $this->pdo->prepare($sql);
        $success = $statement->execute($params);
        if ($success) {
           
        } else {
           
        }

        $sql = <<<EOD
            DELETE FROM relation_for_resource
                WHERE resource=:resourceId or related_to_resource=:resourceId;
            EOD;
        $params = [];
        $params[':resourceId'] = $resource->getId();
        $statement = $this->pdo->prepare($sql);
        $success = $statement->execute($params);
        if ($success) {
            
        } else {
            
        }

        $sql = <<<EOD
            DELETE FROM resource_localisation
                WHERE resource=:resourceId;
            EOD;
        $params = [];
        $params[':resourceId'] = $resource->getId();
        $statement = $this->pdo->prepare($sql);
        $success = $statement->execute($params);
        if ($success) {
            
        } else {
            
        }

        $sql = "SELECT * FROM license WHERE resource=:resourceId";
        $params = [];
        $params[':resourceId'] = $resource->getId();
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $licensesAssoc = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($licensesAssoc && count($licensesAssoc) > 0) {
            foreach ($licensesAssoc as $licenseAssoc) {
                $id = (int) $licenseAssoc['id'];

                $params = [];
                $params[':id'] = $id;

                $sql = "DELETE FROM access WHERE license = :id";
                $statement = $this->pdo->prepare($sql);
                $success = $statement->execute($params);
                if ($success) {
                    
                } else {
                    
                }

                $sql = "DELETE FROM license_localisation WHERE license = :id";
                $statement = $this->pdo->prepare($sql);
                $success = $statement->execute($params);
                if ($success) {
                    
                } else {
                    
                }

                $sql = "DELETE FROM license_for_organization WHERE license=:id";
                $statement = $this->pdo->prepare($sql);
                $success = $statement->execute($params);
                if ($success) {
                    
                } else {
                    
                }
            }
        } 

        $sql = <<<EOD
            DELETE FROM license
                WHERE resource=:resourceId RETURNING id;
            EOD;
        $params = [];
        $params[':resourceId'] = $resource->getId();
        $statement = $this->pdo->prepare($sql);
        $success = $statement->execute($params);
        if ($success) {
            
        } else {
            
        }

        $deletedIds = $statement->fetchAll(PDO::FETCH_COLUMN);

        $sql = <<<EOD
            DELETE FROM resources_accessed
                WHERE resource=:resourceId;
            EOD;
        $params = [];
        $params[':resourceId'] = $resource->getId();
        $statement = $this->pdo->prepare($sql);
        $success = $statement->execute($params);
        if ($success) {
           
        } else {
            
        }     

        $sql = <<<EOD
            DELETE FROM resource_api
                WHERE resource=:resourceId;
            EOD;
        $params = [];
        $params[':resourceId'] = $resource->getId();
        $statement = $this->pdo->prepare($sql);
        $success = $statement->execute($params);
        if ($success) {
            
        } else {
            
        } 

        if ($isSuperAdmin) {
            $sql = <<<EOD
                DELETE FROM resource
                    WHERE id=:resourceId;
                EOD;
            $params = [];
            $params[':resourceId'] = $resource->getId();
            $statement = $this->pdo->prepare($sql);
            $success = $statement->execute($params);
            if ($success) {
               
            } else {
                
            } 
        } else {
            $sql = <<<EOD
                DELETE FROM resource
                    WHERE id=:resourceId and created_by=:createdBy;
                EOD;
            $params = [];
            $params[':resourceId'] = $resource->getId();
            $params[':createdBy'] = $localOrganizationId;
            $statement = $this->pdo->prepare($sql);
            $success = $statement->execute($params);
            if ($success) {
            
            } else {
                
            } 
        }
    }

    public function updateResource(
        Resource $resourceGlobal,
        string $localOrganizationId = null,
        Resource $resourceLocal = null,
        Resource $previousResourceGlobal = null
    ): void {
        $this->updateResourceGlobal($resourceGlobal, $previousResourceGlobal);

        if (isset($localOrganizationId) && isset($resourceLocal)) {
            $this->updateResourceLocal($resourceLocal, $localOrganizationId);
        }
    }

    public function updateResourceGlobal(Resource $resource, Resource $previousResourceGlobal = null)
    {
        $sql = <<<EOD
            UPDATE resource SET
                title=:title,
                description_short=:description_short,
                description=:description,
                report_time_start=:report_time_start,
                report_time_end=:report_time_end,
                publication_time_start=:publication_time_start,
                publication_time_end=:publication_time_end,
                is_still_updated=:is_still_updated,
                update_frequency=:update_frequency,
                shelfmark=:shelfmark,
                note=:note,
                isbn_issn=:isbn_issn,
                instructions=:instructions,
                is_visible=:is_visible,
                is_free=:is_free
            WHERE id=:id;
        EOD;

        $params = $this->entityToAssocResource($resource);

        // As global resource does not has a local note
        unset($params[":local_note"]);

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        // store dependent licenses
        // $this->updateLicenses($resource);

        $this->persistKeywordsForResource($resource);

        $this->persistAuthorsForResource($resource);

        $this->persistSubjectsForResource($resource);

        $this->persistTypesForResource($resource);

        // $this->persistTopResourceEntriesForResource($resource);

        $this->persistAlternativeTitlesForResource($resource);

        $this->persistApiUrlsForResource($resource);

        $this->persistCountriesForResource($resource);

        $this->persistExternalIdsForResource($resource);

        if ($previousResourceGlobal && !is_null($previousResourceGlobal)) {
            if ($previousResourceGlobal->isVisible() !== $resource->isVisible()) {
                $this->mailToUpdateMailingListBecauseOfVisiblityChange($resource);
            }
        }
        
    }

    public function updateResourceLocal(Resource $resource, string $localOrganizationId = null)
    {
        // PostgreSQL has no "REPLACE INTO", use ON CONFLICT instead
        $sql = <<<EOD
            INSERT INTO resource_localisation
                (title, description_short, description, report_time_start, report_time_end,
                    publication_time_start, publication_time_end, is_still_updated, update_frequency, 
                    shelfmark, note, isbn_issn, local_note, instructions, is_visible, resource, organisation)
                VALUES (:title, :description_short, :description, :report_time_start, :report_time_end, 
                    :publication_time_start, :publication_time_end, :is_still_updated, :update_frequency, 
                        :shelfmark, :note, :isbn_issn, :local_note, :instructions, :is_visible, :id, :organisation)
                            
            ON CONFLICT (resource, organisation)
            DO UPDATE SET
                title=:title,
                description_short=:description_short,
                description=:description,
                report_time_start=:report_time_start,
                report_time_end=:report_time_end,
                publication_time_start=:publication_time_start,
                publication_time_end=:publication_time_end,
                is_still_updated=:is_still_updated,
                update_frequency=:update_frequency,
                shelfmark=:shelfmark,
                note=:note,
                isbn_issn=:isbn_issn,
                local_note=:local_note,
                instructions=:instructions,
                is_visible=:is_visible,
                resource=:id,
                organisation=:organisation;
        EOD;

        $params = $this->entityToAssocResource($resource);

        // Remove unnecessarry field is_free, as only global resources have it
        unset($params[':is_free']);
        /*
        $params = array_map(function ($param) {
            $result = null;
            if (isset($param) && $param != "" && $param != '{"de":"","en":""}') {
                $result = $param;
            }
            return $result;
        }, $params);
        */
        $params['organisation'] = $localOrganizationId;

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        // store dependent licenses
        // $this->updateLicenses($resource, $localOrganizationId);

        $this->persistKeywordsForResource($resource, $localOrganizationId);

        $this->persistAuthorsForResource($resource, $localOrganizationId);

        $this->persistSubjectsForResource($resource, $localOrganizationId);

        $this->persistTypesForResource($resource, $localOrganizationId);

        // $this->persistTopResourceEntriesForResource($resource, $localOrganizationId);

        $this->persistCountriesForResource($resource, $localOrganizationId);
    }

    //
    //
    // Update Keywords
    private function persistKeywordsForResource(Resource $resource, string $localOrganizationId = null): void
    {
        $this->clearKeywordsForResource($resource, $localOrganizationId);
        
        if (is_null($localOrganizationId)) {
            foreach ($resource->getKeywords() as $keyword) {
                $keywordId = $this->createKeyword($keyword);
    
                $sql = "INSERT INTO keyword_for_resource (keyword, resource) "
                    . " VALUES (:keywordId, :resourceId);";
                $statement = $this->pdo->prepare($sql);
                $statement->execute([
                    "keywordId" => $keywordId,
                    "resourceId" => $resource->getId()
                ]);
            }
        } else {
            foreach ($resource->getKeywords() as $keyword) {
                $keywordId = $this->createKeyword($keyword);
    
                $sql = "INSERT INTO keyword_for_resource (keyword, resource, organisation) "
                    . " VALUES (:keywordId, :resourceId, :organization);";
                $statement = $this->pdo->prepare($sql);
                $statement->execute([
                    "keywordId" => $keywordId,
                    "resourceId" => $resource->getId(),
                    "organization" => $localOrganizationId
                ]);
            }
        }
    }

    private function clearKeywordsForResource(Resource $resource, string $localOrganizationId = null): void
    {
        $params = array();

        if (isset($localOrganizationId)) {
            $sql = "DELETE FROM keyword_for_resource WHERE resource=:id AND organisation=:organisation";
            $params['organisation'] = $localOrganizationId;
        } else {
            $sql = "DELETE FROM keyword_for_resource WHERE resource=:id AND organisation IS NULL";
        }
        $params['id'] = $resource->getId();
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }

    private function createKeyword(Keyword $keyword): int
    {
        $sql = <<<EOD
            INSERT INTO keyword (
                title,
                external_id,
                keyword_system
            )  VALUES (
                :title,
                :external_id,
                :keyword_system
            );
        EOD;
        $params = $this->entityToAssocKeyword($keyword);
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateKeyword(Keyword $keyword): void
    {
        $sql = <<<EOD
            UPDATE keyword 
                SET title=:title,
                external_id=:external_id,
                keyword_system=:keyword_system
            WHERE
                id=:keyword_id;
        EOD;

        $params = $this->entityToAssocKeyword($keyword);
        $params['keyword_id'] = $keyword->getId();

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }

    private function persistTopResourceEntriesForResource(Resource $resource, string $localOrganizationId = null): void
    {
        // TODO: Why gets the resource deleted from all subjects as top resource?
        $this->clearTopResourceEntriesForResource($resource, $localOrganizationId);
        foreach ($resource->getTopResourceEntries() as $entry) {
            $this->createTopResourceEntry($entry);
        }
    }

    public function setTopEntryForSubject($resourceId, $subjectId, $index, $orgId) {
        
        if (!$this->topResorceAlreadyExistsForSubject($resourceId, $subjectId, $orgId)) {
            $params = array();

            $sql = "INSERT INTO top_resource_for_subject (resource, subject, organization, sort_order) "
                . " VALUES (:resource, :subject, :organization, :order);";
            $statement = $this->pdo->prepare($sql);
            $params[':resource'] = $resourceId;
            $params[':subject'] = $subjectId;
            $params[':organization'] = $orgId;
            $params[':order'] = $index;
            $statement->execute($params);
        }
    }

    public function setTopEntryForCollection($resourceId, $collectionId, $index, $orgId) {
        
        if (!$this->topResorceAlreadyExistsForCollection($resourceId, $collectionId, $orgId)) {
            $params = array();

            $sql = "INSERT INTO top_resource_for_collection (resource, collection, organization, sort_order) "
                . " VALUES (:resource, :collection, :organization, :order);";
            $statement = $this->pdo->prepare($sql);
            $params[':resource'] = $resourceId;
            $params[':collection'] = $collectionId;
            $params[':organization'] = $orgId;
            $params[':order'] = $index;
            $statement->execute($params);
        }
    }

    private function clearTopResourceEntriesForResource(Resource $resource, string $localOrganizationId = null): void
    {
        $params = array();

        if (isset($localOrganizationId)) {
            $sql = "DELETE FROM top_resource_for_subject "
                . "WHERE resource=:resource "
                . "AND organization=:organization";
            $params[':organization'] = $localOrganizationId;
        } else {
            $sql = "DELETE FROM top_resource_for_subject "
                . "WHERE resource=:resource "
                . "AND organization IS NULL";
        }
        $params[':resource'] = $resource->getId();
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }

    public function clearTopResourceEntriesForSubject(Subject $subject, string $localOrganizationId = null): void
    {
        $params = array();

        if (isset($localOrganizationId)) {
            $sql = "DELETE FROM top_resource_for_subject "
                . "WHERE organization=:organization "
                . "AND subject=:subject";
            $params[':organization'] = $localOrganizationId;
        } else {
            $sql = "DELETE FROM top_resource_for_subject "
                . "WHERE organization IS NULL "
                . "AND subject=:subject";
        }
        $params[':subject'] = $subject->getId();
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }

    public function clearTopResourceEntriesForCollection(?Collection $collection, $orgId)
    {
        $sql = "DELETE FROM top_resource_for_collection "
            . "WHERE organization=:organization "
            . "AND collection=:collection";
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            ":organization" => $orgId,
            ":collection" => $collection->getId()
        ]);
    }

    private function topResorceAlreadyExistsForSubject($resourceId, $subjectId, $organization) {
        $query = "SELECT id FROM top_resource_for_subject WHERE resource = :resource AND subject = :subject AND organization = :organization";
    
        // Prepare the statement
        $stmt = $this->pdo->prepare($query);
        
        // Bind the values to the query
        $stmt->bindParam(':resource', $resourceId);
        $stmt->bindParam(':subject', $subjectId);
        $stmt->bindParam(':organization', $organization);
        
        // Execute the query
        $stmt->execute();
        
        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return true;
        } else {
            return false;
        }
    }

    private function topResorceAlreadyExistsForCollection($resourceId, $collectionId, $organization) {
        $query = "SELECT id FROM top_resource_for_collection WHERE resource = :resource AND collection = :collection AND organization = :organization";
    
        // Prepare the statement
        $stmt = $this->pdo->prepare($query);
        
        // Bind the values to the query
        $stmt->bindParam(':resource', $resourceId);
        $stmt->bindParam(':collection', $collectionId);
        $stmt->bindParam(':organization', $organization);
        
        // Execute the query
        $stmt->execute();
        
        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return true;
        } else {
            return false;
        }
    }

    private function createTopResourceEntry(TopResourceEntry $entry): void
    {   
        $sql = null;
        if ($entry->isCollection()) {
            $organization = $entry->getOrganizationId();
            $collectionId = $entry->getSubject()->getId();
            $resourceId = $entry->getResourceId();

            if (!$this->topResorceAlreadyExistsForCollection($resourceId, $collectionId, $organization)) {
                $sql = "INSERT INTO top_resource_for_collection (resource, collection, organization, sort_order) "
                . " VALUES (:resource, :collection, :organization, :order);";
            }   
        } else {
            $organization = $entry->getOrganizationId();
            $subjectId = $entry->getSubject()->getId();
            $resourceId = $entry->getResourceId();

            if (!$this->topResorceAlreadyExistsForSubject($resourceId, $subjectId, $organization)) {
                $sql = "INSERT INTO top_resource_for_subject (resource, subject, organization, sort_order) "
                    . " VALUES (:resource, :subject, :organization, :order);";
            }
        }

        if (!is_null($sql)) {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($this->entityToAssocTopResourceEntry($entry));
        }
    }

    private function persistApiUrlsForResource(Resource $resource)
    {
        $apiUrls = $resource->getApiUrls();

        $this->clearApiUrlsForResource($resource);

        foreach ($apiUrls as $apiUrl) {
            $this->persistApiUrlForResource($apiUrl, $resource);
        }
    }


    private function clearApiUrlsForResource(Resource $resource)
    {
        $params = array();

        $sql = "DELETE FROM resource_api WHERE resource=:resource";
        $params[':resource'] = $resource->getId();
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }

    private function persistApiUrlForResource(Url $apiUrl, Resource $resource)
    {
        $sql = "INSERT INTO resource_api (resource, url) "
            . " VALUES (:resource, :url);";
        $statement = $this->pdo->prepare($sql);

        $success = $statement->execute([
            ":resource" => $resource->getId(),
            ":url" => $apiUrl->getUrl()
        ]);
    }


    // Alternative Titles
    private function persistAlternativeTitlesForResource(Resource $resource)
    {
        $this->clearAlternativeTitlesForResource($resource);

        $altTitles = $resource->getAlternativeTitles();

        foreach ($altTitles as $at) {
            $this->persistAlternativeTitleForResource($at, $resource);
        }
    }

    private function persistAlternativeTitleForResource(
        AlternativeTitle $altTitle,
        Resource $resource
    ) {
        $sql = "INSERT INTO alternative_title (resource, title, valid_from_date, valid_to_date) "
            . " VALUES (:resource, :title, :valid_from_date, :valid_to_date);";
        $statement = $this->pdo->prepare($sql);

        $validFromDate = $altTitle->getValidFromDate() ?
            date_format($altTitle->getValidFromDate(), "Y-m-d H:i:s") : null;
        $validToDate = $altTitle->getValidToDate() ?
            date_format($altTitle->getValidToDate(), "Y-m-d H:i:s") : null;

        $success = $statement->execute([
            ":resource" => $resource->getId(),
            ":title" => $altTitle->getTitle(),
            ":valid_from_date" => $validFromDate,
            ":valid_to_date" => $validToDate
        ]);
    }

    private function clearAlternativeTitlesForResource(Resource $resource)
    {
        $params = array();

        $sql = "DELETE FROM alternative_title WHERE resource=:resource";
        $params[':resource'] = $resource->getId();
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }

    // Authors
    private function persistAuthorsForResource(Resource $resource, string $localOrganizationId = null)
    {
        
        $authors = $resource->getAuthors();

        $this->clearAuthorsForResource($resource, $localOrganizationId);

        if (!is_null($localOrganizationId)) {
            foreach ($authors as $author) {
                $authorId = $author->getId() ? $author->getId() : $this->createAuthor($author);
                $sql = "INSERT INTO author_for_resource (author,resource,organisation) "
                    . " VALUES (:author,:resource,:organisation);";
    
                $statement = $this->pdo->prepare($sql);
    
                $statement->execute([
                    "author" => $authorId,
                    "resource" => $resource->getId(),
                    "organisation" => $localOrganizationId
                ]);
            }
        } else {
            foreach ($authors as $author) {
                $authorId = $author->getId() ? $author->getId() : $this->createAuthor($author);
                $sql = "INSERT INTO author_for_resource (author,resource) "
                    . " VALUES (:author,:resource);";
    
                $statement = $this->pdo->prepare($sql);
    
                $statement->execute([
                    "author" => $authorId,
                    "resource" => $resource->getId()
                ]);
            }
        }
    }

    private function clearAuthorsForResource(Resource $resource, string $localOrganizationId = null)
    {
        $params = array();

        if (isset($localOrganizationId) && !is_null($localOrganizationId)) {
            $sql = "DELETE FROM author_for_resource WHERE resource=:id AND organisation=:organisation;";
            $params['organisation'] = $localOrganizationId;
        } else {
            $sql = "DELETE FROM author_for_resource WHERE resource=:id AND organisation IS NULL;";
        }
        $params['id'] = $resource->getId();
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }


    /**
     * Notice, 2.02.2022
     *
     * Added a check by name, so that authors with the same name will not be
     * created as doubles
     */
    private function createAuthor(Author $author): int
    {
        $id = $this->existsAuthor($author);
        if ($id) {
            return $id;
        }

        $sql = "INSERT INTO author (title) VALUES(:title)";
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            "title" => $author->getTitle()
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * This will return the id of the author, if existing
     * @param Author $author
     * @return int
     */
    private function existsAuthor(Author $author): ?int
    {
        $title = $author->getTitle();
        $sql = "SELECT * FROM author WHERE author.title LIKE :title";
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            "title" => $title
        ]);

        if ($statement->rowCount() > 0) {
            return $statement->fetch(PDO::FETCH_ASSOC)['id'];
        } else {
            return null;
        }
    }

    //
    //
    // Subjects
    private function persistSubjectsForResource(Resource $resource, string $localOrganizationId = null): void
    {
        $subjects = $resource->getSubjects();

        if (!is_null($localOrganizationId)) {
            $this->clearSubjectsForResource($resource, $localOrganizationId);
        }

        if (count($subjects) > 0) {
            if (is_null($localOrganizationId)) {
                $this->clearSubjectsForResource($resource);
            }

            if (!is_null($localOrganizationId)) {
                foreach ($subjects as $subject) {
                    $subjectId = $subject->getId() ? $subject->getId() : $this->createSubject($subject);
                    $sql = "INSERT INTO subject_for_resource (subject,resource,organisation) "
                        . " VALUES (:subject,:resource,:organisation);";
        
                    $statement = $this->pdo->prepare($sql);
        
                    $statement->execute([
                        "subject" => $subjectId,
                        "resource" => $resource->getId(),
                        "organisation" => $localOrganizationId
                    ]);
                }
            } else {
                foreach ($subjects as $subject) {
                    $subjectId = $subject->getId() ? $subject->getId() : $this->createSubject($subject);
                    $sql = "INSERT INTO subject_for_resource (subject,resource) "
                        . " VALUES (:subject,:resource);";
        
                    $statement = $this->pdo->prepare($sql);
        
                    $statement->execute([
                        "subject" => $subjectId,
                        "resource" => $resource->getId()
                    ]);
                }
            }
        }        
    }

    private function clearSubjectsForResource(Resource $resource, string $localOrganizationId = null): void
    {
        $params = array();

        if (isset($localOrganizationId)) {
            $sql = "DELETE FROM subject_for_resource WHERE resource=:id AND organisation=:organisation;";
            $params['organisation'] = $localOrganizationId;
        } else {
            $sql = "DELETE FROM subject_for_resource WHERE resource=:id AND organisation IS NULL;";
        }
        $params['id'] = $resource->getId();
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }

    private function createSubject(Subject $subject): int
    {
        $sql = "INSERT INTO subject (title) VALUES(:title)";
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            "title" => json_encode($subject->getTitle())
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    //
    //
    // Enterprise
    private function createEnterprise(Enterprise $enterprise): Enterprise
    {
        $sql = "INSERT INTO enterprise (title) VALUES (:title);";
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            "title" => $enterprise->getTitle()
        ]);
        $id = (int)$this->pdo->lastInsertId();
        $enterprise->setId($id);
        return $enterprise;
    }

    // Country
    private function persistCountriesForResource(Resource $resource, string $localOrganizationId = null): void
    {

        $this->clearCountriesForResource($resource, $localOrganizationId);
        
        if (!is_null($localOrganizationId)) {
            foreach ($resource->getCountries() as $country) {
                $countryId = $country->getId() ? $country->getId() :
                    $this->createCountry($country);
                $sql = "INSERT INTO country_for_resource (country, resource, organisation) "
                    . " VALUES (:countryId, :resourceId, :organisationId);";
                $statement = $this->pdo->prepare($sql);
                $statement->execute([
                    "countryId" => $countryId,
                    "resourceId" => $resource->getId(),
                    "organisationId" => $localOrganizationId
                ]);
            }
        } else {
            foreach ($resource->getCountries() as $country) {
                $countryId = $country->getId() ? $country->getId() :
                    $this->createCountry($country);
                $sql = "INSERT INTO country_for_resource (country, resource) "
                    . " VALUES (:countryId, :resourceId);";
                $statement = $this->pdo->prepare($sql);
                $statement->execute([
                    "countryId" => $countryId,
                    "resourceId" => $resource->getId()
                ]);
            }
        }
    }

    private function clearCountriesForResource(Resource $resource, string $localOrganizationId = null): void
    {
        $params = array();

        if (isset($localOrganizationId)) {
            $sql = "DELETE FROM country_for_resource WHERE resource=:id AND organisation=:organisation";
            $params['organisation'] = $localOrganizationId;
        } else {
            $sql = "DELETE FROM country_for_resource WHERE resource=:id AND organisation IS NULL";
        }
        $params['id'] = $resource->getId();
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }

    private function createCountry(Country $country): int
    {
        $sql = <<<EOD
            INSERT INTO country (
                title,
                code
            )  VALUES (
                :title,
                :code
            );
        EOD;
        $params = $this->entityToAssocCountry($country);
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    //
    //
    // Type

    private function persistTypesForResource(Resource $resource, string $localOrganizationId = null): void
    {
        $types = $resource->getTypes();

        if (!is_null($localOrganizationId)) {
            $this->clearTypesForResource($resource, $localOrganizationId);
        }

        if (count($types) > 0) {
            if (is_null($localOrganizationId)) {
                $this->clearTypesForResource($resource);
            }
            
            if (!is_null($localOrganizationId)) {
                foreach ($types as $type) {
                    $typeId = $type->getId();
                    $sql = "INSERT INTO resource_type_for_resource (resource_type,resource,organisation) "
                        . "VALUES (:type,:resource,:organisation);";
    
                    $statement = $this->pdo->prepare($sql);
    
                    $statement->execute([
                        "type" => $typeId,
                        "resource" => $resource->getId(),
                        "organisation" => $localOrganizationId
                    ]);
                }
            } else {
                foreach ($types as $type) {
                    $typeId = $type->getId();
                    $sql = "INSERT INTO resource_type_for_resource (resource_type,resource) "
                        . "VALUES (:type,:resource);";
    
                    $statement = $this->pdo->prepare($sql);
    
                    $statement->execute([
                        "type" => $typeId,
                        "resource" => $resource->getId()
                    ]);
                }
            }
        }
    }

    private function persistExternalIdsForResource(Resource $resource): void
    {
        $external_ids = $resource->getExternalIDs();

        $this->clearExternalIdsForResource($resource);

        foreach ($external_ids as $external_id_obj) {
            $external_id = $external_id_obj->getId();
            $namespace = $external_id_obj->getNamespace();
            $id_name = $external_id_obj->getIdName();
            $sql = "INSERT INTO external_resource_id (external_id,resource,namespace,external_id_name) "
                . "VALUES (:external_id,:resource,:namespace,:external_id_name);";

            $statement = $this->pdo->prepare($sql);

            $statement->execute([
                "external_id" => $external_id,
                "resource" => $resource->getId(),
                "namespace" => $namespace,
                "external_id_name" => $id_name
            ]);
        }
    }

    private function persistExternalIdsForLicense(License $license): void
    {
        $this->clearExternalIdsForLicense($license);

        $external_ids = $license->getExternalIDs();
        foreach ($external_ids as $external_id_obj) {
            $external_id = $external_id_obj->getId();
            $namespace = $external_id_obj->getNamespace();
            $id_name = $external_id_obj->getIdName();
            $sql = "INSERT INTO external_license_id (external_id,license,namespace,external_id_name) "
                . "VALUES (:external_id,:license,:namespace,:external_id_name);";

            $statement = $this->pdo->prepare($sql);

            $statement->execute([
                "external_id" => $external_id,
                "license" => $license->getId(),
                "namespace" => $namespace,
                "external_id_name" => $id_name
            ]);
        }
    }

    private function clearTypesForResource(Resource $resource, string $localOrganizationId = null): void
    {
        $params = array();

        if (isset($localOrganizationId)) {
            $sql = "DELETE FROM resource_type_for_resource WHERE resource=:id AND organisation=:organisation;";
            $params['organisation'] = $localOrganizationId;
        } else {
            $sql = "DELETE FROM resource_type_for_resource WHERE resource=:id AND organisation IS NULL;";
        }
        $params['id'] = $resource->getId();
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }

    private function createType(Type $type): int
    {
        /* TODO: review
        $sql = "SELECT "
                . "resource.*, "
                . "JSON_AGG(to_json(l.*)) as licenses, "
                . "JSON_AGG(to_json(rt.*)) as types "
                . "FROM resource "
                . "LEFT JOIN ( "
                . "   SELECT license.id as license_id, "
                . "     license.*, "
                . "     to_json(lt.*) as type, "
                . "     to_json(lf.*) as form, "
                . "     JSON_AGG(to_json(a.*)) as accesses "
                . "   FROM license "
                . "     LEFT JOIN license_type as lt "
                . "         ON lt.id=license.type "
                . "     LEFT JOIN license_form as lf "
                . "         ON lf.id=license.form "
                . "     LEFT JOIN (SELECT access.*, "
                . "             to_json(at.*) as type, "
                . "             to_json(p.*) as host "
                . "         FROM access "
                . "         LEFT JOIN host AS p "
                . "             ON p.id=access.host "
                . "         LEFT JOIN access_type AS at "
                . "             ON at.id=access.type) AS a "
                . "         ON a.license=license.id "
                . "     WHERE %BLOCK_ORG_LICENSES% "
                . "     GROUP BY license.id, lt.id, lf.id "
                . "     ) AS l ON l.resource=resource.id "

                . "LEFT JOIN resource_type_for_resource as rtr "
                . "     ON rtr.resource=resource.id "
                . "     INNER JOIN resource_type as rt"
                . "         ON rt.id = rtr.resource_type "
                . "WHERE resource.id = :id "
                . "GROUP BY resource.id ";
        $params = [':id' => $id];

        // Handle retrieving a repository as viewed from an organization
        if ($localOrganizationId) {
            $params[':orgId'] = $localOrganizationId;
            $sql = str_replace("%BLOCK_ORG_LICENSES%", "organisation IS NULL OR organisation=:orgId", $sql);
        } else {
            $sql = str_replace("%BLOCK_ORG_LICENSES%", "organisation IS NULL", $sql);
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
           "title" => json_encode($type->getTitle()),
           "description" => json_encode($type->getDescription())
        ]);

        return (int)$this->pdo->lastInsertId();
        */

        $sql = "INSERT INTO resource_type (title, description) VALUES (:title, :description)";
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            "title" => json_encode($type->getTitle()),
            "description" => json_encode($type->getDescription())
        ]);

        return (int)$this->pdo->lastInsertId();
    }


    public function getUpdateFrequencies(): array
    {
        $sql = "SELECT * FROM update_frequency;";

        $statement = $this->pdo->prepare($sql);

        $statement->execute();

        $update_frequencies_assoc = $statement->fetchAll(PDO::FETCH_ASSOC);

        $update_frequencies_obj = array();

        foreach ($update_frequencies_assoc as $entry) {
            $entry['title'] = json_decode($entry['title'], true);
            array_push($update_frequencies_obj, $this->assocToEntityUpdateFrequency($entry));
        }

        return $update_frequencies_obj;
    }

    public function setAccessesUrlState($accesId, $newState) {
        $sql = <<<EOD
            UPDATE access SET
                state=:state
            WHERE id=:id;
        EOD;

        $params = [
            ":state" => $newState,
            ":id" => $accesId
        ];

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }

    public function getAccessesWithInvalidUrls($ubrId, $offset, $limit): array
    {
        $sql = "select
                    access.*
                from
                    access
                left join license_for_organization on
                    license_for_organization.license = access.license
                where
                    access.state = 'invalid Url'
                    and (access.organization = '$ubrId'
                        or access.organization is null)
                    and (license_for_organization.organization = '$ubrId') limit $limit offset $offset;";

        $statement = $this->pdo->prepare($sql);
        $statement->execute();

        $invalid_urls_assoc = $statement->fetchAll(PDO::FETCH_ASSOC);

        $invalid_urls_obj = array();

        foreach ($invalid_urls_assoc as $entry) {
            if ($entry['description']) {
                $entry['description'] = json_decode($entry['description'], true);
            }
            
            if ($entry['label']) {
                $entry['label'] = json_decode($entry['label'], true);
            }
            
            if ($entry['requirements']) {
                $entry['requirements'] = json_decode($entry['requirements'], true);
            }
            

            $licenseId = (int) $entry['license'];

            $sql = "select license.resource from license where license.id = $licenseId;";
            $statement = $this->pdo->prepare($sql);
            $statement->execute();

            $result = $statement->fetch(PDO::FETCH_ASSOC);
            $resourceId = (int) $result['resource'];

            $resource = $this->getResourceById_NEW($resourceId);

            $access = $this->assocToEntityAccess($entry);
            $access->setResource($resource);

            array_push($invalid_urls_obj, $access);
        }

        return $invalid_urls_obj;
    }

    public function getLicenseForms($options): array
    {
        $is_fid = isset($options['isFID']) ? $options['isFID'] == true : true;
        $is_consortium = isset($options['isConsortium']) ? $options['isConsortium'] == true : true;
        $allowed_for_nl = isset($options['allowedForNL']) ? $options['allowedForNL'] == true : true;

        $sql = "SELECT * FROM license_form ORDER BY id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute();
        $licenseFormsAssoc = $statement->fetchAll(PDO::FETCH_ASSOC);
        $licenseForms = array_map(function ($assoc) {
            return new LicenseForm(
                $assoc['id'],
                $assoc['title'] ? json_decode($assoc['title'], true) : null,
                $assoc['description'] ? json_decode($assoc['description'], true) : null
            );
        }, $licenseFormsAssoc);

        $filteredLicenseForms = array();

        foreach ($licenseForms as &$license_form) {
            $id = $license_form->getId();

            if ($id == 31) {
                if ($allowed_for_nl) {
                    $filteredLicenseForms[] = $license_form;
                } 

                continue;
            }

            if ($id == 41 || $id == 42 || $id == 43) {
                if ($is_fid) {
                    $filteredLicenseForms[] = $license_form;
                } 

                continue;
            }

            $filteredLicenseForms[] = $license_form;
        }

        return $filteredLicenseForms;
    }

    public function getLicenseTypes(array $options = []): array
    {
        $is_fid = isset($options['isFID']) ? $options['isFID'] == true : true;
        $is_consortium = isset($options['isConsortium']) ? $options['isConsortium'] == true : true;
        $allowed_for_nl = isset($options['allowedForNL']) ? $options['allowedForNL'] == true : true;

        $sql = "SELECT * FROM license_type";
        if (isset($options['onlyGlobal']) && ['onlyGlobal']) {
            $sql .= " WHERE is_global = true ORDER BY id";
        } else {
            $sql .= " ORDER BY id";
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute();
        $licenseTypesAssoc = $statement->fetchAll(PDO::FETCH_ASSOC);
        $licenseTypes = array_map(function ($assoc) {
            $type = new LicenseType(
                $assoc['id'],
                $assoc['title'] ? json_decode($assoc['title'], true) : null,
                $assoc['description'] ? json_decode($assoc['description'], true) : null
            );
            $type->setGlobal($assoc['is_global']);
            return $type;
        }, $licenseTypesAssoc);

        $filteredLicenseTypes = array();

        foreach ($licenseTypes as &$license_type) {
            $id = $license_type->getId();

            if ($id == 3) {
                if ($allowed_for_nl) {
                    $filteredLicenseTypes[] = $license_type;
                } 
                
                continue;
            }

            if ($id == 4) {
                if ($is_fid) {
                    $filteredLicenseTypes[] = $license_type;
                } 

                continue;
            }

            if ($id == 5) {
                if ($is_consortium) {
                    $filteredLicenseTypes[] = $license_type;
                } 

                continue;
            }

            $filteredLicenseTypes[] = $license_type;
        }

        return $filteredLicenseTypes;
    }

    /**
     *
     * @return AccessType[]
     */
    public function getAccessTypes(): array
    {
        $sql = "SELECT * FROM access_type";
        $statement = $this->pdo->prepare($sql);
        $statement->execute();
        $accessTypesAssoc = $statement->fetchAll(PDO::FETCH_ASSOC);
        $accessTypes = array_map(function ($assoc) {
            $type = new AccessType(
                $assoc['id'],
                json_decode($assoc['title'], true)
            );
            return $type;
        }, $accessTypesAssoc);
        return $accessTypes;
    }

        /**
     *
     * @return AccessForm[]
     */
    public function getAccessForms(): array
    {
        $sql = "SELECT * FROM access_form";
        $statement = $this->pdo->prepare($sql);
        $statement->execute();
        $accessFormsAssoc = $statement->fetchAll(PDO::FETCH_ASSOC);
        $accessForms = array_map(function ($assoc) {
            $type = new AccessForm(
                $assoc['id'],
                json_decode($assoc['title'], true)
            );
            return $type;
        }, $accessFormsAssoc);
        return $accessForms;
    }

    /**
     * @return Host[]
     */
    public function getHosts(): array
    {
        $sql = "SELECT * FROM host";
        $statement = $this->pdo->prepare($sql);
        $statement->execute();
        $hostsAssoc = $statement->fetchAll(PDO::FETCH_ASSOC);
        $hosts = array_map(
            function ($entry): Host {
                return $this->assocToEntityHost($entry);
            },
            $hostsAssoc
        );
        return $hosts;
    }


    public function getHostByName(string $name, string $language = null): ?Host
    {
        $sql = "SELECT * FROM host ";

        $sql .= "WHERE title = :name::TEXT";

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            ":name" => $name
        ]);
        $hostsAssoc = $statement->fetchAll(PDO::FETCH_ASSOC);

        $hosts = array_map(
            function ($entry): Host {
                return $this->assocToEntityHost($entry);
            },
            $hostsAssoc
        );
        return count($hosts) > 0 ? $hosts[0] : null;
    }

    public function getHostById(int $id): ?Host
    {
        $sql = "SELECT * FROM host WHERE id=:id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            ":id" => $id
        ]);

        $hostsAssoc = $statement->fetchAll(PDO::FETCH_ASSOC);

        $hosts = array_map(
            function ($entry): Host {
                return $this->assocToEntityHost($entry);
            },
            $hostsAssoc
        );
        return count($hosts) > 0 ? $hosts[0] : null;
    }

    /**
     * @return Enterprise[]
     */
    public function getEnterprises(array $options = null): array
    {

        $params = [];
        $q = $options['q'] ?? null;
        $id = $options['id'] ?? null;
        $pagesize = $options['ps'] ?? null;

        $sql = "SELECT * FROM enterprise WHERE TRUE ";

        if ($q) {
            // '%' just is a wildcard for any char or none
            $sql .= " AND LOWER(title) LIKE LOWER('%' || :q || '%') ";
            $params['q'] = $q;
        }

        if ($id) {
            $sql .= " AND id = :id";
            $params['id'] = $id;
        }

        if ($pagesize) {
            $sql .= " LIMIT :ps ";
            $params['ps'] = $pagesize;
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $enterprisesAssoc = $statement->fetchAll(PDO::FETCH_ASSOC);
        $enterprises = array_map(
            function ($entry) {
                return $this->assocToEntityEnterprise($entry);
            },
            $enterprisesAssoc
        );
        return $enterprises;
    }

    public function getEnterpriseById(int $id): ?Enterprise
    {
        $sql = "SELECT * FROM enterprise WHERE id=:id";
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            ":id" => $id
        ]);

        $enterprisesAssoc = $statement->fetchAll(PDO::FETCH_ASSOC);

        $enterprises = array_map(
            function ($entry): Enterprise {
                return $this->assocToEntityEnterprise($entry);
            },
            $enterprisesAssoc
        );
        return count($enterprises) > 0 ? $enterprises[0] : null;
    }

    public function getAccessMapping(?string $dbis_id, int $resource_id): ?AccessMapping
    {
        if (!$dbis_id) {
            $dbis_id = 'alle_test';
        }

        $sql = "SELECT * FROM access_mapping WHERE bib_id=:bib_id AND titel_id=:titel_id;";
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            ":bib_id" => $dbis_id,
            ":titel_id" => $resource_id
        ]);

        $accessMappingAssoc = $statement->fetchAll(PDO::FETCH_ASSOC);

        $accessMappings = array_map(
            function ($entry): AccessMapping {
                return $this->assocToEntityAccessMapping($entry);
            },
            $accessMappingAssoc
        );
        return count($accessMappings) > 0 ? $accessMappings[0] : null;
    }

    public function getAllAccessMappingsForDbisId(string $dbis_id): array
    {
        // for the broken queries from USB_K
        $dbis_id = strtolower($dbis_id);

        $sql = "SELECT * FROM access_mapping WHERE bib_id=:bib_id;";
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            ":bib_id" => $dbis_id
        ]);

        $accessMappingAssoc = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            function ($entry): AccessMapping {
                return $this->assocToEntityAccessMapping($entry);
            },
            $accessMappingAssoc
        );
    }

    public function getTypes(): array
    {
        $sql = "SELECT resource_type.* as type FROM resource_type;";

        $statement = $this->pdo->prepare($sql);

        $statement->execute();
        $types_assoc = $statement->fetchAll(PDO::FETCH_ASSOC);

        $types_obj = array();

        foreach ($types_assoc as $entry) {
            $entry['title'] = json_decode($entry['title'], true);
        	$entry['description'] = json_decode($entry['description'], true);

            array_push($types_obj, $this->assocToEntityType($entry));
        }

        return $types_obj;
    }

    public function getTypeByText($text): ?Type
    {
        $sql = "SELECT resource_type.id, resource_type.* FROM resource_type WHERE ";

        // '%' just is a wildcard for any char or none
        $sql .= " LOWER(title::jsonb->>'de') = LOWER(:q) "
            . " OR LOWER(title::jsonb->>'en') = LOWER(:q) ";
        $params['q'] = $text;

        $statement = $this->pdo->prepare($sql);

        $statement->execute($params);
        $resource_type_assoc = $statement->fetch(PDO::FETCH_ASSOC);

        if ($resource_type_assoc) {
            $resource_type_assoc['title'] = json_decode($resource_type_assoc['title'], true);
            $resource_type_assoc['description'] = json_decode($resource_type_assoc['description'], true);
            return $this->assocToEntityType($resource_type_assoc);
        } else {
            return null;
        }        
    }    

    public function getPublicationFormByText($text): ?PublicationForm
    {
        $sql = "SELECT publication_form.* FROM publication_form WHERE ";
        
        // '%' just is a wildcard for any char or none
        $sql .= " LOWER(title::jsonb->>'de') = LOWER(:q) "
        . " OR LOWER(title::jsonb->>'en') = LOWER(:q) "
        . "ORDER BY id;";
        $params['q'] = $text;
    
    
        $statement = $this->pdo->prepare($sql);

        $statement->execute($params);
        $types_assoc = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($types_assoc) {
            $types_assoc[0]['title'] = json_decode($types_assoc[0]['title'], true);
            $types_assoc[0]['description'] = json_decode($types_assoc[0]['description'], true);
            return $this->assocToEntityPublicationForm($types_assoc[0]);
        } else {
            return null;
        }   

    }

    public function getPublicationForms(): array
    {
        $sql = "SELECT to_json(publication_form.*) as publication_form FROM publication_form ORDER BY id;";

        $statement = $this->pdo->prepare($sql);

        $statement->execute();
        $types_assoc = $statement->fetchAll(PDO::FETCH_ASSOC);

        $types_obj = array();

        foreach ($types_assoc as $entry) {
            $types_obj[] = $this->assocToEntityPublicationForm(json_decode($entry['publication_form'], true));
        }

        return $types_obj;
    }

    public function getSubjects(array $options = []): array
    {
        $sort_language = array_key_exists('sort_language', $options) ? $options['sort_language'] : "de";
        
        // $only_with_license = array_key_exists('only_with_license', $options) && $options['only_with_license'];
        // Show also unlicensed databases
        $with_license  = false;

        $without_resources = array_key_exists('without_resources', $options);

        $with_organisation = array_key_exists('organizationId', $options) ? $options['organizationId'] : null;

        /**
         * Query Explanation
         * - ORDER BY CASE
         * Puts subject "Allgemeines" in the first position, while sorting
         * by Alphabet for other items
         */

        /*
        coalesce(subject_hidden_for_organisation.subject, 0) as is_hidden
        */

        if ($without_resources) {
            if ($with_organisation) {
                $sql = "SELECT subject.*, coalesce(subject_hidden_for_organisation.subject, 0) as is_hidden FROM subject left join subject_hidden_for_organisation on subject.id = subject_hidden_for_organisation.subject and subject_hidden_for_organisation.organisation = '$with_organisation' ORDER BY (title->>:sort_language)::text";
            } else {
                $sql = "SELECT * FROM subject ORDER BY (title->>:sort_language)::text";
            }
            
        } else {
            if ($with_organisation) {
                $sql= <<<EOD
                    SELECT subject.*, title::jsonb, 
                            coalesce(json_agg(distinct sfr.resource_id) filter (where sfr.resource_id is not null), '[]') as resources,
                            coalesce(subject_hidden_for_organisation.subject, 0) as is_hidden
                        FROM 
                        subject 
                        left join subject_hidden_for_organisation on subject.id = subject_hidden_for_organisation.subject and subject_hidden_for_organisation.organisation = '$with_organisation'
                        left join (
                            select
                                resource.id as resource_id,
                                resource.is_visible as visible_global,
                                resource_localisation.is_visible,
                                subject_for_resource.subject
                            from
                                subject_for_resource
                            left join resource on
                                subject_for_resource.resource = resource.id
                            left join resource_localisation on
                                subject_for_resource.resource = resource_localisation.resource
                            where
                                (resource.is_visible = true
                                    and resource_localisation.is_visible is null)
                                or (resource_localisation.is_visible = true)
                        ) as sfr on
                            subject.id = sfr.subject
                        where
                            true
                        group by
                            subject.id,
                            subject_hidden_for_organisation.subject
                        ORDER BY CASE WHEN subject.id = 1 then 0 else 1 end, (title->>:sort_language)::text;
                EOD;
            } else {
                $sql= <<<EOD
                    SELECT subject.*, title::jsonb, 
                            coalesce(json_agg(distinct sfr.resource_id) filter (where sfr.resource_id is not null), '[]') as resources
                        FROM subject left join (
                            select
                                resource.id as resource_id,
                                resource.is_visible as visible_global,
                                resource_localisation.is_visible,
                                subject_for_resource.subject
                            from
                                subject_for_resource
                            left join resource on
                                subject_for_resource.resource = resource.id
                            left join resource_localisation on
                                subject_for_resource.resource = resource_localisation.resource
                            where
                                (resource.is_visible = true
                                    and resource_localisation.is_visible is null)
                                or (resource_localisation.is_visible = true)
                        ) as sfr on
                            subject.id = sfr.subject
                        where
                            true
                        group by
                            subject.id
                        ORDER BY CASE WHEN subject.id = 1 then 0 else 1 end, (title->>:sort_language)::text;
                EOD;
            }
        }

        $statement = $this->pdo->prepare($sql);

        $statement->execute([
            "sort_language" => $sort_language
        ]);
        $subjects_assoc = $statement->fetchAll(PDO::FETCH_ASSOC);

        $subjects_obj = array();

        foreach ($subjects_assoc as $entry) {
            $entry['title'] = json_decode($entry['title'], true);
            $subjects_obj[] = $this->assocToEntitySubject($entry);
        }

        return $subjects_obj;
    }

    public function setSubjectsVisibility($subjectIds, $organisationId) {
        $sql = <<<EOD
            DELETE FROM subject_hidden_for_organisation WHERE organisation=:organisationId;
        EOD;

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            "organisationId" => $organisationId
        ]);

        foreach ($subjectIds as &$subjectId) {
            $subjectId = (int) $subjectId;

            $sql = <<<EOD
                INSERT INTO subject_hidden_for_organisation (subject, organisation) VALUES (:subjectId, :organisationId);
            EOD;

            $statement = $this->pdo->prepare($sql);
            try {
                $statement->execute([
                    "subjectId" => $subjectId,
                    "organisationId" => $organisationId
                ]);
            } catch (\Exception $e) {

            }
        }
    }

    public function getSubjectByText(string $text): ?Subject
    {
        $sql = "SELECT subject.*, title::jsonb, "
            . "COALESCE(json_agg(DISTINCT sfr.resource) "
            . "FILTER (WHERE sfr.resource IS NOT NULL), '[]') as resources "
            . "FROM subject "
            . "LEFT JOIN subject_for_resource as sfr "
            . " ON subject.id = sfr.subject "
            . "WHERE "
            // '%' just is a wildcard for any char or none
            . " LOWER(subject.title::jsonb->>'de') = LOWER(:q) "
            . " OR LOWER(subject.title::jsonb->>'en') = LOWER(:q) "
            . "GROUP BY subject.id ";
        $statement = $this->pdo->prepare($sql);

        $statement->execute([
            "q" => $text
        ]);
        $subject_assoc = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($subject_assoc) {
            $subject_assoc[0]['title'] = json_decode($subject_assoc[0]['title'], true);
            return $this->assocToEntitySubject($subject_assoc[0]);
        } else {
            return null;
        }        
    }

    public function getSubjectById(int $id): ?Subject
    {
        $sql = "SELECT subject.*, title::jsonb, "
            . "COALESCE(json_agg(DISTINCT sfr.resource) "
            . "FILTER (WHERE sfr.resource IS NOT NULL), '[]') as resources "
            . "FROM subject "
            . "LEFT JOIN subject_for_resource as sfr "
            . " ON subject.id = sfr.subject "
            . "WHERE subject.id=:id "
            . "GROUP BY subject.id ";
        $statement = $this->pdo->prepare($sql);

        $statement->execute([
            "id" => $id
        ]);
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (count($results) > 0) {
            $results[0]['title'] = json_decode($results[0]['title'], true);
            return $this->assocToEntitySubject($results[0]);
        }
        return null;
    }

    public function getCollectionByText(string $text): ?Subject
    {
        $sql = "SELECT collection.*, title::jsonb "
            . "FROM collection "
            . "WHERE "
            // '%' just is a wildcard for any char or none
            . " LOWER(collection.title::jsonb->>'de') = LOWER(:q) "
            . " OR LOWER(collection.title::jsonb->>'en') = LOWER(:q) "
            . "GROUP BY collection.id ";
        $statement = $this->pdo->prepare($sql);

        $statement->execute([
            "q" => $text
        ]);
        $collection_assoc = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($collection_assoc) {
            $collection_assoc[0]['title'] = json_decode($collection_assoc[0]['title'], true);
            return $this->assocToEntitySubject($collection_assoc[0]);
        } else {
            return null;
        }        
    }

    /**
     *
     * @return array<integer, integer>
     */
    public function countResourcesBySubject(): array
    {
        $sql = <<<EOD
                SELECT subject.id, COUNT(*) FROM resource
                    LEFT JOIN subject_for_resource as sfr ON sfr.resource = resource.id
                    LEFT JOIN subject ON subject.id = sfr.subject
                    GROUP BY subject.id
                EOD;
        $statement = $this->pdo->prepare($sql);
        $statement->execute();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_reduce($result, function ($carry, $item) {
            $carry[(int)$item['id']] = $item['count'];
            return $carry;
        }, array());
    }

    public function getAuthors(array $options = null): array
    {

        $params = [];
        $q = $options['q'] ?? null;
        $id = $options['id'] ?? null;
        $pagesize = $options['ps'] ?? null;

        $sql = "SELECT * FROM author WHERE TRUE ";
        if ($q) {
            // '%' just is a wildcard for any char or none
            $sql .= " AND LOWER(title) LIKE LOWER('%' || :q || '%') ";
            $params['q'] = $q;
        }

        if ($id) {
            $sql .= " AND id = :id";
            $params['id'] = $id;
        }

        if ($pagesize) {
            $sql .= " LIMIT :ps ";
            $params['ps'] = $pagesize;
        }


        $statement = $this->pdo->prepare($sql);

        $statement->execute($params);
        $authors_assoc = $statement->fetchAll(PDO::FETCH_ASSOC);

        $authors_obj = array();

        foreach ($authors_assoc as $entry) {
            array_push($authors_obj, $this->assocToEntityAuthor($entry));
        }

        return $authors_obj;
    }

    public function getKeywordById(int $id): ?Keyword
    {
        $results = $this->getKeywords([
            "id" => $id
        ]);
        return $results[0] ?? null;
    }

    public function getKeywordByText(string $text): ?Keyword
    {
        $sql = "SELECT keyword.id, keyword.* FROM keyword WHERE ";

        // '%' just is a wildcard for any char or none
        $sql .= " LOWER(title::jsonb->>'de') = LOWER(:q) "
            . " OR LOWER(title::jsonb->>'en') = LOWER(:q) ";
        $params['q'] = $text;

        $statement = $this->pdo->prepare($sql);

        $statement->execute($params);
        $keyword_assoc = $statement->fetch(PDO::FETCH_ASSOC);

        if ($keyword_assoc) {
            $keyword_assoc['title'] = json_decode($keyword_assoc['title'], true);
            return $this->assocToEntityKeyword($keyword_assoc);
        } else {
            return null;
        }
    }

    public function getCountryByText(string $text): ?Country
    {
        $sql = "SELECT country.id, country.* "
            . "FROM country WHERE ";

        // '%' just is a wildcard for any char or none
        $sql .= " LOWER(title::jsonb->>'de') = LOWER(:q) "
            . " OR LOWER(title::jsonb->>'en') = LOWER(:q) ";
        $params['q'] = $text;

        $statement = $this->pdo->prepare($sql);

        $statement->execute($params);
        $country_assoc = $statement->fetch(PDO::FETCH_ASSOC);

        if ($country_assoc) {
            $country_assoc['title'] = json_decode($country_assoc['title'], true);
            return $this->assocToEntityCountry($country_assoc);
        } else {
            return null;
        }
    }

    public function getKeywords(array $options = null): array
    {
        $params = [];
        $q = $options['q'] ?? null;
        $id = $options['id'] ?? null;
        $keyword_system = $options['keyword_system'] ?? null;
        $pagesize = $options['ps'] ?? 15;
        // If true, return only tags, that have been given for at least one
        // resource
        $onlyGivenTags = $options['only_given'] ?? true;

        $sql = "SELECT keyword.id, keyword.*, "
            . "COUNT(keyword_for_resource.id) as num_occurrences "
            . "FROM keyword ";

        if ($onlyGivenTags) {
            $sql .= " INNER JOIN keyword_for_resource ON keyword_for_resource.keyword = keyword.id ";
        } else {
            $sql .= " LEFT JOIN keyword_for_resource ON keyword_for_resource.keyword = keyword.id ";
        }

        $sql .= " WHERE TRUE ";


        if ($q) {
            // '%' just is a wildcard for any char or none
            $sql .= " AND LOWER(title::jsonb->>'de') LIKE LOWER('%' || :q || '%') "
                . " OR LOWER(title::jsonb->>'en') LIKE LOWER('%' || :q || '%') ";
            $params['q'] = $q;
        }

        if ($id) {
            $sql .= " AND keyword.id = :id";
            $params['id'] = $id;
        }

        if ($keyword_system) {
            $sql .= " AND keyword_system = :keyword_system";
            $params['keyword_system'] = $keyword_system;
        }

        $sql .= " GROUP BY keyword.id ";

        if ($pagesize) {
            $sql .= " LIMIT :ps ";
            $params['ps'] = $pagesize;
        }

        $statement = $this->pdo->prepare($sql);

        $statement->execute($params);
        $keywords_assoc = $statement->fetchAll(PDO::FETCH_ASSOC);

        $keywords_obj = array();

        foreach ($keywords_assoc as $entry) {
            $entry['title'] = json_decode($entry['title'], true);
            array_push($keywords_obj, $this->assocToEntityKeyword($entry));
        }

        return $keywords_obj;
    }

    public function getUnstandardizedKeywords($options, $ubrId = null): array
    {
        $params = [];
        $pagesize = $options['ps'] ?? null;

        $sql = "select
                    resource.id,
                    resource.title,
                    keyword.title as keyword_title,
                    keyword.id as keyword_id,
                    keyword.keyword_system as keyword_system,
                    (select count(*) from keyword where keyword.keyword_system is null) as total
                from
                    keyword
                left join keyword_for_resource on
                    keyword_for_resource.keyword = keyword.id
                left join resource on
                    keyword_for_resource.resource = resource.id
                where keyword.keyword_system is null and (keyword_for_resource.organisation is null or keyword_for_resource.organisation = :ubrId)";

        $params['ubrId'] = $ubrId;

        if ($pagesize) {
            $sql .= " LIMIT :ps;";
            $params['ps'] = $pagesize;
        } else {
            $sql .= ";";
        }

        $statement = $this->pdo->prepare($sql);

        $statement->execute($params);
        $keywords_assoc = $statement->fetchAll(PDO::FETCH_ASSOC);

        $keywords = array();

        foreach ($keywords_assoc as $entry) {
            $entry['keyword_title'] = json_decode($entry['keyword_title'], true);
            $keywords[] = $entry;
        }

        return $keywords;
    }

    public function getCountryById(int $id): ?Country
    {
        $results = $this->getCountries([
            "id" => $id
        ]);
        return $results[0] ?? null;
    }

    public function getCountries(array $options = null): array
    {
        $params = [];
        $q = $options['q'] ?? null;
        $id = $options['id'] ?? null;
        $pagesize = $options['ps'] ?? null;
        // If true, return only countries, that have been given for at least one
        // resource
        $onlyGivenCountries = $options['only_given'] ?? false;

        $sql = "SELECT country.id, country.*, "
            . "COUNT(country_for_resource.id) as num_occurrences "
            . "FROM country ";

        if ($onlyGivenCountries) {
            $sql .= " INNER JOIN country_for_resource ON country_for_resource.country = country.id ";
        } else {
            $sql .= " LEFT JOIN country_for_resource ON country_for_resource.country = country.id ";
        }

        $sql .= " WHERE TRUE ";


        if ($q) {
            // '%' just is a wildcard for any char or none
            $sql .= " AND LOWER(title::jsonb->>'de') LIKE LOWER('%' || :q || '%') "
                . " OR LOWER(title::jsonb->>'en') LIKE LOWER('%' || :q || '%') ";
            $params['q'] = $q;
        }

        if ($id) {
            $sql .= " AND country.id = :id";
            $params['id'] = $id;
        }


        $sql .= " GROUP BY country.id ORDER BY country.id";

        if (isset($pagesize)) {
            $sql .= " LIMIT :ps ";
            $params['ps'] = $pagesize;
        }

        $statement = $this->pdo->prepare($sql);

        $statement->execute($params);
        $countries_assoc = $statement->fetchAll(PDO::FETCH_ASSOC);

        $countries_obj = array();

        foreach ($countries_assoc as $entry) {
            $entry['title'] = json_decode($entry['title'], true);
            array_push($countries_obj, $this->assocToEntityCountry($entry));
        }

        return $countries_obj;
    }

    public function getTopResources(array $options = [], $localOrganizationId = null): array {
        $organization_id = $localOrganizationId;
        $subject = $options['for_subject'] ?? null;
        $collection = $options['for_collection'] ?? null;

        $resources = [];

        if (is_null($subject) && is_null($collection)) {
            return $resources;
        }

        $params = [];
        $params["subject_id"] = $subject;

        if ($organization_id) {
            $params["organization_id"] = $organization_id;

            $sql = "select resource.id from resource;";

            if ($subject) {
                $sql = "select resource.id from resource left join top_resource_for_subject on resource.id = top_resource_for_subject.resource where top_resource_for_subject.organization = :organization_id and top_resource_for_subject.subject = :subject_id;";
            } elseif ($collection) {
                $sql = "select resource.id from resource left join top_resource_for_collection on resource.id = top_resource_for_collection.resource where top_resource_for_collection.organization = :organization_id and top_resource_for_collection.collection = :subject_id;";
                $params["subject_id"] = $collection;
            }
        } else {
            $sql = "select resource.id from resource;";

            if ($subject) {
                $sql = "select resource.id from resource left join top_resource_for_subject on resource.id = top_resource_for_subject.resource where top_resource_for_subject.organization is null and top_resource_for_subject.subject = :subject_id;";
            } 
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (count($results) > 0) {
            foreach($results as &$result) {
                $resource_id = (int) $result['id'];

                $resource = $this->getResourceById_NEW($resource_id, $organization_id);

                $resources[] = $resource;
            }
        }

        return $resources;
    }

    public function doesSubjectHasTopresources(array $options = [], $localOrganizationId = null): bool {
        $organization_id = $localOrganizationId;
        $subject = $options['for_subject'] ?? null;
        $collection = $options['for_collection'] ?? null;

        $resources = [];

        if (is_null($subject) && is_null($collection)) {
            return $resources;
        }

        $sql = null;

        $params = [];
        $params["subject_id"] = $subject;

        if ($organization_id) {
            $params["organization_id"] = $organization_id;

            if ($subject) {
                $sql = "select resource.id from resource left join top_resource_for_subject on resource.id = top_resource_for_subject.resource where top_resource_for_subject.organization = :organization_id and top_resource_for_subject.subject = :subject_id;";
            } elseif ($collection) {
                $sql = "select resource.id from resource left join top_resource_for_collection on resource.id = top_resource_for_collection.resource where top_resource_for_collection.organization = :organization_id and top_resource_for_collection.collection = :subject_id;";
                $params["subject_id"] = $collection;
            }
        } else {
            if ($subject) {
                $sql = "select resource.id from resource left join top_resource_for_subject on resource.id = top_resource_for_subject.resource where top_resource_for_subject.organization is null and top_resource_for_subject.subject = :subject_id;";
            } 
        }

        if ($sql) {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);

            $results = $statement->fetchAll(PDO::FETCH_ASSOC);

            return count($results) > 0;
        } else {
            return false;
        }
    }

    public function getResourcesForSubject(array $options = [], $localOrganizationId = null): array {
        $organization_id = $localOrganizationId;
        $subject = $options['for_subject'] ?? null;
        $collection = $options['for_collection'] ?? null;

        $resources = [];

        if (is_null($subject) && is_null($collection)) {
            return $resources;
        }

        $params = [];

        $params["subject_id"] = $subject;

        if ($organization_id) {
            $params["organization_id"] = $organization_id;

            $sql = "select resource.id from resource;";

            if ($subject) {
                $sql = "select resource.id from resource left join subject_for_resource on resource.id = subject_for_resource.resource where (subject_for_resource.organisation = :organization_id or subject_for_resource.organisation is null) and subject_for_resource.subject = :subject_id;";
            } elseif ($collection) {
                $sql = "select resource.id from resource left join resource_for_collection on resource.id = resource_for_collection.resource where resource_for_collection.collection = :subject_id;";
            }
        } else {
            $sql = "select resource.id from resource;";

            if ($subject) {
                $sql = "select resource.id from resource left join subject_for_resource on resource.id = subject_for_resource.resource where subject_for_resource.organisation is null and subject_for_resource.subject = :subject_id;";
            } 
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (count($results) > 0) {
            foreach($results as &$result) {
                $resource_id = (int) $result['id'];

                $resource = $this->getResourceById_NEW($resource_id, $organization_id);

                $resources[] = $resource;
            }
        }

        return $resources;
    }

    public function getTopRessourcesForSubject($subjectId, string $organizationId = null) {
        $sql = "select resource.id, top_resource_for_subject.sort_order from resource join top_resource_for_subject on resource.id = top_resource_for_subject.resource "
                . "WHERE top_resource_for_subject.subject = :subjectId and top_resource_for_subject.organization = :organizationId order by sort_order asc";
        $params[':subjectId'] = $subjectId;
        $params[':organizationId'] = $organizationId;

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        $resources = [];

        if (count($results) > 0) {
            foreach($results as &$result) {
                $resource_id = (int) $result['id'];

                $resource = $this->getResourceById_NEW($resource_id, $organizationId);

                $resources[] = $resource;
            }
        }

        return $resources;
    }

    public function getResources(array $options = null, string $localization = self::COMBINED): array
    {
        $id = $options['id'] ?? null;
        $organizationId = $options['organizationId'] ?? null;
        $offset = $options['offset'] ?? null;
        $limit = $options['limit'] ?? null;
        $subject = $options['for_subject'] ?? null;
        $collection = $options['for_collection'] ?? null;
        $sort_by = $options['sort_by'] ?? RELEVANCE_SORTING;  // Set relevance as default sorting
        $lang = $options['lang'] ?? 'de';

        $subjects = $subject ? [$subject] : null;

        // If the option is global, the organizationId should be set to null
        // since the id is irrelevant and we secure the query working correctly
        if ($localization == self::GLOBAL) {
            $options['organizationId'] = null;
            $organizationId = null;
        }

        $query = new GetResourcesQuery($this->pdo, $organizationId, [
            'explain' => false
        ]);

        $query->addResultCount();

        if ($collection) {
            $query->addFilterCollections([$collection]);
        }

        if (!is_null($sort_by)) {
            $query->setSort($sort_by, $lang);
        }

        if ($id) {
            $query->addGetById($id);
        }

        if ($subjects) {
            $query->addFilterSubjects($subjects);
        }

        if ($offset || $limit) {
            $query->setPagniation($offset, $limit);
        }

        $result = $query->execute();

        $entries = $result['results'];

        $resources_obj = [];

        foreach ($entries as $entry) {
            $data = json_decode($entry['data'], true);

            $resource = $this->assocToEntityResource($data);

            $resources_obj[] = $resource;
        }

        return $resources_obj;
    }

    /**
     * @throws ResourceNotFoundException
     */
    public function getResourceById(
        int $id,
        string $localOrganizationId = null,
        string $localization = self::COMBINED
    ): ?Resource {
        $results = $this->getResources([
            "id" => $id,
            "organizationId" => $localOrganizationId
        ], $localization);
        if (count($results) == 0) {
            throw new ResourceNotFoundException($id);
        }

        return (count($results) > 0) ? $results[0] : null;
    }

    public function getResourceById_NEW(
        int $id,
        string $organization_id = null
    ): ?Resource {

        $params = [
            ":resource_id" => $id
        ];

        if ($organization_id) {
            $params[":organization_id"] = $organization_id;

            $sql = <<<EOD
            select
                resource.*,
                coalesce(jsonb_agg(distinct licenses.*) filter (where licenses.id is not null), '[]') as licenses,
                coalesce(jsonb_agg(distinct subjects.*) filter (where subjects.id is not null), '[]') as subjects,
                coalesce(jsonb_agg(distinct collections.*) filter (where collections.id is not null), '[]') as collections,
                coalesce(jsonb_agg(distinct authors.*) filter (where authors.id is not null), '[]') as authors,
                coalesce(jsonb_agg(distinct countries.*) filter (where countries.id is not null), '[]') as countries,
                coalesce(jsonb_agg(distinct resource_types.*) filter (where resource_types.id is not null), '[]') as resource_types,
                coalesce(jsonb_agg(distinct keywords.*) filter (where keywords.id is not null), '[]') as keywords,
                COALESCE(jsonb_agg(DISTINCT alternative_titles.*) FILTER (WHERE alternative_titles.id IS NOT NULL), '[]') as alternative_titles,
                COALESCE(jsonb_agg(DISTINCT external_resource_id.*) FILTER (WHERE external_resource_id.id IS NOT NULL), '[]') as external_resource_id,
                COALESCE(jsonb_agg(DISTINCT resource_api.*) FILTER (WHERE resource_api.id IS NOT NULL), '[]') as api_urls,
                COALESCE(jsonb_agg(DISTINCT top_resource_entries_for_subject.*) FILTER (WHERE top_resource_entries_for_subject.id IS NOT NULL), '[]') as top_resource_entries, 
                COALESCE(jsonb_agg(DISTINCT top_resource_entries_for_collection.*) FILTER (WHERE top_resource_entries_for_collection.id IS NOT NULL), '[]') as top_resource_entries_within_collections,
                COALESCE(jsonb_agg(DISTINCT resource_localisation.*) FILTER (WHERE resource_localisation.id IS NOT NULL), '[]') as resource_localisation
            from
                resource
            left join (
                select
                    license.id as license_id,
                    license.*,
                    TO_JSONB(license_type.*) as license_type,
                    TO_JSONB(license_form.*) as form,
                    JSONB_AGG(accesses.*) as accesses,
                    COUNT(accesses.id) as n_accesses,
                    TO_JSONB(enterprise_vendor.*) as vendor,
                    TO_JSONB(enterprise_publisher.*) as publisher,
                    TO_JSONB(publication_form.*) as publication_form,
                    fid_for_license.fid,
                    fid_for_license.hosting_privilege,
                    TO_JSONB(license_localisation.*) as license_localisation,
                    coalesce(jsonb_agg(distinct external_license_id.*) filter (where external_license_id.id is not null), '[]') as external_license_id
                from
                    license
                left join license_type on
                    license_type.id = license.type
                left join license_form on
                    license_form.id = license.form
                left join enterprise as enterprise_vendor on
                    enterprise_vendor.id = license.vendor
                left join enterprise as enterprise_publisher on
                    enterprise_publisher.id = license.publisher
                left join license_localisation on
                    license_localisation.license = license.id and license_localisation.organisation = :organization_id
                left join license_for_organization on
                    license_for_organization.license = license.id
                left join publication_form on
                    publication_form.id = license.publication_form
                left join fid_for_license on
                    fid_for_license.license = license.id
                LEFT JOIN external_license_id
                    ON external_license_id.license = license.id
                left join (
                    select
                        TO_JSONB(access_type.*) as access_type,
                        TO_JSONB(access_form.*) as access_form,
                        TO_JSONB(host.*) as access_host,
                        access.*,
                        labels_for_organisation.label as org_label,
                        labels_for_organisation.label_long as org_label_long,
                        labels_for_organisation.label_longest as org_label_longest,
                        CASE 
                            WHEN main_access_for_organization.access IS NOT NULL THEN TRUE
                            ELSE FALSE
                        END AS is_main_access,
                        CASE 
                            WHEN access_hidden_for_organisation.access IS NULL THEN true
                            ELSE false
                        END AS is_visible
                    from
                        access
                    left join labels_for_organisation
                        ON access.label_id = labels_for_organisation.id
                    LEFT JOIN access_hidden_for_organisation
                        ON access.id = access_hidden_for_organisation.access AND access_hidden_for_organisation.organisation = :organization_id
                    left join access_type on
                        access_type.id = access.type
                    left join access_form on
                        access_form.id = access.form
                    left join main_access_for_organization on
                        access.id = main_access_for_organization.access and main_access_for_organization.resource = :resource_id and main_access_for_organization.organization = :organization_id
                    left join host on
                        host.id = access.host where access.organization = :organization_id or access.organization is null) as accesses on
                    accesses.license = license.id
                where
                    license_for_organization.organization = :organization_id
                group by
                    license.id,
                    license_type.id,
                    license_form.id,
                    license_localisation.*,
                    enterprise_vendor.*,
                    enterprise_publisher.*,
                    publication_form.*,
                    fid_for_license.fid,
                    fid_for_license.hosting_privilege) as licenses on
                licenses.resource = resource.id
            left join (
                select
                    keyword.id,
                    keyword.title,
                    keyword.external_id,
                    keyword.keyword_system,
                    keyword_for_resource.resource as resource_id
                from
                    keyword
                left join keyword_for_resource on
                    (keyword.id = keyword_for_resource.keyword)
                where 
                    keyword_for_resource.organisation = :organization_id
                group by
                    keyword.id,
                    keyword_for_resource.resource
            ) as keywords on
                keywords.resource_id = resource.id
            left join (
                select
                    subject.id,
                    subject.title,
                    subject.parent,
                    subject.subject_system,
                    subject.sort_by, 
                    subject_for_resource.resource as resource_id
                from
                    subject
                left join subject_for_resource on
                    (subject.id = subject_for_resource.subject)
                where 
                    subject_for_resource.organisation = :organization_id
                group by
                    subject.id,
                    subject_for_resource.resource
            ) as subjects on
                subjects.resource_id = resource.id
            left join (
                select
                    collection.*,
                    resource_for_collection.resource as resource_id
                from
                    collection
                left join resource_for_collection on
                    collection.id = resource_for_collection.collection
                left join collection_for_organisation on 
                    collection.id = collection_for_organisation.collection
                    where collection_for_organisation.organisation = :organization_id
            ) as collections on collections.resource_id = resource.id
            left join (
                select
                    author.id,
                    author.title,
                    author_for_resource.resource as resource_id
                from
                    author
                left join author_for_resource on
                    (author.id = author_for_resource.author)
                where 
                    author_for_resource.organisation = :organization_id
                group by
                    author.id,
                    author_for_resource.resource
            ) as authors on
                authors.resource_id = resource.id
            left join (
                select
                    country.id,
                    country.title,
                    country.code,
                    country_for_resource.resource as resource_id
                from
                    country
                left join country_for_resource on
                    (country.id = country_for_resource.country)
                where 
                    country_for_resource.organisation = :organization_id
                group by
                    country.id,
                    country_for_resource.resource
            ) as countries on
                countries.resource_id = resource.id
            left join (
                select
                    resource_type.id,
                    resource_type.title,
                    resource_type.description,
                    resource_type_for_resource.resource as resource_id
                from
                    resource_type
                left join resource_type_for_resource on
                    (resource_type.id = resource_type_for_resource.resource_type)
                where 
                    resource_type_for_resource.organisation = :organization_id
                group by
                    resource_type.id,
                    resource_type_for_resource.resource
            ) as resource_types on
                resource_types.resource_id = resource.id
            LEFT JOIN resource_localisation 
                ON resource_localisation.resource = resource.id
                AND resource_localisation.organisation = :organization_id
            LEFT JOIN external_resource_id
                ON external_resource_id.resource = resource.id
            LEFT JOIN resource_api
                ON resource_api.resource = resource.id
            LEFT JOIN alternative_title as alternative_titles 
                ON alternative_titles.resource = resource.id 
            LEFT JOIN (
                SELECT 
                    top_resource_for_subject.*,
                    TO_JSONB(subject.*) as top_subject
                FROM top_resource_for_subject
                LEFT JOIN subject 
                    ON subject.id = top_resource_for_subject.subject
                ) AS top_resource_entries_for_subject
                    ON top_resource_entries_for_subject.resource = resource.id
                        AND top_resource_entries_for_subject.organization = :organization_id
            LEFT JOIN (
                SELECT 
                    top_resource_for_collection.*,
                    TO_JSONB(collection.*) as top_collection
                FROM top_resource_for_collection
                LEFT JOIN collection 
                    ON collection.id = top_resource_for_collection.collection
                ) AS top_resource_entries_for_collection
                    ON top_resource_entries_for_collection.resource = resource.id
                        AND top_resource_entries_for_collection.organization = :organization_id
            where resource.id = :resource_id
            group by
                resource.id;

            EOD;
        } else {
            $sql = <<<EOD

            select
                resource.*,
                coalesce(jsonb_agg(distinct licenses.*) filter (where licenses.id is not null), '[]')::text as licenses,
                coalesce(jsonb_agg(distinct subjects.*) filter (where subjects.id is not null), '[]')::text as subjects,
                coalesce(jsonb_agg(distinct authors.*) filter (where authors.id is not null), '[]')::text as authors,
                coalesce(jsonb_agg(distinct countries.*) filter (where countries.id is not null), '[]')::text as countries,
                coalesce(jsonb_agg(distinct resource_types.*) filter (where resource_types.id is not null), '[]')::text as resource_types,
                coalesce(jsonb_agg(distinct keywords.*) filter (where keywords.id is not null), '[]')::text as keywords,
                COALESCE(jsonb_agg(DISTINCT alternative_titles.*) FILTER (WHERE alternative_titles.id IS NOT NULL), '[]') as alternative_titles,
                COALESCE(jsonb_agg(DISTINCT external_resource_id.*) FILTER (WHERE external_resource_id.id IS NOT NULL), '[]') as external_resource_id,
                COALESCE(jsonb_agg(DISTINCT resource_api.*) FILTER (WHERE resource_api.id IS NOT NULL), '[]') as api_urls,
                COALESCE(jsonb_agg(DISTINCT top_resource_entries_for_subject.*) FILTER (WHERE top_resource_entries_for_subject.id IS NOT NULL), '[]') as top_resource_entries
            from
                resource
            left join (
                select
                    license.id as license_id,
                    license.*,
                    TO_JSONB(license_type.*) as license_type,
                    TO_JSONB(license_form.*) as form,
                    JSONB_AGG(accesses.*) as accesses,
                    COUNT(accesses.id) as n_accesses,
                    TO_JSONB(enterprise_vendor.*) as vendor,
                    TO_JSONB(enterprise_publisher.*) as publisher,
                    TO_JSONB(publication_form.*) as publication_form,
                    fid_for_license.fid,
                    fid_for_license.hosting_privilege,
                    coalesce(jsonb_agg(distinct external_license_id.*) filter (where external_license_id.id is not null), '[]') as external_license_id
                from
                    license
                left join license_type on
                    license_type.id = license.type
                left join license_form on
                    license_form.id = license.form
                left join enterprise as enterprise_vendor on
                    enterprise_vendor.id = license.vendor
                left join enterprise as enterprise_publisher on
                    enterprise_publisher.id = license.publisher
                left join publication_form on
                    publication_form.id = license.publication_form
                left join fid_for_license on
                    fid_for_license.license = license.id
                LEFT JOIN external_license_id
                    ON external_license_id.license = license.id
                left join (
                    select
                        TO_JSONB(access_type.*) as access_type,
                        TO_JSONB(access_form.*) as access_form,
                        TO_JSONB(host.*) as access_host,
                        access.*,
                        labels_for_organisation.label as org_label,
                        labels_for_organisation.label_long as org_label_long,
                        labels_for_organisation.label_longest as org_label_longest
                    from
                        access
                    left join labels_for_organisation
                        ON access.label_id = labels_for_organisation.id
                    left join access_type on
                        access_type.id = access.type
                    left join access_form on
                        access_form.id = access.form
                    left join host on
                        host.id = access.host where access.organization is null) as accesses on
                    accesses.license = license.id
                where
                    license_type.id = 1 OR license_type.id = 3 OR license_type.id = 4 OR license_type.id = 5 OR license_type.id = 6 
                group by
                    license.id,
                    license_type.id,
                    license_form.id,
                    enterprise_vendor.*,
                    enterprise_publisher.*,
                    publication_form.*,
                    fid_for_license.fid,
                    fid_for_license.hosting_privilege) as licenses on
                licenses.resource = resource.id
            left join (
                select
                    keyword.id,
                    keyword.title,
                    keyword.external_id,
                    keyword.keyword_system,
                    keyword_for_resource.resource as resource_id
                from
                    keyword
                left join keyword_for_resource on
                    (keyword.id = keyword_for_resource.keyword)
                where 
                    keyword_for_resource.organisation is null
                group by
                    keyword.id,
                    keyword_for_resource.resource
            ) as keywords on
                keywords.resource_id = resource.id
            left join (
                select
                    subject.id,
                    subject.title,
                    subject.parent,
                    subject.subject_system,
                    subject.sort_by, 
                    subject_for_resource.resource as resource_id
                from
                    subject
                left join subject_for_resource on
                    (subject.id = subject_for_resource.subject)
                where 
                    subject_for_resource.organisation is null
                group by
                    subject.id,
                    subject_for_resource.resource
            ) as subjects on
                subjects.resource_id = resource.id
            left join (
                select
                    author.id,
                    author.title,
                    author_for_resource.resource as resource_id
                from
                    author
                left join author_for_resource on
                    (author.id = author_for_resource.author)
                where 
                    author_for_resource.organisation is null
                group by
                    author.id,
                    author_for_resource.resource
            ) as authors on
                authors.resource_id = resource.id
            left join (
                select
                    country.id,
                    country.title,
                    country.code,
                    country_for_resource.resource as resource_id
                from
                    country
                left join country_for_resource on
                    (country.id = country_for_resource.country)
                where 
                    country_for_resource.organisation is null
                group by
                    country.id,
                    country_for_resource.resource
            ) as countries on
                countries.resource_id = resource.id
            left join (
                select
                    resource_type.id,
                    resource_type.title,
                    resource_type.description,
                    resource_type_for_resource.resource as resource_id
                from
                    resource_type
                left join resource_type_for_resource on
                    (resource_type.id = resource_type_for_resource.resource_type)
                where 
                    resource_type_for_resource.organisation is null
                group by
                    resource_type.id,
                    resource_type_for_resource.resource
            ) as resource_types on
                resource_types.resource_id = resource.id
            LEFT JOIN external_resource_id
                ON external_resource_id.resource = resource.id
            LEFT JOIN resource_api
                ON resource_api.resource = resource.id
            LEFT JOIN alternative_title as alternative_titles 
                ON alternative_titles.resource = resource.id 
            LEFT JOIN (
                SELECT 
                    top_resource_for_subject.*,
                    TO_JSONB(subject.*) as top_subject
                FROM top_resource_for_subject
                LEFT JOIN subject 
                    ON subject.id = top_resource_for_subject.subject
                ) AS top_resource_entries_for_subject
                    ON top_resource_entries_for_subject.resource = resource.id
                        AND top_resource_entries_for_subject.organization is null
            where resource.id = :resource_id
            group by
                resource.id;

            EOD;
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (count($results) > 0) {
            $entry = $results[0];
            $entry['description_short'] = $entry['description_short'] ? json_decode($entry['description_short'], true) : null;
            $entry['description'] = $entry['description'] ? json_decode($entry['description'], true) : null;
            $entry['note'] = $entry['note'] ? json_decode($entry['note'], true) : null;
            $entry['instructions'] = $entry['instructions'] ? json_decode($entry['instructions'], true) : null;

            $entry['keywords'] = json_decode($entry['keywords'], true);
            $entry['licenses'] = json_decode($entry['licenses'], true);
            $entry['subjects'] = json_decode($entry['subjects'], true);
            $entry['collections'] = array_key_exists('collections', $entry) ? json_decode($entry['collections'], true) : null;
            $entry['authors'] = json_decode($entry['authors'], true);
            $entry['countries'] = json_decode($entry['countries'], true);
            $entry['resource_types'] = json_decode($entry['resource_types'], true);
            $entry['alternative_titles'] = json_decode($entry['alternative_titles'], true);
            $entry['external_resource_id'] = json_decode($entry['external_resource_id'], true);
            $entry['api_urls'] = json_decode($entry['api_urls'], true);
            $entry['top_resource_entries'] = json_decode($entry['top_resource_entries'], true);
            $entry['top_resource_entries_within_collections'] = array_key_exists('top_resource_entries_within_collections', $entry) ? json_decode($entry['top_resource_entries_within_collections'], true) : null;
            $entry['resource_localisation'] = array_key_exists('resource_localisation', $entry) ? json_decode($entry['resource_localisation'], true) : null;
            $entry['license_localisation'] = array_key_exists('license_localisation', $entry) ? json_decode($entry['license_localisation'], true) : null;
            return $this->assocToEntityResource($entry);
        } else {
            return null;
        }
    }

    public function getFreeResourceWithLicenseOnly(int $resource_id): ?Resource {
        $params = [
            ":resource_id" => $resource_id
        ];

        $sql = <<<EOD
            select
                resource.*,
                coalesce(jsonb_agg(distinct licenses.*) filter (where licenses.id is not null), '[]')::text as licenses
            from
                resource
            left join (
                select
                    license.id as license_id,
                    license.*,
                    TO_JSONB(license_type.*) as license_type,
                    TO_JSONB(license_form.*) as form,
                    JSONB_AGG(accesses.*) as accesses,
                    COUNT(accesses.id) as n_accesses,
                    TO_JSONB(enterprise_vendor.*) as vendor,
                    TO_JSONB(enterprise_publisher.*) as publisher,
                    TO_JSONB(publication_form.*) as publication_form,
                    fid_for_license.fid,
                    fid_for_license.hosting_privilege,
                    coalesce(jsonb_agg(distinct external_license_id.*) filter (where external_license_id.id is not null), '[]') as external_license_id
                from
                    license
                left join license_type on
                    license_type.id = license.type
                left join license_form on
                    license_form.id = license.form
                left join enterprise as enterprise_vendor on
                    enterprise_vendor.id = license.vendor
                left join enterprise as enterprise_publisher on
                    enterprise_publisher.id = license.publisher
                left join publication_form on
                    publication_form.id = license.publication_form
                left join fid_for_license on
                    fid_for_license.license = license.id
                left join external_license_id
                                on
                    external_license_id.license = license.id
                left join (
                    select
                        TO_JSONB(access_type.*) as access_type,
                        TO_JSONB(access_form.*) as access_form,
                        TO_JSONB(host.*) as access_host,
                        access.*
                    from
                        access
                    left join access_type on
                        access_type.id = access.type
                    left join access_form on
                        access_form.id = access.form
                    left join host on
                        host.id = access.host
                    where
                        access.organization is null) as accesses on
                    accesses.license = license.id
                where
                    license_type.id = 1
                group by
                    license.id,
                    license_type.id,
                    license_form.id,
                    enterprise_vendor.*,
                    enterprise_publisher.*,
                    publication_form.*,
                    fid_for_license.fid,
                    fid_for_license.hosting_privilege) as licenses on
                licenses.resource = resource.id
            where
                resource.id = :resource_id
            group by
                resource.id;
            EOD;

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (count($results) > 0) {
            $entry = $results[0];
            $entry['description_short'] = $entry['description_short'] ? json_decode($entry['description_short'], true) : null;
            $entry['description'] = $entry['description'] ? json_decode($entry['description'], true) : null;
            $entry['note'] = $entry['note'] ? json_decode($entry['note'], true) : null;
            $entry['instructions'] = $entry['instructions'] ? json_decode($entry['instructions'], true) : null;

            $entry['licenses'] = json_decode($entry['licenses'], true);
            return $this->assocToEntityResource($entry);
        } else {
            return null;
        }
    }

    public function getResourceDrafts(): array {
        $params = array();

        $sql = "select * from resource WHERE DATE(created_at) = CURRENT_DATE;";

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        $resources_obj = [];

        foreach ($results as $result) {
            // $data = json_decode($result['data'], true);
            $result['description'] = json_decode($result['description'], true);
            $result['description_short'] = isset($result['description_short']) ? json_decode($result['description_short'], true): null;
            $result['note'] = isset($result['note'])? json_decode($result['note'], true): null;
            $result['instructions'] = isset($result['instructions'])? json_decode($result['instructions'], true): null;

            $resource = $this->assocToEntityResource($result);

            $resources_obj[] = $resource;
        }

        return $resources_obj;
    }

    public function getLicensesCount($resourceId): int {
        $params = array();

        $sql = "select count(*) from license WHERE resource=:resourceId;";

        $statement = $this->pdo->prepare($sql);
        $params['resourceId'] = $resourceId;
        $statement->execute($params);

        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        return (int) $results[0]['count'];
    }

    /**
     * Create assoc array for pdo
     * @param Resource $resource
     * @return array
     */
    private function entityToAssocResource(Resource $resource, $is_local = null): array
    {
        $params = [
            ":title" => $resource->getTitle()
        ];

        $resource->getId() ? $params[":id"] = $resource->getId() : null;

        $description_short = $resource->getDescriptionShort();
        $params[":description_short"] = json_encode($description_short) ?: null;

        $description = $resource->getDescription();
        $params[":description"] = json_encode($description) ?: null;

        $report_time_start = $resource->getReportTimeStart();

        $params[":report_time_start"] =
            $report_time_start ? date("m/d/Y", strtotime($report_time_start)) : null;

        $report_time_end = $resource->getReportTimeEnd();
        $params[":report_time_end"] =
            $report_time_end ? date("m/d/Y", strtotime($report_time_end)) : null;

        $publication_time_start = $resource->getPublicationTimeStart();
        $params[":publication_time_start"] =
            $publication_time_start ? date("m/d/Y", strtotime($publication_time_start)) : null;

        $publication_time_end = $resource->getPublicationTimeEnd();
        $params[":publication_time_end"] =
            $publication_time_end ? date("m/d/Y", strtotime($publication_time_end)) : null;

        $params[":is_still_updated"] = $resource->isIsStillUpdated();

        $update_frequency = $resource->getUpdateFrequency() ? $resource->getUpdateFrequency()->getId() : null;
        $params[":update_frequency"] = $update_frequency;

        $shelfmark = $resource->getShelfmark();
        $params[":shelfmark"] = $shelfmark;

        $note = $resource->getNote();
        $params[":note"] = json_encode($note) ?: null;

        $isbn_issn = $resource->getIsbnIssn();
        $params[":isbn_issn"] = $isbn_issn;

        $local_note = $resource->getLocalNote();
        $params[":local_note"] = json_encode($local_note) ?: null;

        $instructions = $resource->getInstructions();
        $params[":instructions"] = json_encode($instructions) ?: null;

        // Postgres only reads strings
        $params[":is_visible"] = ($resource->isVisible() !== null) ? (($resource->isVisible())
            ? 'true' : 'false') : null;

        $params[":is_free"] = $resource->isFree() ? 'true': 'false';

        // Needed for the extra local note
        if ($is_local) {
            $local_note = $resource->getLocalNote();
            $params[":local_note"] = json_encode($local_note) ?: null;
        }
        

        return $params;
    }

    /**
     * Create assoc array for pdo
     * @param Keyword $keyword
     * @return array
     */
    private function entityToAssocKeyword(Keyword $keyword): array
    {
        $params = [
            ":title" => json_encode($keyword->getTitle())
        ];

        $external_id = $keyword->getExternalId();
        $params[":external_id"] = $external_id ?: null;
        $keyword_system = $keyword->getKeywordSystem();
        $params[":keyword_system"] = $keyword_system ?: null;

        return $params;
    }

    /**
     * Create assoc array for pdo
     * @param Country $country
     * @return array
     */
    private function entityToAssocCountry(Country $country): array
    {
        $params = [
            ":title" => json_encode($country->getTitle())
        ];

        $code = $country->getCode();
        $params[":code"] = $code ?: null;

        return $params;
    }

    /**
     * Create assoc array for pdo
     * @param Author $author
     * @return array
     */
    private function entityToAssocAuthor(Author $author): array
    {
        return [
            ":title" => $author->getTitle()
        ];
    }

    private function entityToAssocTopResourceEntry(TopResourceEntry $entry): array
    {
        if ($entry->isCollection()) {
            return [
                ":organization" => $entry->getOrganizationId(),
                ":collection" => $entry->getSubject()->getId(),
                ":resource" => $entry->getResourceId(),
                ":order" => $entry->getOrder()
            ];
        } else {
            return [
                ":organization" => $entry->getOrganizationId(),
                ":subject" => $entry->getSubject()->getId(),
                ":resource" => $entry->getResourceId(),
                ":order" => $entry->getOrder()
            ];
        }
    }

    //
    //
    // Assoc Array from Database to Entity Model

    private function assocToEntityUpdateFrequency($entry): UpdateFrequency
    {
        $update_frequency = new UpdateFrequency($entry['id']);
        $update_frequency->setTitle($entry['title']);
        return $update_frequency;
    }

    private function assocToEntityType($entry): Type
    {
        $type = new Type($entry['id']);
        $type->setTitle($entry['title']);
        $type->setDescription($entry['description']);
        return $type;
    }

    private function assocToEntityPublicationForm($entry): PublicationForm
    {
        $id = (int) $entry['id'];
        $publication_form = new PublicationForm($id);
        $publication_form->setTitle($entry['title']);
        $publication_form->setDescription($entry['description']);
        return $publication_form;
    }

    private function assocToEntitySubject($entry): Subject
    {
        $subject = new Subject($entry['id']);
        $subject->setTitle($entry['title']);

        if (array_key_exists('subject_system', $entry)) {
            $subject->setSubjectSystem($entry['subject_system']);
        }
        if (array_key_exists('parent', $entry)) {
            $subject->setParent($entry['parent']);
        }
        
        $subject->setSortBy((int)$entry['sort_by']);
        // "resources" is null, when subject is built from "getResources"-Query
        // to save inflating unused fields.

        if (array_key_exists('is_hidden', $entry)) {
            if ($entry['is_hidden']) {
                $subject->setVisibility(false);
            }
        }

        $subject->setResourceIds(json_decode($entry['resources'] ?? '[]'));

        return $subject;
    }

    private function assocToEntityResourceAggregate($entry): ResourceAggregate
    {
        // TODO: if entry is a collection, it's just an int
        $entry_id = (int)$entry['id'];
        if (key_exists('subject_system', $entry)) {
            $subject = new Subject($entry_id);
            $subject->setTitle($entry['title']);
            $subject->setSubjectSystem($entry['subject_system']);
            $subject->setParent($entry['parent']);
            return $subject;
        } else {
            $collection = new Collection($entry['title']);
            $collection->setId($entry_id);
            if ($entry['notation']) {
                $collection->setNotation($entry['notation']);
            }
            return $collection;
        }
    }

    private function assocToEntityKeyword($entry): Keyword
    {
        $keyword = new Keyword($entry['title'], true);
        $keyword->setId($entry['id']);
        $keyword->setExternalId($entry['external_id']);
        $keyword->setKeywordSystem($entry['keyword_system']);
        return $keyword;
    }

    private function assocToEntityAuthor($entry): Author
    {
        $author = new Author($entry['title']);
        $author->setId($entry['id']);

        return $author;
    }


    private function assocToEntityLicense($entry): License
    {
        $type = new LicenseType(
            $entry['license_type']['id'],
            $entry['license_type']['title'],
            $entry['license_type']['description'] ?? null
        );
        $type->setGlobal($entry['license_type']['is_global']);

        $license = new License(
            $type,
            (int)$entry['license_id']
        );

        if ($entry['form']) {
            $license->setForm(
                new LicenseForm(
                    $entry['form']['id'],
                    $entry['form']['title'],
                    $entry['form']['description'] ?: ["de" => "", "en" => ""]
                )
            );
        }
        
        if ($entry['publication_form']) {
            $publicationForm = new PublicationForm($entry['publication_form']['id']);
            $publicationForm->setTitle($entry['publication_form']['title']);
            if ($entry['publication_form']['description']) {
                $publicationForm->setDescription($entry['publication_form']['description']);
            }
            $license->setPublicationForm($publicationForm);
        }

        if ($entry['fid']) {
            $license->setFID($entry['fid']);
        }

        if ($entry['hosting_privilege']) {
            $license->setHostingPrivilege((bool)$entry['hosting_privilege']);
        }

        if (array_key_exists("number_of_concurrent_users", $entry) && $entry['number_of_concurrent_users']) {
            $license->setNumberOfConcurrentUsers($entry['number_of_concurrent_users']);
        }
        
        if (array_key_exists("internal_notes", $entry) && $entry["internal_notes"]) {
            $license->setInternalNotes($entry['internal_notes']);
        }
        
        if (array_key_exists("external_notes", $entry) && $entry["external_notes"]) {
            $license->setExternalNotes($entry['external_notes']);
        }
        
        if (array_key_exists("is_active", $entry)) {
            $license->setActive((bool)$entry['is_active']);
        }
        
        if (array_key_exists("is_allowing_data_mining", $entry)) {
            $license->setTextMiningAllowed((bool)$entry['is_allowing_data_mining']);
        }
        
        if (array_key_exists("is_allowing_walking", $entry)) {
            $license->setAllowingWalking((bool)$entry['is_allowing_walking']);
        }
        
        if (array_key_exists("is_oa", $entry)) {
            $license->setOA((bool)$entry['is_oa']);
        }
        
        if (array_key_exists("vendor", $entry) && $entry["vendor"]) {
            $license->setVendor($entry["vendor"]);
        }
        
        if (array_key_exists("publisher", $entry) && $entry["publisher"]) {
            $license->setPublisher($entry["publisher"]);
        }

        if (isset($entry["last_check"])) {
            $license->setLastCheck($entry["last_check"]);
        }

        if (isset($entry["aquired"])) {
            $license->setAquired($entry["aquired"]);
        }

        if (isset($entry["created_at"])) {
            $license->setCreatedAt($entry["created_at"]);
        }

        if (isset($entry["modified_at"])) {
            $license->setModifiedAt($entry["modified_at"]);
        }
        
        if (isset($entry["cancelled"])) {
            $license->setCancelled($entry["cancelled"]);
        }

        $externalIds = array();
        
        if (array_key_exists('external_license_id', $entry) && $entry['external_license_id']) {
            $externalIds = array_map(
                function ($externalIdAssoc) {
                    return $this->assocToEntityExternalID($externalIdAssoc);
                },
                $entry['external_license_id']
            );
        }
        $license->setExternalIDs($externalIds);

        // Parse and set accesses
        $accesses = array_filter($entry['accesses'], function ($access, $k) {
            return $access != null;
        }, ARRAY_FILTER_USE_BOTH);
        $accesses = array_map(
            function ($entry) {
                return $this->assocToEntityAccess($entry);
            },
            $accesses
        );
        $license->setAccesses($accesses);

        return $license;
    }

    private function assocToEntityAccess($entry): Access
    {
        $access_type = null;

        // print_r($entry);

        if (array_key_exists('access_type', $entry) && !is_null($entry['access_type'])) {
            $access_type = new AccessType(
                $entry['access_type']['id'],
                $entry['access_type']['title']
            );
        }

        $access_form = null;
        if (array_key_exists('access_form', $entry) && !is_null($entry['access_form'])) {
            $access_form = new AccessForm(
                $entry['access_form']['id'],
                $entry['access_form']['title']
            );
        }

        $access = new Access(
            $access_type,
            $entry['id']
        );

        if ($access_form) {
            $access->setForm($access_form);
        }

        if (array_key_exists('is_main_access', $entry) && (bool)$entry['is_main_access'] == true) {
            $access->setMainAccess(true);
        }

        if (array_key_exists('is_visible', $entry) && (bool)$entry['is_visible'] == false) {
            $access->setVisibility(false);
        }

        if (array_key_exists('access_url', $entry) && $entry['access_url']) {
            $access->setAccessUrl($entry['access_url']);
            // $access->setAccessHash($this->generateHash($entry['access_url']));
        }
        
        if (array_key_exists('manual_url', $entry) && $entry['manual_url']) {
            $access->setManualUrl($entry['manual_url']);
        }
        
        if (array_key_exists('404_url', $entry) && $entry['404_url']) {
            $access->set404Url($entry['404_url']);
        }
        
        if (array_key_exists('description', $entry) && $entry['description']) {
            $access->setDescription($entry['description']);
        }

        if (array_key_exists('label_id', $entry) && $entry['label_id']) {
            $labelId = (int) $entry['label_id'];
            $access->setLabelId($labelId);
        }

        /*
        if (array_key_exists('org_label', $entry) && $entry['org_label']) {
            $access->setLabel($entry['org_label']);
        }

        if (array_key_exists('org_label_long', $entry) && $entry['org_label_long']) {
            $access->setLongLabel($entry['org_label_long']);
        }

        if (array_key_exists('org_label_longest', $entry) && $entry['org_label_longest']) {
            $access->setLongestLabel($entry['org_label_longest']);
        }
        */
        
        if (array_key_exists('label', $entry) && $entry['label']) {
            $access->setLabel($entry['label']);
        }

        if (array_key_exists('label_long', $entry) && $entry['label_long']) {
            $access->setLongLabel($entry['label_long']);
        }

        if (array_key_exists('label_longest', $entry) && $entry['label_longest']) {
            $access->setLongestLabel($entry['label_longest']);
        }
        
        if (array_key_exists('requirements', $entry) && $entry['requirements']) {
            $access->setRequirements($entry['requirements']);
        }
        
        if (array_key_exists('host', $entry) && $entry['host']) {
            $access->setHost($this->assocToEntityHost($entry['access_host']));
        }

        if (array_key_exists('organization', $entry) && $entry['organization']) {
            $access->setOrganizationId($entry['organization']);
        }

        if (array_key_exists('state', $entry) && $entry['state']) {
            $access->setState($entry['state']);
        }

        if (array_key_exists('license', $entry) && $entry['license']) {
            $access->setLicenseId((int)$entry['license']);
        }

        if (array_key_exists('shelfmark', $entry) && $entry['shelfmark']) {
            $access->setShelfmark($entry['shelfmark']);
        }

        return $access;
    }

    private function assocToEntityHost($entry): Host
    {
        // $entry could be an id too?!
        $host = new Host();
        $host->setId((int)$entry['id']);
        $host->setTitle($entry['title']);
        return $host;
    }

    private function assocToEntityEnterprise($entry): Enterprise
    {
        $enterprise = new Enterprise($entry['id']);
        $enterprise->setTitle($entry['title']);
        return $enterprise;
    }

    private function assocToEntityAccessMapping($entry): AccessMapping
    {
        $accessMapping = new AccessMapping($entry['bib_id'], $entry['titel_id']);
        $accessMapping->setZugangId($entry['zugang_id']);
        $accessMapping->setNutzung($entry['nutzung']);
        $accessMapping->setKurznutzung($entry['kurznutzung']);
        $accessMapping->setLongText($entry['long_text']);
        return $accessMapping;
    }

    private function assocToEntityTopResourceEntry($entry): TopResourceEntry
    {
        // TODO: If it is a collection, $entry['collection'] is an id, not an object
        $subject = array_key_exists('subject', $entry) ? $entry['top_subject'] : $entry['top_collection'];

        $entity = new TopResourceEntry(
            $entry['organization'],
            $entry['resource'],
            $this->assocToEntityResourceAggregate($subject)
        );
        $entity->setOrder((int)$entry['sort_order']);
        return $entity;
    }

    private function assocToEntityCountry($entry): Country
    {
        $country = new Country($entry['id']);
        if ($entry['title']) {
            $country->setTitle($entry['title']);
        }
        if ($entry['code']) {
            $country->setCode($entry['code']);
        }

        return $country;
    }

    private function assocToEntityResource($entry): Resource
    {   
        $keywords = array();

        if (array_key_exists('keywords', $entry) && $entry['keywords']) {
            $keywords = array_map(
                function ($item) {
                    return $this->assocToEntityKeyword($item);
                },
                $entry['keywords']
            );
        }
        
        $types_obj = array();

        if (array_key_exists('resource_types', $entry) && $entry['resource_types']) {
            $types_obj = array_map(
                function ($item) {
                    return $this->assocToEntityType($item);
                },
                $entry['resource_types']
            );
        }

        $subjects = array();
        
        if (array_key_exists('subjects', $entry) && $entry['subjects']) {
            $subjects = array_map(
                function ($subjAssoc) {
                    return $this->assocToEntitySubject($subjAssoc);
                },
                $entry['subjects']
            );  
        }

        $authors = array();
            
        if (array_key_exists('authors', $entry) && $entry['authors']) {
            $authors = array_map(
                function ($authorAssoc) {
                    return $this->assocToEntityAuthor($authorAssoc);
                },
                $entry['authors']
            );
        }

        $alternativeTitles = array();
        
        if (array_key_exists('alternative_titles', $entry) && $entry['alternative_titles']) {
            $alternativeTitles = array_map(
                function ($altTitleAssoc) {
                    return $this->assocToEntityAlternativeTitle($altTitleAssoc);
                },
                $entry['alternative_titles']
            );
        }

        $apiUrls = array();
        
        if (array_key_exists('api_urls', $entry) && $entry['api_urls']) {
            $apiUrls = array_map(
                function ($apiUrlAssoc) {
                    return $this->assocToEntityApiUrl($apiUrlAssoc);
                },
                $entry['api_urls']
            );
        }

        $externalIds = array();
        
        if (array_key_exists('external_resource_id', $entry) && $entry['external_resource_id']) {
            $externalIds = array_map(
                function ($externalIdAssoc) {
                    return $this->assocToEntityExternalID($externalIdAssoc);
                },
                $entry['external_resource_id']
            );
        }

        $topEntries_of_subjects = array();
        
        if (array_key_exists('top_resource_entries', $entry) && $entry['top_resource_entries']) {
            $topEntries_of_subjects = array_map(
                function ($assoc) {
                    return $this->assocToEntityTopResourceEntry($assoc);
                },
                $entry['top_resource_entries']
            );
        }

        $top_entries_of_collections = array();

        if (array_key_exists('top_resource_entries_within_collections', $entry) && $entry['top_resource_entries_within_collections']) {
            $top_entries_of_collections = array_map(
                function ($assoc) {
                    return $this->assocToEntityTopResourceEntry($assoc);
                },
                $entry['top_resource_entries_within_collections']
            );
        }

        $topEntries = array_merge($topEntries_of_subjects, $top_entries_of_collections);

        $countries = array();

        if (array_key_exists('countries', $entry) && $entry['countries']) {
            $countries = array_map(
                function ($countryAssoc) {
                    return $this->assocToEntityCountry($countryAssoc);
                },
                $entry['countries']
            );
        }
        
        $res = new Resource(
            $entry['title'],
            $types_obj
        );

        $res->setId($entry['id']);

        if ($entry['description_short']) {
            $res->setDescriptionShort($entry['description_short']);
        }
        if ($entry['description']) {
            $res->setDescription($entry['description']);
        }
        if ($entry['report_time_start']) {
            $res->setReportTimeStart($entry['report_time_start']);
        }
        if ($entry['report_time_end']) {
            $res->setReportTimeEnd($entry['report_time_end']);
        }
        if ($entry['publication_time_start']) {
            $res->setPublicationTimeStart($entry['publication_time_start']);
        }
        if ($entry['publication_time_end']) {
            $res->setPublicationTimeEnd($entry['publication_time_end']);
        }

        if ($entry['is_still_updated']) {
            $res->setIsStillUpdated($entry['is_still_updated']);
        }

        if (isset($entry['is_free'])) {
        $is_free = (bool)$entry['is_free'];
        $res->setIsFree($is_free);
        }

        if ($entry['update_frequency']) {
            $res->setUpdateFrequency($this->assocToEntityUpdateFrequency(
                $entry['update_frequency']
            ));
        }
        if ($entry['shelfmark']) {
            $res->setShelfmark($entry['shelfmark']);
        }
        if ($entry['note']) {
            $res->setNote($entry['note']);
        }
        if ($entry['isbn_issn']) {
            $res->setIsbnIssn($entry['isbn_issn']);
        }

        if ($entry['created_by']) {
            $res->setCreatedBy($entry['created_by']);
        }

        if ($entry['created_at']) {
            $res->setCreatedAt($entry['created_at']);
        }

        if ($entry['modified_at']) {
            $res->setModifiedAt($entry['modified_at']);
        }

        if ($entry['instructions']) {
            $res->setInstructions($entry['instructions']);
        }

        if (array_key_exists('collections', $entry) && $entry['collections']) {
            $collections = array_map(
                function ($collAssoc) {
                    return $this->assocToEntityCollection($collAssoc);
                },
                $entry['collections']
            );
            $res->setCollections($collections);
        }

        $is_visible = !is_null($entry['is_visible']) ? (bool)$entry['is_visible'] : null;
        $res->setIsVisible($is_visible);

        $res->setAuthors($authors);
        $res->setSubjects($subjects);
        $res->setKeywords($keywords);
        $res->setTopResourceEntries($topEntries);
        $res->setAlternativeTitles($alternativeTitles);
        $res->setApiUrls($apiUrls);
        $res->setCountries($countries);
        $res->setExternalIDs($externalIds);

        // parse and set licenses ---------------------
        $licenses = array();
        if (array_key_exists('licenses', $entry)) {
            $licensesAssoc = $entry['licenses'];

            $licensesFiltered = array_filter($licensesAssoc, function ($item) {
                return ($item != null);
            });

            $uniqueArray = [];
            $seenIds = [];

            $licensesUnique = array_filter($licensesFiltered, function ($item) use (&$seenIds) {
                if (!in_array($item['id'], $seenIds)) {
                    $seenIds[] = $item['id'];
                    return true;
                }
                return false; 
            });
    
            $licenses = array_map(
                function ($item) use ($entry) {
                    $item['vendor'] = isset($item['vendor']) && $item['vendor'] ?
                        $this->assocToEntityEnterprise($item['vendor']) : null;
                    $item['publisher'] = isset($item['publisher']) && $item['publisher'] ?
                        $this->assocToEntityEnterprise($item['publisher']) : null;
    
                    $license = $this->assocToEntityLicense($item);
                    
                    if (array_key_exists('license_localisation', $item) && $item['license_localisation']) {
                        if ($license->getId() == $item['license_localisation']['license']) {
                            $license_localisation = $this->assocToEntityLicenseLocalisation($item['license_localisation']);
                            $license->setLicenseLocalisation($license_localisation);
                        }
                    }
            
    
                    return $license;
                },
                $licensesUnique
            );
        } 

        $res->setLicenses($licenses);

        if (array_key_exists('resource_localisation', $entry) && $entry['resource_localisation'] && count($entry['resource_localisation']) > 0) {
            $resource_localisation = $this->assocToEntityResourceLocalisation($entry['resource_localisation'][0]);
            $res->setOverwrite($resource_localisation);
        }

        return $res;
    }

    private function assocToEntityResourceLocalisation(array $entry): Resource
    {
        $resourceLocalisation = new Resource();

        if ($entry['title']) {
            $resourceLocalisation->setTitle($entry['title']);
        }
        if ($entry['description_short']) {
            $resourceLocalisation->setDescriptionShort($entry['description_short']);
        }
        if ($entry['description']) {
            $resourceLocalisation->setDescription($entry['description']);
        }
        if ($entry['report_time_start']) {
            $resourceLocalisation->setReportTimeStart($entry['report_time_start']);
        }
        if ($entry['report_time_end']) {
            $resourceLocalisation->setReportTimeEnd($entry['report_time_end']);
        }
        if ($entry['publication_time_start']) {
            $resourceLocalisation->setPublicationTimeStart($entry['publication_time_start']);
        }
        if ($entry['publication_time_end']) {
            $resourceLocalisation->setPublicationTimeEnd($entry['publication_time_end']);
        }

        if ($entry['is_still_updated']) {
            $resourceLocalisation->setIsStillUpdated($entry['is_still_updated']);
        }

        if ($entry['update_frequency']) {
            $resourceLocalisation->setUpdateFrequency($this->assocToEntityUpdateFrequency(
                $entry['update_frequency']
            ));
        }
        if ($entry['shelfmark']) {
            $resourceLocalisation->setShelfmark($entry['shelfmark']);
        }
        if ($entry['note']) {
            $resourceLocalisation->setNote($entry['note']);
        }
        if ($entry['local_note']) {
            $resourceLocalisation->setLocalNote($entry['local_note']);
        }
        if ($entry['isbn_issn']) {
            $resourceLocalisation->setIsbnIssn($entry['isbn_issn']);
        }
        if ($entry['instructions']) {
            $resourceLocalisation->setInstructions($entry['instructions']);
        }
        if ($entry['is_visible']) {
            $resourceLocalisation->setIsVisible($entry['is_visible']);
        }

        return $resourceLocalisation;
    }

    private function assocToEntityLicenseLocalisation(array $entry): LicenseLocalisation
    {
        $licenseLocalisation = new LicenseLocalisation(
            $entry['organisation'],
            (int) $entry['license']
        );
        if ($entry['id']) {
            $licenseLocalisation->setId($entry['id']);
        }
        if ($entry['internal_notes']) {
            $licenseLocalisation->setInternalNotes($entry['internal_notes']);
        }
        if ($entry['external_notes']) {
            $licenseLocalisation->setExternalNotes($entry['external_notes']);
        }
        if ($entry['aquired']) {
            $licenseLocalisation->setAquired($entry['aquired']);
        }
        if ($entry['cancelled']) {
            $licenseLocalisation->setCancelled($entry['cancelled']);
        }
        if ($entry['input_date']) {
            $licenseLocalisation->setInputDate($entry['input_date']);
        }
        if ($entry['last_check']) {
            $licenseLocalisation->setLastCheck($entry['last_check']);
        }
        return $licenseLocalisation;
    }

    private function assocToEntityAlternativeTitle(array $entry): AlternativeTitle
    {
        $altTitle = new AlternativeTitle(
            $entry['title'],
            $entry['id']
        );
        if ($entry['valid_from_date']) {
            $altTitle->setValidFromDate(new DateTime($entry['valid_from_date']));
        }
        if ($entry['valid_to_date']) {
            $altTitle->setValidToDate(new DateTime($entry['valid_to_date']));
        }
        return $altTitle;
    }

    private function assocToEntityApiUrl(array $entry): Url
    {
        return new Url(
            $entry['url'],
            $entry['id']
        );
    }

    public function createCollection(Collection $collection, $localOrganizationId = null): int
    {
        $sql = <<<EOD
            INSERT INTO collection (
                title,
                sort_by,
                is_subject,
                is_visible
            )  VALUES (
                :title,
                :sort_by,
                :is_subject,
                :is_visible
            );
        EOD;

        $params = $this->entityToAssocCollection($collection);
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            ":title" => $params[':title'],
            ":sort_by" => $params[':sort_by'],
            ":is_subject" => $params[':is_subject'],
            ":is_visible" => $params[':is_visible']
        ]);

        $collection_id = (int)$this->pdo->lastInsertId();
        $collection->setId($collection_id);

        $this->persistResourcesForCollection($collection);

        $this->persistCollectionForOrganisation($collection, $localOrganizationId);

        return $collection_id;
    }

    public function updateCollection(Collection $collection, string $localOrganizationId): void
    {
        $sql = <<<EOD
            UPDATE collection SET
                title=:title,
                sort_by=:sort_by,
                is_subject=:is_subject,
                is_visible=:is_visible
            WHERE id=:id;
        EOD;

        $params = $this->entityToAssocCollection($collection);

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        $this->persistResourcesForCollection($collection);
    }

    public function deleteCollection($collection, string $localOrganizationId)
    {
        $this->deleteTopResourcesForCollection($collection);
        $this->deleteResourcesForCollection($collection);
        $this->deleteCollectionForOrganisation($collection, $localOrganizationId);

        $sql = <<<EOD
            DELETE FROM collection
                WHERE id=:id;
        EOD;

        $params = ["id" => $collection->getId()];

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }

    private function deleteTopResourcesForCollection($collection)
    {
        $sql = "DELETE FROM top_resource_for_collection
                    WHERE collection=:id;";

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            "id" => $collection->getId()
        ]);
    }

    private function deleteCollectionForOrganisation($collection, $localOrganizationId)
    {
        $sql = "DELETE FROM collection_for_organisation
                    WHERE collection=:id AND organisation=:organisation;";

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            "id" => $collection->getId(),
            "organisation" => $localOrganizationId
        ]);
    }

    private function persistResourcesForCollection(Collection $collection)
    {
        $this->clearResourcesForCollection($collection);

        $resourceIds = $collection->getResourceIds();
        foreach ($resourceIds as $rId) {
            $sql = "INSERT INTO resource_for_collection (resource,collection)  "
                . "VALUES (:resource,:collection);";

            $statement = $this->pdo->prepare($sql);

            $statement->execute([
                "resource" => $rId,
                "collection" => $collection->getId()
            ]);
        }
    }

    private function clearResourcesForCollection($collection)
    {
        $sql = <<<EOD
                DELETE FROM resource_for_collection
                    WHERE collection=:collection
                EOD;
        $params = [];
        $params[':collection'] = $collection->getId();

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }

    private function persistCollectionForOrganisation(Collection $collection, string $localOrganizationId = null)
    {
        $sql = "INSERT INTO collection_for_organisation (collection, organisation)  "
            . "VALUES (:collection,:organisation);";

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            "collection" => $collection->getId(),
            "organisation" => $localOrganizationId
        ]);
    }

    private function deleteResourcesForCollection($collection)
    {
        $sql = "DELETE FROM resource_for_collection
                    WHERE collection=:collection;";

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            "collection" => $collection->getId()
        ]);
    }

    public function saveAccess($ubrId, $resourceId, $ip, $licenseType, $licenseForm, $accessType, $accessForm) {
        $sql = "INSERT INTO resources_accessed (organization, resource, ip, license_type, license_form, access_type, access_form) "
            . "VALUES (:organization, :resource, :ip, :license_type, :license_form, :access_type, :access_form);";

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            "organization" => $ubrId,
            "resource" => $resourceId,
            "ip" => $ip,
            "license_type" => $licenseType,
            "license_form" => $licenseForm,
            "access_type" => $accessType,
            "access_form" => $accessForm
        ]);

        if (is_null($ubrId)) {
            $ubrId = 'ALL';
        }

        $sql = "UPDATE resources_accessed_summary SET hits=hits+1 WHERE organization = :organization AND access_day = CURRENT_DATE;";

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            "organization" => $ubrId
        ]);

        $affectedRows = $statement->rowCount();

        if ($affectedRows < 1) {
            $sql = "INSERT INTO resources_accessed_summary (organization, access_day, hits) "
                . "VALUES (:organization, CURRENT_DATE, :hits);";

            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                "organization" => $ubrId,
                "hits" => 1
            ]);
        }
    }

    public function getDailyStatistics($days, $ubrId = null) {
        if ($ubrId) {
            $sql = "SELECT * FROM resources_accessed_summary WHERE organization = :organization and access_day >= CURRENT_DATE - (:days || ' DAYS')::INTERVAL ORDER BY access_day;";

            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                "organization" => $ubrId,
                "days" => $days
            ]);

            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // $sql = "SELECT * FROM resources_accessed_summary WHERE organization = 'ALL' and access_day >= CURRENT_DATE - (:days || ' DAYS')::INTERVAL ORDER BY access_day;";
            $sql = "SELECT access_day, SUM(hits) AS total_hits FROM resources_accessed_summary WHERE (organization = 'ALL' OR organization IS NULL) AND access_day >= CURRENT_DATE - (:days || ' DAYS')::INTERVAL GROUP BY access_day ORDER BY access_day;";
            
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                "days" => $days
            ]);

            return $statement->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    public function getSelectableStatistics($fromMonth, $toMonth, $fromYear, $toYear, $ubrId = null, $licenseTypes = [], $subjects = [], $allOrganisations = null) {
        $fromMonth = stripslashes($fromMonth);
        $toMonth = stripslashes($toMonth);
        $fromYear = stripslashes($fromYear);
        $toYear = stripslashes($toYear);

        $fromDate = $fromYear . '-' . $fromMonth . '-01';
        $daysInMonth = (int)$toMonth == 2 ? ((int)$toYear % 4 ? 28 : ((int)$toYear % 100 ? 29 : ((int)$toYear % 400 ? 28 : 29))) : (((int)$toMonth - 1) % 7 % 2 ? 30 : 31);
        $toDate = $toYear . '-' . $toMonth . '-' . $daysInMonth;
        $dateFormat = 'YYYY-MM-DD';
        $dateTrunc = 'month';

        $joins = [];
        $conditions = [];

        $sqlLicenseTypes = null;
        // License type always needs to be joined
        // $joins[] = "join license_type on license_type.id = resources_accessed.license_type";

        $joins = array();

        $sqlSubjects = "";
        $sqlCollections = "";
        $sqlConnectSubjectConditions = "";
        $sqlConnectCollectionConditions = "";
        if (count($subjects) > 0) {
            foreach($subjects as $subject) {
                $subjectId = (int) $subject['id'];
                if ($subject['is_collection']) {
                    $sqlCollections .= "resource_for_collection.collection = " . $subjectId . " OR ";
                } else {
                    $sqlSubjects .= $subjectId . ",";
                }
            }

            if (strlen($sqlCollections) > 0) {
                $sqlConnectCollectionConditions = " AND ";
                $joins[] = "join resource_for_collection on resource_for_collection.resource = resource.id";
                if (substr($sqlCollections, -4) === " OR ") {
                    $sqlCollections = rtrim($sqlCollections, " OR ");
                }
                $sqlCollections = "($sqlCollections)";
                // $joins[] = "join (select distinct resource from resource_for_collection where ($sqlCollections)) as filtered_collections on filtered_collections.resource = resource.id";
                
                // $conditions[] = "($sqlCollections)";
            }
            
            if (strlen($sqlSubjects) > 0) {
                $sqlSubjects = substr_replace($sqlSubjects, '', -1);
                $sqlConnectSubjectConditions = " and prioritized_subjects.subject in ($sqlSubjects) ";
                $joins[] = "join prioritized_subjects on prioritized_subjects.resource = resource.id";
                // $joins[] = "join (select distinct resource from subject_for_resource where ($sqlSubjects)) as filtered_subjects on filtered_subjects.resource = resource.id";
                
                // $conditions[] = "($sqlSubjects)";
            }
        }

        if ($ubrId && is_null($allOrganisations)) {
            if (count($licenseTypes) > 0) {
                $sqlLicenseTypes = implode(" OR ", array_map(function($id) {
                    return "license.type = " . $id;
                }, $licenseTypes));
    
                $conditions[] = "($sqlLicenseTypes)";
            }

            $connectConditions = "";
            if (count($conditions) > 0) {
                $connectConditions = " AND ";
            }          
            $connectedConditions = implode(" AND ", $conditions);
            $connectedJoins = implode(" ", $joins);

            $sql = <<<EOD
                WITH prioritized_subjects AS (
                    SELECT DISTINCT ON (resource, subject)
                        resource,
                        subject,
                        organisation
                    FROM
                        subject_for_resource
                    WHERE
                        organisation = :organization OR organisation IS NULL
                    ORDER BY
                        resource,
                        subject,
                        CASE WHEN organisation = :organization THEN 1 ELSE 2 END
                )
                select
                    licensed_resources.title,
                    licensed_resources.resource_id as resource,
                    licensed_resources.license_type_title AS license_type_title,
                    COUNT(resources_accessed.resource) as hits
                from
                    (
                    select
                        distinct on
                        (resource.id)
                        resource.title,
                        resource.id as resource_id,
                        license_type.title as license_type_title
                    from
                        resource
                    join license on
                        license.resource = resource.id
                    join license_type on
                        license_type.id = license.type
                    join license_for_organization on
                        license_for_organization.license = license.id
                    $connectedJoins
                    where
                        license_for_organization.organization = :organization
                        and resource.is_visible = true
                        $sqlConnectCollectionConditions $sqlCollections
                        $sqlConnectSubjectConditions
                        $connectConditions $connectedConditions
                    order by
                        resource.id,
                        license.id
                    ) as licensed_resources
                left join resources_accessed on
                    licensed_resources.resource_id = resources_accessed.resource
                    and resources_accessed.organization = :organization
                    and resources_accessed.access_time between TO_TIMESTAMP(:fromDate, :dateFormat)::TIMESTAMP
                                                        and TO_TIMESTAMP(:toDate, :dateFormat)::TIMESTAMP
                group by
                    licensed_resources.resource_id,
                    licensed_resources.title,
                    licensed_resources.license_type_title
                order by
                    hits desc;
            EOD;
            
            /*
            echo($ubrId);
            echo("</br>");
            echo($sql);
            echo("</br>");
            echo($fromDate);
            echo("</br>");
            echo($toDate);
            echo("</br>");
            echo($dateFormat);
            echo("</br>");
            echo($sqlConnectSubjectConditions);
            */
        

            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                "organization" => $ubrId,
                "fromDate" => $fromDate,
                "dateFormat" => $dateFormat,
                "toDate" => $toDate
            ]);

            $results = $statement->fetchAll(PDO::FETCH_ASSOC);

            $results = array_map(function ($assoc) {
                $assoc['license_type_title'] = isset($assoc['license_type_title']) ? json_decode($assoc['license_type_title'], true): null;
                return $assoc;
            }, $results);

            return $results;
        } else {
            $connectSqlSubjects = "";
            if ($sqlSubjects) {
                $connectSqlSubjects = " AND subject_for_resource.subject in ($sqlSubjects)";
            }

            if (count($licenseTypes) > 0) {
                $sqlLicenseTypes = implode(" OR ", array_map(function($id) {
                    return "unique_accesses.license_type = " . $id;
                }, $licenseTypes));
    
                $conditions[] = "($sqlLicenseTypes)";
            }

            $connectConditions = "";
            if (count($conditions) > 0) {
                $connectConditions = " WHERE ";
            }          
            $connectedConditions = implode(" AND ", $conditions);

            $sql = <<<EOD
                with unique_accesses as (
                select
                    distinct on
                    (resources_accessed.resource,
                    resources_accessed.access_time)
                        resources_accessed.resource,
                    resources_accessed.access_time,
                    resources_accessed.license_type
                from
                    resources_accessed
                left join subject_for_resource on
                    subject_for_resource.resource = resources_accessed.resource
                    and subject_for_resource.organisation is null
                where
                    resources_accessed.access_time between TO_TIMESTAMP(:fromDate, :dateFormat)::TIMESTAMP
                                                        and TO_TIMESTAMP(:toDate, :dateFormat)::TIMESTAMP
                    $connectSqlSubjects
                )
                select
                    resource.id as resource,
                    resource.title,
                    license_type.title as license_type_title,
                    coalesce(COUNT(unique_accesses.resource), 0) as hits
                from
                    resource
                left join unique_accesses on
                    unique_accesses.resource = resource.id
                left join license_type on
                    unique_accesses.license_type = license_type.id
                $connectConditions $connectedConditions
                group by
                    resource.id,
                    resource.title,
                    license_type.title
                order by
                    hits desc;
            EOD;

            /*
            echo($sql);
            echo("</br>");
            echo($fromDate);
            echo("</br>");
            echo($toDate);
            echo("</br>");
            echo($dateFormat);
            */            
            
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                "fromDate" => $fromDate,
                "dateFormat" => $dateFormat,
                "toDate" => $toDate
            ]);

            $results = $statement->fetchAll(PDO::FETCH_ASSOC);

            $results = array_map(function ($assoc) {
                $assoc['license_type_title'] = isset($assoc['license_type_title']) ? json_decode($assoc['license_type_title'], true): null;
                return $assoc;
            }, $results);

            return $results;
        }
    }

    public function getLabels($ubrId) {
        if (is_null($ubrId)) {
            $sql = "SELECT lfo.*, EXISTS (SELECT 1 FROM access WHERE access.label_id = lfo.id) AS is_referenced FROM labels_for_organisation lfo WHERE lfo.organisation is null and is_for_free_resources = false ORDER BY lfo.id;";

            $statement = $this->pdo->prepare($sql);
            $statement->execute();
        } else {
            $sql = "SELECT lfo.*, EXISTS (SELECT 1 FROM access WHERE access.label_id = lfo.id) AS is_referenced FROM labels_for_organisation lfo WHERE lfo.organisation = :organisation ORDER BY lfo.id;";

            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                "organisation" => $ubrId
            ]);
        }
        
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        $results = array_map(function ($assoc) {
            $assoc['label'] = isset($assoc['label']) ? json_decode($assoc['label'], true): null;
            $assoc['label_long'] = isset($assoc['label_long']) ? json_decode($assoc['label_long'], true): null;
            $assoc['label_longest'] = isset($assoc['label_longest']) ? json_decode($assoc['label_longest'], true): null;
            $assoc['is_referenced'] = (bool) $assoc['is_referenced'];
            $assoc['is_for_license_types'] = isset($assoc['is_for_license_types']) ? json_decode($assoc['is_for_license_types'], true): null;
            return $assoc;
        }, $results);

        return $results;
    }

    public function saveLabels($labels, $ubrId) {
        foreach($labels as $label) {
            $label_id = $label['id'];
            $label['label'] = json_encode($label['label']);
            $label['label_long'] = $label['label_long'] ? json_encode($label['label_long']) : null;
            $label['label_longest'] = $label['label_longest'] ? json_encode($label['label_longest']) : null;

            if (is_null($label_id)) {
                if (is_null($label["is_for_license_type"])) {
                    $sql = "INSERT INTO labels_for_organisation (organisation, label, label_long, label_longest) VALUES (:organisation, :label, :label_long, :label_longest);";

                    $statement = $this->pdo->prepare($sql);
                    $statement->execute([
                        "organisation" => $ubrId,
                        "label" => $label['label'],
                        "label_long" => $label['label_long'],
                        "label_longest" => $label['label_longest']
                    ]);
                } else {
                    $sql = "INSERT INTO labels_for_organisation (label, label_long, label_longest, is_for_license_type) VALUES (:label, :label_long, :label_longest, :is_for_license_type);";

                    $statement = $this->pdo->prepare($sql);
                    $statement->execute([
                        "label" => $label['label'],
                        "label_long" => $label['label_long'],
                        "label_longest" => $label['label_longest'],
                        "is_for_license_type" => $label['is_for_license_type']
                    ]);
                }
            } else {
                $sql = "UPDATE labels_for_organisation SET label = :label, label_long = :label_long, label_longest = :label_longest WHERE id = :label_id;";

                $statement = $this->pdo->prepare($sql);
                $statement->execute([
                    "label_id" => $label_id,
                    "label" => $label['label'],
                    "label_long" => $label['label_long'],
                    "label_longest" => $label['label_longest']
                ]);
            }
        }
    }

    public function deleteLabel($labelId) {
        $sql = "UPDATE access SET label_id = NULL WHERE label_id=:labelId;";

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            "labelId" => $labelId
        ]);

        $sql = "DELETE FROM labels_for_organisation WHERE id=:labelId;";

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            "labelId" => $labelId
        ]);
    }

    public function mergeLabels($label_id_of_label_to_merge_from, $label_id_of_label_to_merge_into){
        $sql = "UPDATE access SET label_id = :label_id_of_label_to_merge_into WHERE label_id=:label_id_of_label_to_merge_from;";

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            "label_id_of_label_to_merge_into" => $label_id_of_label_to_merge_into,
            "label_id_of_label_to_merge_from" => $label_id_of_label_to_merge_from
        ]);

        $sql = "DELETE FROM labels_for_organisation WHERE id=:label_id_of_label_to_merge_from;";

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            "label_id_of_label_to_merge_from" => $label_id_of_label_to_merge_from
        ]);
    }

    public function getPrivilegesOfOrganisation($ubrId) {
        $sql = "select p.*, pa.name from privilege p left join privilege_addon_for_privilege pafp on p.id = pafp.privilege left join privilege_addon pa on pafp.privilege_addon = pa.id where p.organization = :organization;";

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            "organization" => $ubrId
        ]);

        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $results;
    }

    public function getOrganisationsWithLicense($resourceId) {
        $sql = "select DISTINCT ON (organization) organization, license from license_for_organization join license on license_for_organization.license = license.id where license.resource = :resourceId and license.is_active = True;";

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            "resourceId" => $resourceId
        ]);

        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $results;
    }

    public function isUrlContainedInDbis($url) {
        $url = trim($url);
        //$sql = "SELECT * FROM access WHERE access_url = :url;";
        $sql = "SELECT * FROM access WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(access_url, '%22', '\"'), '%20', ' '), '%3A', ':'), '%2F', '/'), '%40', '@'), '%3B', ';'), '%3F', '?') = :url;";

	    $sql = "SELECT * FROM access WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(access_url, '%22', '\"'), '%20', ' '), '%3A', ':'), '%2F', '/'), '%40', '@'), '%3B', ';'), '%3F', '?') = :url;";

        $decodedUrl = urldecode($url);

        $sql = "SELECT * FROM access WHERE TRIM(access_url) = TRIM(:url)";

        /*
        $sql = "SELECT * FROM access WHERE REPLACE(" .
       "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(" .
       "UPPER(access_url::text), '%22', '\"'), '%20', ' '), '%3A', ':'), '%2F', '/'), '%40', '@'), " .
       "'%3B', ';'), '%3F', '?'), '%2B', '+'), '%25', '%'), '%26', '&') = :url";

       $sql = "SELECT * FROM access WHERE REPLACE(" .
       "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(" .
       "REPLACE(UPPER(access_url::text), '%22', '\"'), '%20', ' '), '%3A', ':'), '%2F', '/'), '%40', '@'), " .
       "'%3B', ';'), '%3F', '?'), '%2B', '+'), '%25', '%'), '%26', '&'), '%3D', '=') = :url";
        */
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            "url" => $decodedUrl
        ]);

        if ($statement->rowCount() > 0) {
            return true;
        } else {
            return false;
        }
    }

    public function getSecret() {
        $sql = "SELECT secret_key FROM secret_key WHERE key_name = 'dbis_key';";
        $statement = $this->pdo->prepare($sql);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result['secret_key'];
    }

    public function generateHash($url) {
        if ($url) {
            $secretKey = $this->getSecret();

            return hash_hmac('sha256', $url, $secretKey);
        } else {
            return null;
        }
    }

    public function isUrlSafe($accessId) {
        if (is_null($accessId)) {
            return null;
        }

        $sql = "SELECT access_url FROM access WHERE id = :accessId;";
        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            'accessId' => $accessId
        ]);

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return $result['access_url'];
        } else {
            return null;
        }   
    }

    public function getAccessId($ubrId, $resourceId, $licenseType) {
        $results = [];

        if ($licenseType) {
            $sql = "SELECT * FROM license WHERE resource = :resourceId and type = :licenseType;";
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'resourceId' => $resourceId,
                'licenseType' => $licenseType
            ]);

            $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $sql = "SELECT * FROM license WHERE resource = :resourceId;";
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'resourceId' => $resourceId
            ]);

            $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        }
        
        $licenseId = null;

        if ($ubrId) {
            foreach($results as $result) {
                $tmpLicenseId = (int) $result['id'];

                $sql = "SELECT * FROM license_for_organization WHERE license = :licenseId and organization = :ubrId;";
                $statement = $this->pdo->prepare($sql);
                $statement->execute([
                    'licenseId' => $tmpLicenseId,
                    'ubrId' => $ubrId
                ]);

                $result_license_for_organization = $statement->fetch(PDO::FETCH_ASSOC);

                // TODO: What if multiple licenses exists
                if ($result_license_for_organization) {
                    $licenseId = $tmpLicenseId;

                    $sql_accesses = "SELECT * FROM access WHERE license = :licenseId;";
                    $statement_accesses = $this->pdo->prepare($sql_accesses);
                    $statement_accesses->execute([
                        'licenseId' => $licenseId
                    ]);
    
                    $results_accesses = $statement_accesses->fetchAll(PDO::FETCH_ASSOC);
    
                    if ($results_accesses) {
                        foreach($results_accesses as $result_access) {
                            $accessUrl = $result_access['access_url'];
    
                            if ($accessUrl && strlen($accessUrl) > 0) {
                                $accessId = $result_access['id'];
                                return $accessId;
                            }
                        }
                    } 
                } 
            }
        } else {
            foreach($results as $result) {
                $licenseId = (int) $result['id'];

                $sql = "SELECT * FROM access WHERE license = :licenseId and organization is null;";
                $statement = $this->pdo->prepare($sql);
                $statement->execute([
                    'licenseId' => $licenseId
                ]);

                $results_accesses = $statement->fetchAll(PDO::FETCH_ASSOC);

                if ($results_accesses) {
                    foreach($results_accesses as $result_access) {
                        $accessUrl = $result_access['access_url'];

                        if ($accessUrl && strlen($accessUrl) > 0) {
                            $accessId = $result_access['id'];
                            return $accessId;
                        }
                    }
                } 
            }
        }

        return null;
    }

    public function getNewAccessIdForElasticSearch($license, $access) {
        $accessId = null;

        $licenseId = (int) $license['id'];
        $accessUrl = $access['access_url'];

        $ubrId = $access['organization'];

        if($ubrId && !is_null($ubrId)) {
            $sql = "SELECT * FROM access WHERE license = :licenseId and access_url = :accessUrl and organization = :ubrId;";
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'licenseId' => $licenseId,
                'accessUrl' => $accessUrl,
                'ubrId' => $ubrId
            ]);
        } else {
            $sql = "SELECT * FROM access WHERE license = :licenseId and access_url = :accessUrl and organization is null;";
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'licenseId' => $licenseId,
                'accessUrl' => $accessUrl
            ]);
        }

        $results_accesses = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($results_accesses) {
            foreach($results_accesses as $result_access) {
                $accessUrl = $result_access['access_url'];
                
                // TODO: What if more accesses have been found?
                $accessId = (int) $result_access['id'];
            }
        } 

        return $accessId;
    }

    public function getSortTypes(): array
    {
        $sql = "SELECT * FROM sort where id != 2;";

        $statement = $this->pdo->prepare($sql);

        $statement->execute();

        $sort_types_assoc = $statement->fetchAll(PDO::FETCH_ASSOC);

        $sort_types_obj = array();

        foreach ($sort_types_assoc as $entry) {
            $entry['title'] = json_decode($entry['title'], true);
            $sort_types_obj[] = $this->assocToEntitySortType($entry);
        }

        return $sort_types_obj;
    }

    public function getCollections(array $options = null): array
    {
        $q = $options['q'] ?? null;
        $id = $options['id'] ?? null;
        $organizationId = $options['organizationId'] ?? null;
        $only_subjects = $options['only_subjects'] ?? null;
        $only_visibles = $options['only_visibles'] ?? null;
        $without_resources = $options['without_resources'] ?? null;

        if ($without_resources) {
            $sql = <<<EOD
                SELECT collection.*,
                       TO_JSON(sb.*) as sort_type
                FROM collection
                    LEFT JOIN sort as sb
                        ON collection.sort_by = sb.id
                    LEFT JOIN collection_for_organisation as cfr
                        ON collection.id = cfr.collection
                WHERE TRUE
            EOD;

            $params = [];

            if ($q) {
                $sql .= " AND ((collection.title->>'de' LIKE CONCAT('%', :q::text, '%') "
                    . "OR collection.title->>'en' LIKE CONCAT('%', :q::text, '%') )) ";
                $params['q'] = $q;
            }

            if ($id) {
                $sql .= " AND collection.id = :id::integer ";
                $params['id'] = $id;
            }

            if ($only_subjects) {
                $sql .= " AND collection.is_subject = 't' ";
            }

            if ($only_visibles) {
                $sql .= " AND collection.is_visible = 't' ";
            }

            // Handle retrieving a collection as viewed from an organization
            if ($organizationId) {
                $sql .= " AND (cfr.organisation=:orgId) ";
                $params[':orgId'] = $organizationId;
            } else {
                $sql .= " AND cfr.organisation IS NULL ";
            }

            $sql .= " GROUP BY collection.id, sb.id;";

            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);
            $collections_assoc = $statement->fetchAll(PDO::FETCH_ASSOC);

            $collections_obj = array();
            foreach ($collections_assoc as $entry) {
                $entry['title'] = json_decode($entry['title'], true);
                $entry['sort_type'] = json_decode($entry['sort_type'], true);
        
                $collection = $this->assocToEntityCollection($entry);
                $collections_obj[] = $collection;
            }
            return $collections_obj;
        } else {
            $sql = <<<EOD
                SELECT collection.*,
                       TO_JSON(sb.*) as sort_type,
                       COALESCE(json_agg(DISTINCT r.id) FILTER (WHERE r.id IS NOT NULL), '[]') as resources
                FROM collection
                    LEFT JOIN sort as sb
                        ON collection.sort_by = sb.id
                    LEFT JOIN collection_for_organisation as cfr
                        ON collection.id = cfr.collection
                    LEFT JOIN resource_for_collection as rfc
                        ON collection.id = rfc.collection
                        LEFT JOIN resource as r
                            ON r.id = rfc.resource
                WHERE TRUE
            EOD;

            $params = [];

            if ($q) {
                $sql .= " AND ((collection.title->>'de' LIKE CONCAT('%', :q::text, '%') "
                    . "OR collection.title->>'en' LIKE CONCAT('%', :q::text, '%') )) ";
                $params['q'] = $q;
            }

            if ($id) {
                $sql .= " AND collection.id = :id::integer ";
                $params['id'] = $id;
            }

            if ($only_subjects) {
                $sql .= " AND collection.is_subject = 't' ";
            }

            if ($only_visibles) {
                $sql .= " AND collection.is_visible = 't' ";
            }

            // Handle retrieving a collection as viewed from an organization
            if ($organizationId) {
                $sql .= " AND (cfr.organisation=:orgId) ";
                $params[':orgId'] = $organizationId;
            } else {
                $sql .= " AND cfr.organisation IS NULL ";
            }

            $sql .= " GROUP BY collection.id, sb.id;";

            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);
            $collections_assoc = $statement->fetchAll(PDO::FETCH_ASSOC);

            $collections_obj = array();
            foreach ($collections_assoc as $entry) {
                $entry['title'] = json_decode($entry['title'], true);
                $entry['sort_type'] = json_decode($entry['sort_type'], true);
                if ($entry['resources']) {
                    $entry['resources'] = json_decode($entry['resources']);
                }
        
                $collection = $this->assocToEntityCollection($entry);
                $collections_obj[] = $collection;
            }
            return $collections_obj;
        }
    }

    /**
     * @throws CollectionNotFoundException
     */
    public function getCollectionById(int $id, string $localOrganizationId = null): ?Collection
    {
        $results = $this->getCollections([
            "id" => $id,
            "organizationId" => $localOrganizationId
        ]);
        if (count($results) == 0) {
            throw new CollectionNotFoundException($id);
        }
        return (count($results) > 0) ? $results[0] : null;
    }

    public function getCollectionIdByOrgAndNotation(string $organizationId, string $notation): ?int
    {
        $sql = <<<EOD
        SELECT collection.id
        FROM collection
        LEFT JOIN collection_for_organisation as cfr ON collection.id = cfr.collection
        WHERE collection.notation = :notation AND cfr.organisation = :orgId
        LIMIT 1;
        EOD;

        $statement = $this->pdo->prepare($sql);
        $statement->execute([
            ":notation" => $notation,
            ":orgId" => $organizationId
        ]);
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as $entry) {
            return $entry['id'];
        }

        return null;
    }

    /**
     *
     * @return array<integer, integer>
     */
    public function countResourcesByCollection(): array
    {
        $sql = <<<EOD
                SELECT collection, COUNT(*) FROM resource_for_collection GROUP BY collection
                EOD;
        $statement = $this->pdo->prepare($sql);
        $statement->execute();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_reduce($result, function ($carry, $item) {
            $carry[(int)$item['collection']] = $item['count'];
            return $carry;
        }, array());
    }

    public function getRelationships($resource_id) {
        $params = [];
        $params['resource'] = $resource_id;

        $sql = <<<EOD
                SELECT * FROM relation_for_resource WHERE resource=:resource OR related_to_resource=:resource;
                EOD;
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }

    public function updateRelationships($resource_id, $related_databases, $top_databases, $sub_databases)
    {
        $params = [];

        $sql = <<<EOD
                DELETE FROM relation_for_resource WHERE resource=:resource OR related_to_resource=:resource;
                EOD;
            $params['resource'] = $resource_id;
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($related_databases as $value) {
            $sql = <<<EOD
                INSERT INTO relation_for_resource (resource, related_to_resource, relationship_type) VALUES (:resource, :related_to_resource, :relationship_type);
                EOD;
            // $params['resource'] = $resource_id;
            $params['related_to_resource'] = $value;
            $params['relationship_type'] = 'is-related';
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        }

        foreach ($top_databases as &$value) {
            /*
            $sql = <<<EOD
                DELETE FROM relation_for_resource WHERE ((resource=:resource AND related_to_resource=:related_to_resource) OR (resource=:related_to_resource AND related_to_resource=:resource)) AND relationship_type=:relationship_type;
                EOD;
            $params['resource'] = $resource_id;
            $params['related_to_resource'] = $value;
            $params['relationship_type'] = 'is-child';
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);
            */
            $sql = <<<EOD
                INSERT INTO relation_for_resource (resource, related_to_resource, relationship_type) VALUES (:resource, :related_to_resource, :relationship_type);
                EOD;
            // $params['resource'] = $resource_id;
            $params['related_to_resource'] = $value;
            $params['relationship_type'] = 'is-child';
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        }

        foreach ($sub_databases as &$value) {
            /*
            $sql = <<<EOD
                DELETE FROM relation_for_resource WHERE ((resource=:resource AND related_to_resource=:related_to_resource) OR (resource=:related_to_resource AND related_to_resource=:resource)) AND relationship_type=:relationship_type;
                EOD;
            $params['resource'] = $resource_id;
            $params['related_to_resource'] = $value;
            $params['relationship_type'] = 'is-parent';
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);
            */
            $sql = <<<EOD
                INSERT INTO relation_for_resource (resource, related_to_resource, relationship_type) VALUES (:resource, :related_to_resource, :relationship_type);
                EOD;
            // $params['resource'] = $resource_id;
            $params['related_to_resource'] = $value;
            $params['relationship_type'] = 'is-parent';
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    /**
     * Create assoc array for pdo
     * @param Collection $collection
     * @return array
     */
    private function entityToAssocCollection(Collection $collection): array
    {
        $params = [
            ":title" => json_encode($collection->getTitle())
        ];

        $collection->getId() ? $params[":id"] = $collection->getId() : $params[":id"] = null;

        $is_visible = $collection->isVisible();
        $params[":is_visible"] = $is_visible ?: null;

        $is_subject = $collection->isSubject();
        $params[":is_subject"] = $is_subject ?: null;

        $sort_by = $collection->getSortBy() ? $collection->getSortBy()->getId() : null;
        $params[":sort_by"] = $sort_by;

        return $params;
    }

    private function assocToEntitySortType($entry): SortType
    {
        $sort_type = new SortType($entry['id']);

        if ($entry['title']) {
            $sort_type->setTitle($entry['title']);
        }
        
        return $sort_type;
    }

    private function assocToEntityExternalID($externalIdAssoc): ExternalID
    {
        // Cast needed as $externalIdAssoc['id'] is an integer
        $external_id = (string) $externalIdAssoc['external_id'];
        $external_id_name = (string) $externalIdAssoc['external_id_name'];
        return new ExternalID($externalIdAssoc['namespace'], $external_id, $external_id_name);
    }

    private function assocToEntityCollection($entry): Collection
    {
        $collection = new Collection(
            $entry['title']
        );

        $collection->setId($entry['id']);
        if ($entry['notation']) {
            $collection->setNotation($entry['notation']);
        }

        if ($entry['sort_by']) {
            $sort_type = array_key_exists('sort_type', $entry) ? $entry['sort_type'] : null;
            $title = !is_null($sort_type) && array_key_exists('title', $sort_type) ? $sort_type['title'] : null;
            $collection->setSortBy($this->assocToEntitySortType(
                array('id' => $entry['sort_by'], 'title' => $title)
            ));
        }
        if ($entry['is_visible']) {
            $collection->setIsVisible($entry['is_visible']);
        }
        if ($entry['is_subject']) {
            $collection->setIsSubject($entry['is_subject']);
        }
        if (array_key_exists('resources', $entry) && $entry['resources']) {
            $collection->setResourceIds($entry['resources']);
        }
        return $collection;
    }

    private function filterLocalizedEntries(
        array $entry,
        string $organizationId = null,
        string $localisation = self::COMBINED
    ) {
        $entityTables = [
            array(
                'entity' => 'authors', 'relation' => 'authors_for_resource', 'foreign_id' => 'author'
            ),
            array(
                'entity' => 'keywords', 'relation' => 'keywords_for_resource', 'foreign_id' => 'keyword'
            ),
            array(
                'entity' => 'resource_types', 'relation' => 'resource_types_for_resource',
                'foreign_id' => 'resource_type'
            ),
            array(
                'entity' => 'countries', 'relation' => 'countries_for_resource', 'foreign_id' => 'country'
            ),
            array(
                'entity' => 'subjects', 'relation' => 'subjects_for_resource', 'foreign_id' => 'subject'
            )];

        $resolveTables = [
            array('entity' => 'update_frequency', 'collection' => 'update_frequencies')
        ];

        $connectTables = [];

        /*
         * Loop for 1:n relation tables that have been joined in the SQL statement.
         */
        foreach ($entityTables as $value) {
            $entityTable = $value['entity'];
            $relationTable = $value['relation'];
            $foreignId = $value['foreign_id'];
            $entityToKeep = [];

            if ($organizationId == null || $localisation === self::GLOBAL) {
                $entityToKeep = array_filter($entry[$relationTable], function ($value, $key) {
                    return !isset($value['organisation']);
                }, ARRAY_FILTER_USE_BOTH);
            } else {
                if ($localisation === self::COMBINED) {
                    // Get local as well as global values.
                    $entityToKeep = array_filter($entry[$relationTable], function ($value, $key) {
                        return (isset($value['organisation']) && $value['organisation'] > 0) ||
                            !isset($value['organisation']);
                    }, ARRAY_FILTER_USE_BOTH);
                } elseif ($localisation === self::LOCAL) {
                    // Get local values only.
                    $entityToKeep = array_filter($entry[$relationTable], function ($value, $key) {
                        return (isset($value['organisation']) && $value['organisation'] > 0);
                    }, ARRAY_FILTER_USE_BOTH);
                }
            }

            // Remove all except the relations to be kept.
            foreach ($entry[$entityTable] as $entityKey => $entity) {
                $i = in_array($entity['id'], array_column($entityToKeep, $foreignId));
                if ($i === false) {
                    unset($entry[$entityTable][$entityKey]);
                }
            }
        }

        /*
         * Loop for 1:1 relations where a value is present at resource that has to be
         * resolved with information from one other table (without a middle-table like
         * keyword_for_resource.
         *
         */
        foreach ($resolveTables as $value) {
            $entity = $value['entity'];
            $collection = $value['collection'];

            // Overwrite the int/ID with the actual information from the resolve table
            foreach ($entry[$collection] as $coll) {
                if ($entry[$entity] == $coll['id']) {
                    $entry[$entity] = $coll;
                }
                if (count($entry['resource_localisation']) > 0) {
                    if ($entry['resource_localisation'][0][$entity] == $coll['id']) {
                        $entry['resource_localisation'][0][$entity] = $coll;
                    }
                }
            }
        }

        /*
         * Loop for 1:1 relations where there is no value present at resource. Instead,
         * the information that information is related to the resource is only stored
         * inside this table.
         */
        foreach ($connectTables as $table) {
            // Remove all local (with orgId) or global (without orgId) rows
            if ($organizationId == null || $localisation === self::GLOBAL) {
                $entry[$table] = array_filter($entry[$table], function ($value, $key) {
                    return !(isset($value['organisation']));
                }, ARRAY_FILTER_USE_BOTH);
            } else {
                if ($localisation === self::COMBINED) {
                    $local_values_exist = false;
                    foreach ($entry[$table] as $entry_of_connected_table) {
                        $local_values_exist = isset($entry_of_connected_table['organisation']);
                    }
                    if ($local_values_exist) {
                        $entry[$table] = array_filter($entry[$table], function ($value, $key) {
                            return isset($value['organisation']);
                        }, ARRAY_FILTER_USE_BOTH);
                    }
                } elseif ($localisation === self::LOCAL) {
                    $entry[$table] = array_filter($entry[$table], function ($value, $key) {
                        return isset($value['organisation']);
                    }, ARRAY_FILTER_USE_BOTH);
                }
            }
        }

        /*
         * Loop for standard column values.
         * If a local resource is requested, replace global values with all local values.
         */
        if ($localisation === self::COMBINED && count($entry['resource_localisation']) > 0) {
            $localResource = $entry['resource_localisation'][0];
            // Just if a local value is set use it.
            foreach ($localResource as $key => $value) {
                if (!is_null($value)) {
                    if (is_array($value) && (array_key_exists('de', $value) || array_key_exists('en', $value))) {
                        if ((strlen($value['de']) > 0 || strlen($value['en']) > 0)) {
                            $entry[$key] = $value;
                        }
                    } else {
                        $entry[$key] = $value;
                    }
                }
            }

            // Set the correct ID for local resources, since the
            // localization table has a different structure
            // ("id" is the SQL row id/primary key, "resource" is the resourceId)
            $entry["id"] = $entry["resource"];
        } elseif ($localisation === self::LOCAL) {
            if (count($entry['resource_localisation']) > 0) {
                $localResource = $entry['resource_localisation'][0];
                // Even if the local value is null, keep it.
                foreach ($localResource as $key => $value) {
                    $entry[$key] = $value;
                }
            } else {
                // Set all values null since no localization set exists
                foreach ($entry as $key => $value) {
                    if (is_array($value)) {
                        $entry[$key] = [];
                    } else {
                        $entry[$key] = null;
                    }
                }
            }
        }
        return $entry;
    }

    private function clearExternalIdsForResource(Resource $resource)
    {
        $params = array();
        $sql = "DELETE FROM external_resource_id WHERE resource=:id;";

        $params['id'] = $resource->getId();
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }

    private function clearExternalIdsForLicense(License $license)
    {
        $params = array();

        $sql = "DELETE FROM external_license_id WHERE license=:id;";

        $params['id'] = $license->getId();
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }
}
