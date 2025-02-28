<?php

namespace App\Domain\Resources\Entities;

use App\Domain\Resources\Exceptions\LicenseNotFoundException;
use App\Domain\Resources\Exceptions\LicenseAlreadyExistingException;
use App\Domain\Shared\ValidatorUtil;


/**
 * Resource entity
 *
 */
class Resource
{
    /** @var null|int */
    private ?int $id = null;

    /** @var null|string */
    private ?string $title;

    /** @var Type[] */
    private array $types = [];

    /** @var null|array */
    private ?array $description_short = null;

    /** @var null|array */
    private ?array $description = null;

    /** @var null|string */
    private ?string $report_time_start = null;

    /** @var null|string */
    private ?string $report_time_end = null;

    /** @var null|string */
    private ?string $publication_time_start = null;

    /** @var null|string */
    private ?string $publication_time_end = null;

    /** @var null|int */
    private ?int $is_still_updated = null;

    /** @var null|UpdateFrequency */
    private ?UpdateFrequency $update_frequency = null;

    /** @var License[] */
    private array $licenses = [];

    /** @var Subject[] */
    private ?array $subjects = [];

    /** @var Collection[] */
    private ?array $collections = [];

    /** @var Keyword[] */
    private ?array $keywords = [];

    /** @var Author[] */
    private ?array $authors = [];

    /** @var TopResourceEntry[] */
    private array $topResourceEntries = [];

    /** @var AlternativeTitle[] */
    private array $alternativeTitles = [];

    /** @var Country[] */
    private array $countries = [];

    private ?Resource $overwrite = null;

    /** @var null|string */
    private ?string $shelfmark = null;

    /** @var null|array */
    private ?array $note = null;

    /** @var null|string */
    private ?string $isbn_issn = null;

    /** @var null|array */
    private ?array $local_note = null;

    /** @var null|array */
    private ?array $instructions = null;

    /** @var null|boolean */
    private ?bool $is_visible = null;

    /** @var null|boolean */
    private ?bool $is_free = null;

    /** @var ExternalID[] */
    private ?array $external_resource_ids = [];

    /** @var Url[] */
    private ?array $apiUrls = [];

    /** @var null|string */
    private ?string $created_by = null;

    /** @var null|string */
    private ?string $created_at = null;

    /** @var null|string */
    private ?string $modified_at = null;

    public function __construct(?string $title = null, array $types = [])
    {
        $this->title = $title;
        $this->types = $types;
    }

