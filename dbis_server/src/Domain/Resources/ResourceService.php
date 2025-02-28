<?php

declare(strict_types=1);

namespace App\Domain\Resources;

use App\Domain\Resources\Constants\Constants;
use App\Domain\Resources\Entities\AccessMapping;
use App\Domain\Resources\Entities\Author;
use App\Domain\Resources\Entities\Collection;
use App\Domain\Resources\Entities\Keyword;
use App\Domain\Resources\Entities\SortType;
use App\Domain\Resources\Entities\Subject;
use App\Domain\Resources\Entities\ResourceAggregate;
use App\Domain\Resources\Entities\UpdateFrequency;
use App\Domain\Resources\Entities\Type;
use App\Domain\Resources\Entities\License;
use App\Domain\Resources\Entities\LicenseType;
use App\Domain\Resources\Entities\LicenseForm;
use App\Domain\Resources\Entities\Access;
use App\Domain\Resources\Entities\Host;
use App\Domain\Resources\Entities\AccessType;
use App\Domain\Resources\Entities\Enterprise;
use App\Domain\Resources\Exceptions\CollectionNotFoundException;
use App\Domain\Resources\Exceptions\LicenseAlreadyExistingException;
use App\Domain\Resources\Exceptions\LicenseNotFoundException;
use App\Domain\Resources\Entities\Resource;
use App\Domain\Resources\Entities\Country;
use App\Infrastructure\Resources\ResourceRepository;

/**
 * ResourceService
 *
 * Description of Repository functions to be implemented in the interface layer.
 */
class ResourceService
{
    /** @type ResourceRepositoryInterface */
    private $repository;

    public function __construct(
        ResourceRepository $repo
    ) {
        $this->repository = $repo;
    }


    public function updateResource(
        Resource $resourceGlobal,
        string $localOrganizationId = null,
        Resource $resourceLocal = null,
        Resource $previousResourceGlobal = null
    ): void {
        $this->repository->updateResource($resourceGlobal, $localOrganizationId, $resourceLocal, $previousResourceGlobal);
    }

    public function createResource(
        Resource $resourceGlobal,
        string $localOrganizationId = null,
        Resource $resourceLocal = null
    ): int {
        return $this->repository->createResource($resourceGlobal, $localOrganizationId, $resourceLocal);
    }

    public function removeResource(
        Resource $resource,
        string $localOrganizationId = null,
        bool $isSuperAdmin = false
    ): void {
        $this->repository->removeResource($resource, $localOrganizationId, $isSuperAdmin);
    }

    public function saveAccess($ubrId, $resourceId, $ip, $licenseType, $licenseForm, $accessType, $accessForm) {
        $this->repository->saveAccess($ubrId, $resourceId, $ip, $licenseType, $licenseForm, $accessType, $accessForm);
    }

    public function getDailyStatistics($days, $localOrganizationId = null) {
        return $this->repository->getDailyStatistics($days, $localOrganizationId);
    }

    public function getLicensesCount($resourceId) {
        return $this->repository->getLicensesCount($resourceId);
    }

    public function getSelectableStatistics($fromMonth, $toMonth, $fromYear, $toYear, $organization_id, $licenseTypes, $subjects, $allOrganisations) {
        return $this->repository->getSelectableStatistics($fromMonth, $toMonth, $fromYear, $toYear, $organization_id, $licenseTypes, $subjects, $allOrganisations);
    }

    public function getLabels($ubrId) {
        return $this->repository->getLabels($ubrId);
    }

    public function saveLabels($labels, $ubrId) {
        $this->repository->saveLabels($labels, $ubrId);
    }

    public function deleteLabel($labelId) {
        $this->repository->deleteLabel($labelId);
    }

    public function mergeLabels($label_id_of_label_to_merge_from, $label_id_of_label_to_merge_into){
        $this->repository->mergeLabels($label_id_of_label_to_merge_from, $label_id_of_label_to_merge_into);
    }

    public function getPrivilegesOfOrganisation($ubrId) {
        return $this->repository->getPrivilegesOfOrganisation($ubrId);
    }

    public function updateLicenseLocalisation($licenseLocalisation) {
        $this->repository->updateLicenseLocalisation($licenseLocalisation);
    }

    public function isUrlContainedInDbis($url) {
        return $this->repository->isUrlContainedInDbis($url);
    }

    public function getFreeResourceWithLicenseOnly($resource_id) {
        return $this->repository->getFreeResourceWithLicenseOnly($resource_id);
    }

    public function getResourceDrafts(): array {
        return $this->repository->getResourceDrafts();
    }

