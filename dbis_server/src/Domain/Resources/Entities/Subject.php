<?php

namespace App\Domain\Resources\Entities;

/**
 * Subject entity
 *
 */
class Subject implements ResourceAggregate
{
    /** @var int */
    private int $id;

    /** @var array|null */
    private ? array $title  = null;

    /** @var string|null */
    private ? string $subject_system  = null;
    /** @var int|null */
    private ? int $parent  = null;

    private $sort_by = 0;

    private ? array $resourceIds = [];

    private bool $is_visible = true;

    public function __construct(int $id)
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getId() : int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id) : void
    {
        $this->id = $id;
    }

    /**
     * @return array|null
     */
    public function getTitle() : ?array
    {
        return $this->title;
    }

    /**
     * @param ?array $title
     */
    public function setTitle(?array $title) : void
    {
        $this->title = $title;
    }

    /**
     * @return ?string
     */
    public function getSubjectSystem(): ?string
    {
        return $this->subject_system;
    }

    /**
     * @param ?string $subject_system
     */
    public function setSubjectSystem(?string $subject_system): void
    {
        $this->subject_system = $subject_system;
    }

    /**
     * @return ?int
     */
    public function getParent(): ?int
    {
        return $this->parent;
    }

    /**
     * @param ?int $parent
     */
    public function setParent(?int $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * @param ?int $sort_by
     */
    public function setSortBy(?int $sort_by): void
    {
        $this->sort_by = $sort_by;
    }

    /**
     * @return ?int
     */
    public function getSortBy(): ?int
    {
        return $this->sort_by;
    }

    public function setVisibility(bool $is_visible) {
        $this->is_visible = $is_visible;
    }

    public function getVisibility(): bool {
        return $this->is_visible;
    }

    public function toAssocArray(): array
    {
        return [
            "id" => $this->id,
            "title" => $this->title,
            "subject_system" => $this->subject_system,
            "parent" => $this->parent,
            "is_collection" => false,
            "type" => $this->getType(),
            "is_visible" => $this->getVisibility(),
            "resource_ids" => $this->getResourceIds()
        ];
    }

    public function toI18nAssocArray(string $language): array
    {
        $assoc = $this->toAssocArray();
        $assoc['title'] = $this->title ? $this->title[$language] : null;
        return $assoc;
    }

    public function treatedAsSubject(): bool
    {
        return true;
    }

    public function isCollection(): bool
    {
        return false;
    }

    public function getResourceCount(): ?int
    {
        return count($this->resourceIds);
    }

    public function getResourceIds(): ?array
    {
        return $this->resourceIds;
    }

    public function setResourceIds(array $ids): void
    {
        $this->resourceIds = $ids;
    }

    public function getType(): string
    {
        $classParts = explode('\\', get_class());
        return end($classParts);
    }

    /**
     * @var array|int[] Table that can be used to map old subject ids to new subject ids.
     * <p><b>Note that this is not a one to one mapping!</b></p>
     * Counterpart to Subject::newIdToOldId.
     */
    public static array $oldIdToNewId = array(
        1=>32,
        2=>24,
        3=>10,
        4=>10,
        5=>8,
        52=>8,
        6=>14,
        7=>15,
        8=>26,
        9=>21,
        10=>36,
        11=>16,
        12=>5,
        13=>4,
        15=>35,
        16=>43,
        17=>33,
        18=>38,
        19=>41,
        20=>39,
        21=>31,
        22=>34,
        23=>30,
        24=>22,
        25=>28,
        26=>17,
        27=>6,
        28=>3,
        29=>13,
        30=>20,
        44=>40,
        45=>7,
        46=>11,
        47=>12,
        49=>42,
        53=>25,
        54=>9,
        55=>44,
        48=>1,
        50=>2,
        51=>37
    );

    public static function newIdToOldId(int $newID): int
    {
        // automatic mapping of old and new ids does *not* work for all ids, so we cannot do it here (or it crashes)
        return $newID;
        //return Subject::$newIdToOldId[$newID] ?? -1;
    }

    /**
     * @var array|int[] Table that can be used to map new subject ids to old subject ids.
     * <p><b>Note that this is not a one to one mapping!</b></p>
     * Counterpart to Subject::oldIdToNewId.
     */
    private static array $newIdToOldId = array(
        32=>1,
        24=>2,
        10=>3,
        8 =>5,
        14=>6,
        15=>7,
        26=>8,
        21=>9,
        36=>10,
        16=>11,
        5=>12,
        4=>13,
        35=>15,
        43=>16,
        33=>17,
        38=>18,
        41=>19,
        39=>20,
        31=>21,
        34=>22,
        30=>23,
        22=>24,
        28=>25,
        17=>26,
        6=>27,
        3=>28,
        13=>29,
        20=>30,
        40=>44,
        7=>45,
        11=>46,
        12=>47,
        42=>49,
        25=>53,
        9=>54,
        44=>55,
        1=>48,
        2=>50,
        37=>51
    );

}
