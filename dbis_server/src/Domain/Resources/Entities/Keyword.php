<?php

namespace App\Domain\Resources\Entities;

use App\Domain\Shared\Internationalizable;
use App\Domain\Shared\Serializable;

/**
 * Keyword entity
 * 
 */
class Keyword implements Serializable, Internationalizable
{
    /** @var int|null */
    private ?int $id  = null;

    /** @var array */
    private array $title;

    /** @var string|null */
    private ?string $external_id  = null;
    /** @var string|null */
    private ?string $keyword_system  = null;


    public function __construct(array $title)
    {
        $this->title = $title;
    }

    /**
     * @return ?int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return array
     */
    public function getTitle(): array
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
     * @return ?string
     */
    public function getExternalId(): ?string
    {
        return $this->external_id;
    }

    /**
     * @param string|null $external_id
     */
    public function setExternalId(?string $external_id): void
    {
        $this->external_id = $external_id;
    }

    /**
     * @return ?string
     */
    public function getKeywordSystem(): ?string
    {
        return $this->keyword_system;
    }

    /**
     * @param string|null $keyword_system
     */
    public function setKeywordSystem(?string $keyword_system): void
    {
        $this->keyword_system = $keyword_system;
    }

    public function toAssocArray(): array
    {
        return [
            "id" => $this->id,
            "title" => $this->title,
            "external_id" => $this->external_id,
            "keyword_system" => $this->keyword_system
        ];
    }

    public function toAssocArrayWithHighlight(string $q): array
    {
        $assoc = $this->toAssocArray();

        // Filter all matching values
        $hits = array_filter($assoc['title'], function($value) use ($q) {
            return stripos($value, $q) !== false; // stripos is case-insensitive
        });
    
        // Create a new object for each match
        $results = [];
        foreach ($hits as $hit) {
            $newAssoc = $assoc;
            $newAssoc['match'] = $hit;    // Duplicate the base object
            $newAssoc['title'] = $assoc['title']; // Assign the matching title
            $results[] = $newAssoc;   // Add to the results array
        }
    
        return $results; // Return all matches as separate objects
    }

    public function toI18nAssocArray(string $language): array
    {
        $assoc = $this->toAssocArray();
        $assoc['title'] = $assoc['title'][$language];
        return $assoc;
    }
}