    /**
     * @return UpdateFrequency[]
     */
    public function getUpdateFrequencies(): array
    {
        return $this->repository->getUpdateFrequencies();
    }
    /**
     * @return Access[]
     */
    public function getAccessesWithInvalidUrls($ubrId = null, $offset = 0, $limit = 50): array
    {
        return $this->repository->getAccessesWithInvalidUrls($ubrId, $offset, $limit);
    }

    public function setAccessesUrlState($accesId, $newState) {
        $this->repository->setAccessesUrlState($accesId, $newState);
    }

    /**
     * @return Type[]
     */
    public function getTypes(): array
    {
        return $this->repository->getTypes();
    }

    /**
     * @param string $text
     * @return Type|null
     */
    public function getTypeByText($text): ?Type
    {
        return $this->repository->getTypeByText($text);
    }    

    public function getPublicationFormByText($text)
    {
        return $this->repository->getPublicationFormByText($text);
    }

    public function getPublicationForms()
    {
        return $this->repository->getPublicationForms();
    }

    /**
     *
     * @return LicenseType[]
     */
    public function getLicenseTypes(array $options = []): array
    {
        return $this->repository->getLicenseTypes($options);
    }

    /**
     *
     * @return LicenseType[]
     */
    public function getGlobalLicenseTypes(): array
    {
        $freeLicenseTypeIds = [1];
        return $this->repository->getLicenseTypes([
            "onlyGlobal" => true
        ]);
    }

    /**
     *
     * @return LicenseForm[]
     */
    public function getLicenseForms(array $options = []): array
    {
        return $this->repository->getLicenseForms($options);
    }

    /**
     *
     * @return AccessType[]
     */
    public function getAccessTypes(): array
    {
        return $this->repository->getAccessTypes();
    }

     /**
     *
     * @return AccessForm[]
     */
    public function getAccessForms(): array
    {
        return $this->repository->getAccessForms();
    }

    /**
     * @return Author[]
     */
    public function getAuthors(array $options = null): array
    {
        return $this->repository->getAuthors($options);
    }

    public function getAuthorById(int $id): ?Author
    {
        $results = $this->getAuthors([
            "id" => $id
        ]);
        if (count($results) > 0) {
            return $results[0];
        } else {
            return null;
        }
    }


    /**
     * This function returns subjects and collections handled as subjects in
     * a unified format.
     *
     * @param array $options
     * @return ResourceAggregate[]
     */
    public function getResourceAggregatesHandledAsSubject(array $options = []): array
    {
        $subjects = $this->getSubjects($options);

        $collections = array();
        if ($options['include_collections']) {
            $collections = $this->getCollections(
                ["only_subjects" => true,
                "organizationId" => $options['organizationId'] ?? null,
                "without_resources" => $options['without_resources'] ?? null]
            );
        }
        
        return array_merge($subjects, $collections);
    }

    /**
     * @return Subject[]
     */
    public function getSubjects(array $options = []): array
    {
        $options['sort_language'] = array_key_exists('language', $options) ? $options['language'] : "de";

        $options['organizationId'] = array_key_exists('organizationId', $options) ? $options['organizationId'] : null;

        /*
        $include_collections =
            array_key_exists('include_collections', $options) && $options['include_collections'];
        $organization_id = array_key_exists('organizationId', $options) ? $options['organizationId'] : null;
        */

        return $this->repository->getSubjects($options);
    }

    public function setSubjectsVisibility(array $subjectIds, $organisationId) {
        $this->repository->setSubjectsVisibility($subjectIds, $organisationId);
    }

    public function getSubjectById(int $id): ?Subject
    {
        return $this->repository->getSubjectById($id);
    }

    /**
     * @return array<integer, integer>
     */
    public function countResourcesBySubject(): array
    {
        return $this->repository->countResourcesBySubject();
    }

    /**
     * @return array<integer, integer>
     */
    public function countResourcesByCollection(): array
    {
        return $this->repository->countResourcesByCollection();
    }

    /**
     * @return Keyword[]
     */
    public function getKeywords(array $options = null): array
    {
        return $this->repository->getKeywords($options);
    }

    /**
     * @param String $text
     * @return Keyword|null
     */
    public function getKeywordByText(string $text): ?Keyword
    {
        return $this->repository->getKeywordByText($text);
    }

    public function getKeywordById(int $id): ?Keyword
    {
        return $this->repository->getKeywordById($id);
    }

    /**
     * @return Country[]
     */
    public function getCountries(array $options = null): array
    {
        return $this->repository->getCountries($options);
    }

    /**
     * @return Country
     */
    public function getCountryById(int $id): ?Country
    {
        return $this->repository->getCountryById($id);
    }

