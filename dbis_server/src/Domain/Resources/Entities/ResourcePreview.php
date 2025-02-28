<?php

namespace App\Domain\Resources\Entities;

use App\Domain\Resources\Entities\License;
use App\Domain\Resources\Exceptions\LicenseNotFoundException;
use App\Domain\Resources\Exceptions\LicenseAlreadyExistingException;
use App\Domain\Shared\ValidatorUtil;

/**
 * Resource entity
 *
 */
class ResourcePreview
{
    /** @var int */
    private ? int $id = null;

    /** @var array */
    private array $title;

    /** @var null|array */
    private ? array $description_short = null;

    /** @var License[] */
    private array $licenses = [];

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
     * @return ?array
     */
    public function getDescriptionShort(): ?array
    {
        return $this->description_short;
    }

    /**
     * @param array|null $description_short
     */
    public function setDescriptionShort(?array $description_short): void
    {
        $this->description_short = $description_short;
    }

    /**
     * @return License[]
     */
    public function getLicenses(): array
    {
        return $this->licenses;
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

        return count($results) > 0 ? $results[array_keys($results)[0]] : null;
    }

    public function addLicense(License $license): void
    {
        if (!($license->getId() &&
                $this->getLicenseById($license->getId()))
        ) {
            // only add license, if it is not yet in array of licenses
            array_push($this->licenses, $license);
        } else {
            throw new LicenseAlreadyExistingException($license->getId());
        }
    }

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

    public function updateLicense(License $license): void
    {
        $licenseId = $license->getId();
        // only update, if the license really exists for the resource
        if ($licenseId && $this->getLicenseById($licenseId)) {
            $this->removeLicense($license);
            array_push($this->licenses, $license);
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

    public function toAssocArray(): array
    {
        return [
            "id" => $this->id,
            "title" => $this->title,
            "description_short" => $this->description_short,
            "licenses" => array_map(
                function (License $item) {
                    return $item->toAssocArray();
                },
                $this->getLicenses()
            ),
        ];
    }

    public function toI18nAssocArray($language): array
    {
        $result = $this->toAssocArray();
        $result["title"] = $this->title ? $this->title[$language] : null;
        $result["description_short"] = $this->description_short ?
                $this->description_short[$language] : null;
        $result['licenses'] = array_map(
            function (License $item) use ($language) {
                return $item->toI18nAssocArray($language);
            },
            $this->getLicenses()
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

        return $errors;
    }
}