    /**
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int|null $id
     */
    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function isTitleSet() {
        return $this->title && ValidatorUtil::isStringNotEmpty($this->title, false);
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return ?array
     */
    public function getDescriptionShort(): ?array
    {
        return $this->description_short;
    }

    public function isDescriptionShortSet() {
        return $this->description_short && ValidatorUtil::isStringNotEmpty($this->description_short['de'], false) && ValidatorUtil::isStringNotEmpty($this->description_short['en'], false);
    }

    /**
     * @param array|null $description_short
     */
    public function setDescriptionShort(?array $description_short): void
    {
        if ($description_short) {
            if (array_key_exists('de', $description_short)) {
                $description_short['de'] = html_entity_decode($description_short['de']);
            }
            if (array_key_exists('en', $description_short)) {
                $description_short['en'] = html_entity_decode($description_short['en']);
            }
        }
        $this->description_short = $description_short;
    }

    /**
     * @return ?array
     */
    public function getDescription(): ?array
    {
        return $this->description;
    }

    public function isDescriptionSet() {
        return $this->description && ValidatorUtil::isStringNotEmpty($this->description['de'], false) && ValidatorUtil::isStringNotEmpty($this->description['en'], false);
    }

    /**
     * @param array|null $description
     */
    public function setDescription(?array $description): void
    {
        if ($description) {
            if (array_key_exists('de', $description)) {
                $description['de'] = html_entity_decode($description['de']);
            }
            if (array_key_exists('en', $description)) {
                $description['en'] = html_entity_decode($description['en']);
            }
        }
        $this->description = $description;
    }

    /**
     * @return ?string
     */
    public function getReportTimeStart(): ?string
    {
        return $this->report_time_start;
    }

    /**
     * @param string|null $report_time_start
     */
    public function setReportTimeStart(?string $report_time_start): void
    {
        $this->report_time_start = is_string($report_time_start) ? $report_time_start : null;
    }

    /**
     * @return ?string
     */
    public function getReportTimeEnd(): ?string
    {
        return $this->report_time_end;
    }

    /**
     * @param string|null $report_time_end
     */
    public function setReportTimeEnd(?string $report_time_end): void
    {
        $this->report_time_end = is_string($report_time_end) ? $report_time_end : null;
    }

    public function isReportTimeSet(): ?string
    {
        return $this->report_time_start && strlen($this->report_time_start) > 0 || $this->report_time_end && strlen($this->report_time_end) > 0 ;
    }

    /**
     * @return ?string
     */
    public function getPublicationTimeStart(): ?string
    {
        return $this->publication_time_start;
    }

    public function isPublicationTimeSet(): ?string
    {
        return $this->publication_time_start && strlen($this->publication_time_start) > 0 || $this->publication_time_end && strlen($this->publication_time_end) > 0 ;
    }


    /**
     * @param string|null $publication_time_start
     */
    public function setPublicationTimeStart(?string $publication_time_start): void
    {
        $this->publication_time_start = is_string($publication_time_start) ? $publication_time_start : null;
    }

    /**
     * @return ?string
     */
    public function getPublicationTimeEnd(): ?string
    {
        return $this->publication_time_end;
    }

    /**
     * @param string|null $publication_time_end
     */
    public function setPublicationTimeEnd(?string $publication_time_end): void
    {
        $this->publication_time_end = is_string($publication_time_end) ? $publication_time_end : null;
    }

    /**
     * @return int|null
     */
    public function isIsStillUpdated(): ?int
    {
        return $this->is_still_updated;
    }

    public function isUpdateSet()
    {
        return $this->is_still_updated ? true: false;
    }

    /**
     * @param int|null $is_still_updated
     */
    public function setIsStillUpdated(?int $is_still_updated): void
    {
        $this->is_still_updated = $is_still_updated;
    }

     /**
     * @param bool|null $is_free
     */
    public function setIsFree(?bool $is_free): void
    {
        $this->is_free = $is_free;
    }

    /**
     * @return ?bool
     */
    public function isFree(): ?bool
    {
        return $this->is_free;
    }

    /**
     * @return ?UpdateFrequency
     */
    public function getUpdateFrequency(): ?UpdateFrequency
    {
        return $this->update_frequency;
    }

    /**
     * @param UpdateFrequency|null $update_frequency
     */
    public function setUpdateFrequency(?UpdateFrequency $update_frequency): void
    {
        $this->update_frequency = $update_frequency;
    }

    /**
     * @return Type[]
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * @param Type[] $types
     * @return void
     */
    public function setTypes(array $types): void
    {
        $this->types = $types;
    }

    /**
     *
     * @param Subject[] $subjects
     * @return void
     */
    public function setSubjects(array $subjects): void
    {
        $this->subjects = $subjects;
    }

    /**
     *
     * @return Subject[]
     */
    public function getSubjects(): array
    {
        return $this->subjects;
    }

    /**
     *
     * @param Collection[] $collections
     * @return void
     */
    public function setCollections(array $collections): void
    {
        $this->collections = $collections;
    }

    /**
     *Collection
     * @return Collection[]
     */
    public function getCollections(): array
    {
        return $this->collections;
    }

    public function setKeywords(array $keywords): void
    {
        $this->keywords = $keywords;
    }

    /**
     *
     * @return Keyword[]
     */
    public function getKeywords(): array
    {
        return $this->keywords;
    }

    /**
     *
     * @param Author[] $authors
     * @return void
     */
    public function setAuthors(array $authors): void
    {
        $this->authors = $authors;
    }

    /**
     *
     * @return Author[]
     */
    public function getAuthors(): array
    {
        return $this->authors;
    }

    /**
     * @return License[]
     */
    public function getLicenses(): array
    {
        return $this->licenses;
    }

    /**
     * @return ?array
     */
    public function getInstructions(): ?array
    {
        return $this->instructions;
    }

    public function isInstructionSet() {
        return $this->instructions && ValidatorUtil::isStringNotEmpty($this->instructions['de'], false) && ValidatorUtil::isStringNotEmpty($this->instructions['en'], false);
    }

    /**
     * @param array|null $instructions
     */
    public function setInstructions(?array $instructions): void
    {
        $this->instructions = $instructions;
    }

    /**
     * @return bool|null
     */
    public function isVisible(): ?bool
    {
        return $this->is_visible;
    }

    /**
     * @param bool|null $is_visible
     */
    public function setIsVisible(?bool $is_visible): void
    {
        $this->is_visible = $is_visible;
    }

    public function getLicenseById(int $id): ?License
    {
        $results = array_filter(
            $this->licenses,
            function (License $license, int $i) use ($id) {
                return ($license->getId() == $id);
            },
            ARRAY_FILTER_USE_BOTH
        );

        $license = null;

        if (count($results) > 0) {
            $license  = $results[array_keys($results)[0]];
        } 

        return $license;
    }

    /**
     * @throws LicenseAlreadyExistingException
     */
    public function addLicense(License $license): void
    {
        if (
            !($license->getId() &&
                $this->getLicenseById($license->getId()))
        ) {
            // only add license, if it is not yet in array of licenses
            array_push($this->licenses, $license);
        } else {
            throw new LicenseAlreadyExistingException($license->getId());
        }
    }

    /**
     * @throws LicenseNotFoundException
     */
    public function removeLicense(License $license): void
    {
        $id = $license->getId();
        if ($id && $this->getLicenseById($id) != null) {
            $this->licenses = array_filter(
                $this->licenses,
                function (License $l, $key) use ($license) {
                    return $l->getId() != $license->getId();
                },
                ARRAY_FILTER_USE_BOTH
            );
        } else {
            throw new LicenseNotFoundException($license->getId());
        }
    }

    /**
     * @throws LicenseNotFoundException
     */
    public function updateLicense(License $license): void
    {
        $licenseId = $license->getId();
        // only update, if the license really exists for the resource
        if ($licenseId && $this->getLicenseById($licenseId)) {
            $this->removeLicense($license);
            $this->licenses[] = $license;
        } else {
            throw new LicenseNotFoundException($licenseId);
        }
    }

    /**
     * @param License[] $licenses
     * @return void
     */
    public function setLicenses(array $licenses): void
    {
        $this->licenses = $licenses;
    }

    /**
     *
     * @param TopResourceEntry[] $entries
     * @return void
     */
    public function setTopResourceEntries(array $entries): void
    {
        foreach ($entries as $entry) {
            $entry->setResourceId($this->id);
        }
        $this->topResourceEntries = $entries;
    }

    public function setTopEntryFor(Subject $subject, int $order, string $localOrganizationId = null): void
    {
        $this->removeTopEntryFor($subject, $localOrganizationId);
        $entry = new TopResourceEntry(
            $localOrganizationId,
            $this->id,
            $subject
        );
        $entry->setOrder($order);
        array_push($this->topResourceEntries, $entry);
    }

    public function removeTopEntryFor(Subject $subject, string $localOrganizationId = null): void
    {
        $this->topResourceEntries = array_filter(
            $this->getTopResourceEntries(),
            function ($entry) use ($subject, $localOrganizationId) {
                return !($subject->getId() == $entry->getSubject()->getId() &&
                $entry->getOrganizationId() == $localOrganizationId);
            }
        );
    }

    /**
     * @param Collection $collection
     * @param string|null $localOrganizationId
     * @return void
     */
    public function removeTopEntryForCollection(Collection $collection, string $localOrganizationId = null): void
    {
        $this->topResourceEntries = array_filter(
            $this->getTopResourceEntries(),
            function ($entry) use ($collection, $localOrganizationId) {
                return !($entry->isCollection() && $collection->getId() == $entry->getSubject()->getId() &&
                    $entry->getOrganizationId() == $localOrganizationId);
            }
        );
    }

    /**
     * @param Collection $collection
     * @param int $order
     * @param string|null $localOrganizationId
     * @return void
     */
    public function setTopEntryForCollection(
        Collection $collection,
        int $order,
        string $localOrganizationId = null
    ): void {
        $this->removeTopEntryForCollection($collection, $localOrganizationId);
        $entry = new TopResourceEntry(
            $localOrganizationId,
            $this->id,
            $collection
        );
        $entry->setOrder($order);
        $this->topResourceEntries[] = $entry;
    }

    /**
     * @param ResourceAggregate|null $subject
     * @return bool
     */
    public function isTopEntryFor(?ResourceAggregate $subject): bool
    {
        if (is_null($subject)) {
            return false;
        } else {// 
            return count(array_filter(
                $this->getTopResourceEntries(),
                function (TopResourceEntry $e) use ($subject) {
                    if (
                        $e->getSubject()->isCollection() && $subject->isCollection() ||
                        !$e->getSubject()->isCollection() && !$subject->isCollection()
                    ) {
                        return $e->getSubject()->getId() == $subject->getId();
                    } else {
                        return false;
                    }
                }
            )) > 0;
        }
    }

    /**
     * @param ResourceAggregate $subject
     * @return TopResourceEntry|null
     * Filters the top resources according to the passed subject.
     */
    public function getTopResourceEntryForSubject(ResourceAggregate $subject): ?TopResourceEntry
    {
        $candidates = array_filter(
            $this->getTopResourceEntries(),
            function (TopResourceEntry $e) use ($subject) {
                if (
                    $e->getSubject()->isCollection() && $subject->isCollection() ||
                    !$e->getSubject()->isCollection() && !$subject->isCollection()
                ) {
                    return $e->getSubject()->getId() == $subject->getId();
                } else {
                    return false;
                }
            }
        );
        return current($candidates) ? : null;
    }

    /**
     * @return TopResourceEntry[]
     */
    public function getTopResourceEntries(): array
    {
        return $this->topResourceEntries;
    }

    /**
     *
     * @param array Url[]
     */
    public function setApiUrls(array $api_urls_obj): void
    {
        $this->apiUrls = $api_urls_obj;
    }

    /**
     *
     * @return Url[]
     */
    public function getApiUrls(): array
    {
        return $this->apiUrls;
    }

    /**
     *
     * @param array AlternativeTitle[]
     */
    public function setAlternativeTitles(array $alternativeTitles): void
    {
        $this->alternativeTitles = $alternativeTitles;
    }

    /**
     *
     * @return AlternativeTitle[]
     */
    public function getAlternativeTitles(): array
    {
        return $this->alternativeTitles;
    }

    /**
     *
     * @param array Country[]
     */
    public function setCountries(array $countries): void
    {
        $this->countries = $countries;
    }

    /**
     *
     * @return Country[]
     */
    public function getCountries(): array
    {
        return $this->countries;
    }

    public function setOverwrite(Resource $r): void
    {
        $this->overwrite = $r;
    }

    public function getOverwrite(): ?Resource
    {
        return $this->overwrite;
    }

    /**
     * @return ?string
     */
    public function getShelfmark(): ?string
    {
        return $this->shelfmark;
    }

    /**
     * @return ?string
     */
    public function getShelfmarkGroup(): ?string
    {
        if (is_null($this->shelfmark)) {
            return "";
        }

        $sheflmark_arr = explode(" ", $this->shelfmark);
        if (sizeof($sheflmark_arr) > 0) {
            return $sheflmark_arr[0];
        } else {
            return "";
        }
    }

    /**
     * @return ?string
     */
    public function getShelfmarkNumbers(): ?string
    {
        if (is_null($this->shelfmark)) {
            return "";
        }

        $sheflmark_arr = explode(" ", $this->shelfmark);
        if (sizeof($sheflmark_arr) > 0) {
            return $sheflmark_arr[1];
        } else {
            return "";
        }
    }

    public function getShelfmarkDescription(): string {
        if (!is_null($this->shelfmark) and strlen($this->shelfmark) > 0) {
            if(preg_match("/[a-zA-Z]+\s\d+/i", $this->shelfmark)) {
                $url = sprintf('https://rvk.uni-regensburg.de/api_neu/json/node/%s', urlencode($this->shelfmark));

                $ch = curl_init($url);

                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response_json = curl_exec($ch);

                curl_close($ch);
                $response = json_decode($response_json, true);

                if (is_null($response) or array_key_exists('error-code', $response)) {
                    return "";
                } else {
                    return $response['node']['benennung'];
                }
            } else {
                return "";
            }
        } else {
            return "";
        }
    }

    public function isShelfmarkSet() {
        return $this->shelfmark && ValidatorUtil::isStringNotEmpty($this->shelfmark, false);
    }

    /**
     * @param string|null $shelfmark
     */
    public function setShelfmark(?string $shelfmark): void
    {
        $this->shelfmark = is_string($shelfmark) ? $shelfmark : null;
    }

    /**
     * @return ?array
     */
    public function getNote(): ?array
    {
        return $this->note;
    }

    public function isNoteSet() {
        return $this->note && ValidatorUtil::isStringNotEmpty($this->note['de'], false) && ValidatorUtil::isStringNotEmpty($this->note['en'], false);
    }

    /**
     * @param ?array $note
     */
    public function setNote(?array $note): void
    {
        $this->note = $note;
    }

    /**
     * @return ?array
     */
    public function getLocalNote(): ?array
    {
        return $this->local_note;
    }

    /**
     * @param ?array $local_note
     */
    public function setLocalNote(?array $local_note): void
    {
        $this->local_note = $local_note;
    }

    /**
     * @return ?string
     */
    public function getIsbnIssn(): ?string
    {
        return $this->isbn_issn;
    }

    public function isIsbnIssnSet() {
        return $this->isbn_issn && ValidatorUtil::isStringNotEmpty($this->isbn_issn, false);
    }

    /**
     * @param string|null $isbn_issn
     */
    public function setIsbnIssn(?string $isbn_issn): void
    {
        $this->isbn_issn = is_string($isbn_issn) ? $isbn_issn : null;
    }

    /**
     * @param string|null $created_by
     */
    public function setCreatedBy(?string $created_by): void
    {
        $this->created_by = $created_by;
    }

        /**
     * @return ?string
     */
    public function getCreatedBy(): ?string
    {
        return $this->created_by;
    }

    /**
     * @return ?string
     */
    public function getCreatedAt(): ?string
    {
        return $this->created_at;
    }

    /**
     * @param string|null $created_at
     */
    public function setCreatedAt(?string $created_at): void
    {
        $this->created_at = $created_at;
    }

     /**
     * @param string|null $modified_at
     */
    public function setModifiedAt(?string $modified_at): void
    {
        $this->modified_at = $modified_at;
    }

    /**
     * @return ?string
     */
    public function getModifiedAt(): ?string
    {
        return $this->modified_at;
    }

    /**
     * @param ExternalID[] $external_ids
     * @return void
     */
    public function setExternalIDs(array $external_ids): void
    {
        $this->external_resource_ids = $external_ids;
    }

    public function getExternalIDs(): ?array
    {
        return $this->external_resource_ids;
    }

    public function toAssocArray(): array
    {
        return [
            "id" => $this->id,
            "title" => $this->title,
            "is_free" => $this->is_free,
            "description_short" => $this->description_short,
            "description" => $this->description,
            "report_time_start" => $this->report_time_start,
            "report_time_end" => $this->report_time_end,
            "publication_time_start" => $this->publication_time_start,
            "publication_time_end" => $this->publication_time_end,
            "is_still_updated" => $this->is_still_updated,
            "update_frequency" => $this->update_frequency ? $this->update_frequency->toAssocArray() : null,
            "overwrite" => $this->overwrite ? $this->overwrite->toAssocArray() : null,
            "shelfmark" => $this->shelfmark,
            "shelfmark_group" => $this->getShelfmarkGroup(),
            "shelfmark_numbers" => $this->getShelfmarkNumbers(),
            "shelfmark_description" => $this->getShelfmarkDescription(),
            "note" => $this->note,
            "isbn_issn" => $this->isbn_issn,
            "local_note" => $this->local_note,
            "instructions" => $this->instructions,
            "is_visible" => $this->is_visible,
            "created_by" => $this->created_by,
            "created_at" => $this->created_at,
            "modified_at" => $this->modified_at,
            "authors" => array_map(function ($author) {
                return $author->toAssocArray();
            }, $this->getAuthors()),
            "types" => array_map(function ($type) {
                return $type->toAssocArray();
            }, $this->getTypes()),
            "licenses" => array_map(
                function (License $item) {
                    return $item->toAssocArray();
                },
                $this->getLicenses()
            ),
            "subjects" => array_map(
                function (Subject $item) {
                    return $item->toAssocArray();
                },
                $this->getSubjects()
            ),
            "collections" => array_map(
                function (Collection $item) {
                    return $item->toAssocArray();
                },
                $this->getCollections()
            ),
            "keywords" => array_map(
                function (Keyword $kw) {
                    return $kw->toAssocArray();
                },
                $this->getKeywords()
            ),
            "top_resource_entries" => array_map(
                function (TopResourceEntry $e) {
                    return $e->toAssocArray();
                },
                $this->getTopResourceEntries()
            ),
            "alternative_titles" => array_map(
                function (AlternativeTitle $e) {
                    return $e->toAssocArray();
                },
                $this->getAlternativeTitles()
            ),
            "countries" => array_map(
                function (Country $e) {
                    return $e->toAssocArray();
                },
                $this->getCountries()
            ),
            "external_ids" => array_map(
                function (ExternalID $e) {
                    return $e->toAssocArray();
                },
                $this->getExternalIDs()
            ),
            "api_urls" => array_map(
                function (Url $e) {
                    return $e->toAssocArray();
                },
                $this->getApiUrls()
            )
        ];
    }

    public function toI18nAssocArray($language): array
    {
        $result = $this->toAssocArray();
        $result["title"] = $this->title ?: null;
        $result["description_short"] = $this->description_short ?
                $this->description_short[$language] : null;
        $result["description"] = $this->description ?
                $this->description[$language] : null;
        // TODO: translate UpdateFrequency
        $result["update_frequency"] = $this->update_frequency;
        $result["overwrite"] = $this->overwrite ? $this->overwrite->toI18nAssocArray($language) : null;
        $result["note"] = $this->note ? $this->note[$language] : null;
        $result["local_note"] = $this->local_note ? $this->local_note[$language] : null;
        $result["instructions"] = $this->instructions ? $this->instructions[$language] : null;
        $result["authors"] = array_map(function ($author) use ($language) {
            return $author->toAssocArray();
        }, $this->getAuthors());
        $result["types"] = array_map(function ($type) use ($language) {
            return $type->toI18nAssocArray($language);
        }, $this->getTypes());
        $result['licenses'] = array_map(
            function (License $item) use ($language) {
                return $item->toI18nAssocArray($language);
            },
            $this->getLicenses()
        );
        $result['alternative_titles'] = array_map(
            function (AlternativeTitle $item) use ($language) {
                return $item->toAssocArray();
            },
            $this->getAlternativeTitles()
        );
        $result['api_urls'] = array_map(
            function (Url $item) use ($language) {
                return $item->toAssocArray();
            },
            $this->getApiUrls()
        );
        $result['subjects'] = array_map(
            function (Subject $item) use ($language) {
                return $item->toI18nAssocArray($language);
            },
            $this->getSubjects()
        );
        $result['collections'] = array_map(
            function (Collection $item) use ($language) {
                return $item->toI18nAssocArray($language);
            },
            $this->getCollections()
        );
        $result['keywords'] = array_map(
            function (Keyword $item) use ($language) {
                return $item->toI18nAssocArray($language);
            },
            $this->getKeywords()
        );
        $result['countries'] = array_map(
            function (Country $item) use ($language) {
                return $item->toI18nAssocArray($language);
            },
            $this->getCountries()
        );
        return $result;
    }

    /**
     * @return array
     * @throws \App\Domain\Shared\Exceptions\StringIsEmptyException
     */
    public function validate(): array
    {
        $errors = array();

        if (!ValidatorUtil::isStringNotEmpty($this->title, false)) {
            $errors['title'] = true;
        }

        if (array_key_exists('de', $this->description)) {
            if (!ValidatorUtil::isStringNotEmpty($this->description['de'], false)) {
                $errors['description_de'] = true;
            }
        } else {
            $errors['description_de'] = true;
        }

        if (array_key_exists('en', $this->description)) {
            if (!ValidatorUtil::isStringNotEmpty($this->description['en'], false)) {
                $errors['description_en'] = true;
            }
        } else {
            $errors['description_en'] = true;
        }

        if (count($this->subjects) == 0) {
            $errors['subjects'] = true;
        }

        if (count($this->types) == 0) {
            $errors['type'] = true;
        }

        if (is_null($this->is_free)) {
            $errors['pricetype'] = true;
        }

        if (!is_null($this->shelfmark) and strlen($this->shelfmark) > 0) {
            if(preg_match("/[a-zA-Z]+\s\d+/i", $this->shelfmark)) {
                $url = sprintf('https://rvk.uni-regensburg.de/api_neu/json/node/%s', urlencode($this->shelfmark));

                $ch = curl_init($url);

                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response_json = curl_exec($ch);

                curl_close($ch);
                $response = json_decode($response_json, true);

                if (is_null($response) or array_key_exists('error-code', $response)) {
                    $errors['shelfmark'] = true;
                }
            } else {
                $errors['shelfmark'] = true;
            }
        }

        return $errors;
    }

    public function truncateDescription($lang): string
    {
        if (strlen($this->description[$lang]) > 0) {
            $string = trim($this->description[$lang]);

            if (strlen($string) > 250) {
                $string = wordwrap($string, 250);
                $string = explode("\n", $string, 2);
                $string = $string[0] . "&hellip;";
            }

            return $string;
        } else {
            return "";
        }
    }
}
