<?php

namespace App\Domain\Resources\Entities;

use App\Domain\Shared\Internationalizable;
use App\Domain\Shared\Serializable;

/**
 * Type entity
 *
 */
class Type implements Internationalizable, Serializable
{
    /** @var int */
    private int $id;

    /** @var array|null */
    private ? array $title  = null;
    /** @var array|null */
    private ? array $description  = null;


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
     * @return ?array
     */
    public function getTitle(): ?array
    {
        return $this->title;
    }

    /**
     * @param array $title
     */
    public function setTitle(array $title): void
    {
        $this->title = $title;
    }

    /**
     * @return ?array
     */
    public function getDescription(): ?array
    {
        return $this->description;
    }

    /**
     * @param array $description
     */
    public function setDescription(array $description): void
    {
        $this->description = $description;
    }

    public function toAssocArray(): array
    {
        return [
            "title" => $this->title,
            "description" => $this->description,
            "id" => $this->id
        ];
    }

    public function toI18nAssocArray(string $language): array
    {
        $assoc = $this->toAssocArray();
        if (!is_null($assoc['title'])) {
            $assoc['title'] = $assoc['title'][$language];
        }
        if (!is_null($assoc['description']) && !empty($assoc['description']) ) {
            $assoc['description'] = $assoc['description'][$language];
        }

        return $assoc;
    }

    public static function newIdToOldId(int $newID): int
    {
        return $newID;
        //return Type::$newIdToOldId[$newID] ?? -1;
    }

    /**
     * @var array|int[] Table that can be used to map new resource type ids to old ids.
     * Counterpart to Type::oldIdToNewId.
     */
    private static array $newIdToOldId = array(
        1=>1,
        6=>3,
        13=>4,
        14=>5,
        7=>6,
        4=>7,
        15=>8,
        3=>9,
        16=>10,
        9=>11,
        11=>12,
        // 8 => nothing
        // nothing => 13
        12=>14,
        2=>15,
        5=>16,
        10=>17
    );
}