    public function getCountryByText(string $text)
    {
        return $this->repository->getCountryByText($text);
    }

    public function getSubjectByText(string $text)
    {
        return $this->repository->getSubjectByText($text);
    }

    /**
     * @param array|null $options
     * @return Resource[]
     */
    public function getResources(array $options = null, string $localisation = ResourceRepository::COMBINED): array
    {
        return $this->repository->getResources($options, $localisation);
    }

    /**
     * @param array|null $options
     * @return Resource[]
     */
    public function getTopResources(array $options = [], $localOrganizationId = null): array
    {
        return $this->repository->getTopResources($options, $localOrganizationId);
    }

    public function doesSubjectHasTopresources(array $options = [], $localOrganizationId = null): bool {
        return $this->repository->doesSubjectHasTopresources($options, $localOrganizationId);
    }

    /**
     * @param array|null $options
     * @return Resource[]
     */
    public function getResourcesForSubject(array $options = [], $localOrganizationId = null): array
    {
        return $this->repository->getResourcesForSubject($options, $localOrganizationId);
    }

    public function getTopRessourcesForSubject($subjectId, $organisationId) {
        return $this->repository->getTopRessourcesForSubject($subjectId, $organisationId);
    }

    /**
     * @param $id
     * @param string|null $localOrganizationId
     * @return Resource|null
     */
    public function getResourceById(
        $id,
        string $localOrganizationId = null,
        string $localisation = ResourceRepository::COMBINED
    ): ?Resource {
        return $this->repository->getResourceById($id, $localOrganizationId, $localisation);
    }

    /**
     * @param $id
     * @param string|null $localOrganizationId
     * @return Resource|null
     */
    public function getResourceById_NEW(
        $id,
        string $localOrganizationId = null
    ): ?Resource {
        return $this->repository->getResourceById_NEW($id, $localOrganizationId);
    }

    /**
     * @throws LicenseAlreadyExistingException
     */
    public function addLicenseToResource(Resource $resource, License $license, ?string $localOrganizationId, array $organizations = []): License
    {
        return $this->repository->createLicense($resource, $license, $localOrganizationId, $organizations);
    }

    public function updateLicense(License $license, ?License $oldLicense, ?string $localOrganizationId, array $organizations = []): void
    {
        $this->repository->updateLicense($license, $oldLicense, $localOrganizationId, $organizations);
    }

    public function updateAccesses(License $license, ?string $localOrganizationId): void
    {
        $this->repository->updateAccesses($license, $localOrganizationId);
    }

    public function persistLicenseForOrganization($licenseId, string $forOrganizationWithId) {
        $this->repository->persistLicenseForOrganization($licenseId, $forOrganizationWithId);
    }

    /**
     * @throws LicenseNotFoundException
     */
    public function removeLicenseFromResource(
        License $license,
        string $localOrganizationId = null,
        string $deleteWhat = null
    ): void {
        // $resource->removeLicense($license);

        if ($deleteWhat == "forAll") {
            $this->repository->removeLicense($license, $localOrganizationId);
        } elseif ($deleteWhat == "onlyMyInstitution") {
            if ($license->getType()->getId() === 2) {
                $this->repository->removeLicense($license, $localOrganizationId);
            } else {
                $this->repository->removeLicenseForOrganization($license, $localOrganizationId);
            }
        }
    }

    public function getOrganisationsWithLicense($resourceId) {
        return $this->repository->getOrganisationsWithLicense($resourceId);
    }

    /**
     *
     * @return Host[]
     */
    public function getHosts(): array
    {
        return $this->repository->getHosts();
    }

    public function getHostByName(string $name, string $language = null): ?Host
    {
        return $this->repository->getHostByName($name, $language);
    }

    public function getHostById(int $id): ?Host
    {
        return $this->repository->getHostById($id);
    }

    public function clearTopResourceEntriesForSubject(
        Subject $subject,
        string $localOrganizationId
    ) {
        $this->repository->clearTopResourceEntriesForSubject(
            $subject,
            $localOrganizationId
        );
    }

    public function createCollection(Collection $collection, $localOrganizationId = null): int
    {
        return $this->repository->createCollection($collection, $localOrganizationId);
    }

    /**
     * @param int $id
     * @param string|null $localOrganizationId
     * @return Collection
     * @throws CollectionNotFoundException
     */
    public function getCollectionById(int $id, string $localOrganizationId = null): Collection
    {
        return $this->repository->getCollectionById($id, $localOrganizationId);
    }

    public function getCollectionByText(string $text)
    {
        return $this->repository->getCollectionByText($text);
    }

