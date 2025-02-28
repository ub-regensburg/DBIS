<?php

namespace App\Domain\Resources\Entities;

use App\Domain\Shared\ValidatorUtil;

/**
 * Collection entity
 *
 */
class Collection implements ResourceAggregate
{
    /** @var int */
    private ? int $id = null;
    /** @var null | string */
    private ? string $notation = null;
    /** @var array */
    private array $title;
    /** @var ?SortType */
    private ? SortType $sort_by = null;
    /** @var SortType[] */
    private array $sort_types = [];
    /** @var null|boolean */
    private ? bool $is_subject = false;
    /** @var null|boolean */
    private ? bool $is_visible = false;
    /** @var Resource[] */
    private array $resources = [];
    private array $resourceIds = [];

    public function __construct(array $title)
    {
        $this->title = $title;
    }

    /**
     * @return ?int
     */
    public function getId() : ?int
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
     * @return ?string
     */
    public function getNotation() : ?string
    {
        return $this->notation;
    }

    /**
     * @param string $notation
     */
    public function setNotation(string $notation) : void
    {
        $this->notation = $notation;
    }

    /**
     * @return array
     */
    public function getTitle() : array
    {
        return $this->title;
    }

    /**
     * @param array $title
     */
    public function setTitle(array $title) : void
    {
        $this->title = $title;
    }

    /**
     * @return ?bool
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

    /**
     * @return ?bool
     */
    public function isSubject(): ?bool
    {
        return $this->is_subject;
    }

    /**
     * @param bool|null $is_subject
     */
    public function setIsSubject(?bool $is_subject): void
    {
        $this->is_subject = $is_subject;
    }

    /**
     * @return ?SortType
     */
    public function getSortBy(): ?SortType
    {
        return $this->sort_by;
    }

    /**
     * @param SortType|null $sort_by
     */
    public function setSortBy(?SortType $sort_by): void
    {
        $this->sort_by = $sort_by;
    }

    /**
     * @return SortType[]
     */
    public function getSortTypes(): array
    {
        return $this->sort_types;
    }

    /**
     * @param SortType[] $sort_types
     * @return void
     */
    public function setSortTypes(array $sort_types): void
    {
        $this->sort_types = $sort_types;
    }

    public function toAssocArray(): array
    {
        return [
            "id" => $this->id,
            "title" => $this->title,
            "sort_by" => $this->sort_by ? $this->sort_by->toAssocArray() : null,
            "is_visible" => $this->is_visible,
            "is_subject" => $this->is_subject,
            "is_collection" => true,
            "resource_ids" => $this->getResourceIds(),
            "type" => $this->getType()
        ];
    }

    public function toI18nAssocArray(string $language): array
    {
        $assoc = $this->toAssocArray();
        $assoc['title'] = $assoc['title'][$language];
        $assoc['sort_by'] = $this->sort_by->toI18nAssocArray($language);
        return $assoc;
    }

    /**
     * @return array
     * @throws \App\Domain\Shared\Exceptions\StringIsEmptyException
     */
    public function validate(): array
    {
        $errors = array();

        if (array_key_exists('de', $this->title)) {
            if (!ValidatorUtil::isStringNotEmpty($this->title['de'], false)) {
                $errors['title_de'] = true;
            }
        } else {
            $errors['title_de'] = true;
        }

        if (array_key_exists('en', $this->title)) {
            if (!ValidatorUtil::isStringNotEmpty($this->title['en'], false)) {
                $errors['title_en'] = true;
            }
        } else {
            $errors['title_en'] = true;
        }

        if (count($this->resourceIds) == 0) {
            $errors['resources'] = true;
        }

        return $errors;
    }

    public function treatedAsSubject(): bool
    {
        return $this->is_subject;
    }

    public function isCollection(): bool
    {
        return true;
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
}