    /**
     * Query the DB using ubr_id and collid (which maps to collection.notation) for a collection ID.
     * @param string $organizationId
     * @param string $notation
     * @return int|null
     */
    public function getCollectionIdByOrgAndNotation(string $organizationId, string $notation): ?int
    {
        return $this->repository->getCollectionIdByOrgAndNotation($organizationId, $notation);
    }

    /**
     * @param array|null $options
     * @return Collection[]
     */
    public function getCollections(array $options = null): array
    {
        return $this->repository->getCollections($options);
    }

    /**
     * @return SortType[]
     */
    public function getSortTypes(): array
    {
        return $this->repository->getSortTypes();
    }

    public function updateCollection(Collection $collection, string $localOrganizationId): void
    {
        $this->repository->updateCollection($collection, $localOrganizationId);
    }

    public function deleteCollection($collection, string $localOrganizationId)
    {
        $this->repository->deleteCollection($collection, $localOrganizationId);
    }

    public function clearTopResourceEntriesForCollection(?Collection $collection, $orgId)
    {
        $this->repository->clearTopResourceEntriesForCollection(
            $collection,
            $orgId
        );
    }

    public function setTopEntryForSubject($resourceId, $subjectId, $index, $orgId) {
        $this->repository->setTopEntryForSubject($resourceId, $subjectId, $index, $orgId);
    }

    public function setTopEntryForCollection($resourceId, $collectionId, $index, $orgId) {
        $this->repository->setTopEntryForCollection($resourceId, $collectionId, $index, $orgId);
    }

    public function getSecret() {
        return $this->repository->getSecret();
    }

    public function getAccessId($ubrId, $resourceId, $licenseType) {
        return $this->repository->getAccessId($ubrId, $resourceId, $licenseType);
    }

    public function getNewAccessIdForElasticSearch($license, $access) {
        return $this->repository->getNewAccessIdForElasticSearch($license, $access);
    }

    public function generateHash($url) {
        return $this->repository->generateHash($url);
    }

    public function isUrlSafe($accessId) {
        return $this->repository->isUrlSafe($accessId);
    }

    /**
     * @return Enterprise[]
     */
    public function getEnterprises(array $options = null): array
    {
        return $this->repository->getEnterprises($options);
    }

    /**
     * @param int $id
     * @return Enterprise|null
     */
    public function getEnterpriseById(int $id): ?Enterprise
    {
        return $this->repository->getEnterpriseById($id);
    }
    /**
     * @param ?string $dbis_id If empty then global is assumed.
     * @param int $resource_id Mandatory.
     * @return AccessMapping|null
     */
    public function getAccessMapping(?string $dbis_id, int $resource_id): ?AccessMapping
    {
        return $this->repository->getAccessMapping($dbis_id, $resource_id);
    }

    /**
     * @param string $dbis_id
     * @return AccessMapping[]
     */
    public function getAllAccessMappingsForDbisId(string $dbis_id): array
    {
        return $this->repository->getAllAccessMappingsForDbisId($dbis_id);
    }

    /**
     * Implementing a link here to avoid confusion in the future,
     * since vendors and publishers use the same data set: enterprises
     * @return Enterprise[]
     */
    public function getVendors(array $options = null): array
    {
        return $this->getEnterprises($options);
    }

    /**
     * Implementing a link here to avoid confusion in the future,
     * since vendors and publishers use the same data set: enterprises
     * @return Enterprise[]
     */
    public function getPublishers(array $options = null): array
    {
        return $this->getEnterprises($options);
    }

    /*public function getNamespaces(): array
    {
        return Constants::NAMESPACES;
    }*/

    public function localResource(): string
    {
        return ResourceRepository::LOCAL;
    }

    public function globalResource(): string
    {
        return ResourceRepository::GLOBAL;
    }

    public function combinedResource(): string
    {
        return ResourceRepository::COMBINED;
    }

    public function getAdditionalLicenses(Resource $resource, string $organization_id)
    {
        return $this->repository->getAdditionalLicenses($resource, $organization_id);
    }

    public function reuseLicense(int $license_id, $organization_id)
    {
        $this->repository->reuseLicense($license_id, $organization_id);
    }

    public function getUnstandardizedKeywords(array $options, $ubrId = null)
    {
        return $this->repository->getUnstandardizedKeywords($options, $ubrId);
    }

    public function updateKeyword(Keyword $keyword)
    {
        $this->repository->updateKeyword($keyword);
    }

    public function updateRelationships($resource_id, $related_databases, $top_databases, $sub_databases)
    {
        $this->repository->updateRelationships($resource_id, $related_databases, $top_databases, $sub_databases);
    }

    public function getRelationships($resource_id)
    {
        return $this->repository->getRelationships($resource_id);
    }
}
