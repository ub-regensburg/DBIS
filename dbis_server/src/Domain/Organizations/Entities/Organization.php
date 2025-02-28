<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Entities;

use App\Domain\Organizations\Entities\ExternalOrganizationIdentifier;
use App\Domain\Shared\ValidatorUtil;
use App\Domain\Organizations\Entities\DbisView;
use App\Domain\Organizations\Entities\DbisSettings;
use DateTime;

/**
 * Organization
 *
 * Entity for DBIS client organizations
 * 
 */
class Organization
{
    /** @var string */
    private $ubrId;

    /** @var string */
    private $dbisId;

    /** @var array */
    private $name;

    /** @var string */
    private $countryCode;

    /** @var array */
    private $region;

    /** @var ?string */
    private $zipcode;

    /** @var array */
    private $city;

    /** @var array */
    private $adress;

    /** @var string */
    private $contact;

    /** @var array */
    private $homepage;

    /** @var string */
    private $iconPath;

    /** @var string */
    private $publicIconFolder = null;

    /** @var int */
    private $id;

    /** @var ExternalOrganizationIdentifier[] */
    private $externalIds = [];

    /** @var DateTime */
    private $createdAtDate;

    /** @var string */
    private $ipRanges;

    /**
     *
     * @var DbisView
     */
    private $view = null;

    /**
     *
     * @var DbisSettings
     */
    private $settings;

    /** @var array */
    private $links = [];

    /** @var string */
    private string $color = "#fff";

    private bool $isFID = false;

    private bool $isConsortium = false;

    private bool $isKfL = false;

    public function __construct(
        string $ubrId,
        array $name,
        string $countryCode
    ) {
        ValidatorUtil::hasNoSpecialChars($ubrId);
        $this->ubrId = $ubrId;
        $this->name = $name;
        $this->countryCode = $countryCode;
    }

    public function getIconPath(): ?string
    {
        return $this->iconPath;
    }

    public function getPublicIconPath(): ?string
    {
        if (!$this->iconPath) {
            return null;
        }

        $pathParts = explode('/', $this->iconPath);
        $filename = end($pathParts);

        $IS_PRODUCTIVE = filter_var(getenv('PRODUCTIVE'), FILTER_VALIDATE_BOOLEAN);

        if ($IS_PRODUCTIVE) {
            return '/public/icons/' . $filename;
        } else {
            return '/resources/icons/' . $filename;
        }
    }


    public function setPublicIconFolder($path) {
        $this->publicIconFolder = $path;
    }

    public function getPublicIconFolder(): ?string
    {
        return $this->publicIconFolder;
    }

    public function setIconPath(string $p): void
    {
        ValidatorUtil::isFileOfType($p, ['jpg', 'png', 'svg']);
        $this->iconPath = $p;
    }

    public function setCity(array $city)
    {
        $this->city = $city;
    }

    public function setRegion(array $region)
    {
        $this->region = $region;
    }

    public function setZipcode(?string $zipcode)
    {
        $this->zipcode = $zipcode;
    }

    public function getUbrId(): string
    {
        return $this->ubrId;
    }

    public function getDbisId(): ?string
    {
        return $this->dbisId;
    }

    public function setDbisId(string $dbisId): void
    {
        $this->dbisId = $dbisId;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(string $color): void
    {
        $this->color = $color;
    }

    public function getName(): array
    {
        return $this->name;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function getRegion(): ?array
    {
        return $this->region;
    }

    public function getZipcode(): ?string
    {
        return $this->zipcode;
    }

    public function getCity(): array
    {
        return $this->city;
    }

    public function getContact(): ?string
    {
        return $this->contact;
    }

    public function setContact(string $contact): void
    {
        $this->contact = $contact;
    }

    public function getHomepage(): ?array
    {
        return $this->homepage;
    }

    public function setHomepage(array $homepage): void
    {
        $this->homepage = $homepage;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setAdress(array $address): void
    {
        $this->adress = $address;
    }

    public function getAdress(): ?array
    {
        return $this->adress;
    }

    public function getDbisView(): ?DbisView
    {
        return $this->view;
    }

    public function setDbisView(?DbisView $view): void
    {
        $this->view = $view;
    }

    public function getDbisSettings(): ?DbisSettings
    {
        return $this->settings;
    }

    public function setDbisSettings(?DbisSettings $settings): void
    {
        $this->settings = $settings;
    }

    public function setIpRanges(string $ipRanges)
    {
        $this->ipRanges = $ipRanges;
    }

    public function getIpRanges(): ?string
    {
        return $this->ipRanges;
    }

    public function setLinks(array $links): void
    {
        $this->links = $links;
    }

    public function getLinks(): ?array
    {
        $links = array_slice($this->links, 0, 3);
        $filteredLinks = array_filter($links, function ($link) {
            return !$link->isEmpty();
        });

        return $filteredLinks;
    }

    /**
     *
     * @param ExternalOrganizationIdentifier[] $externalIds
     * @return void
     */
    public function setExternalIds(array $externalIds): void
    {
        $this->externalIds = $externalIds;
    }

    /**
     *
     * @return ExternalOrganizationIdentifier[]
     */
    public function getExternalIds(): array
    {
        return $this->externalIds;
    }

    public function setCreatedAtDate(DateTime $date): void
    {
        $this->createdAtDate = $date;
    }

    public function getCreatedAtDate(): DateTime
    {
        return $this->createdAtDate;
    }

    public function setIsFID(bool $isFID): void
    {
        $this->isFID = $isFID;
    }

    public function getIsFID(): bool
    {
        return $this->isFID;
    }

    public function setIsConsortium(bool $isConsortium): void
    {
        $this->isConsortium = $isConsortium;
    }

    public function getIsConsortium(): bool
    {
        return $this->isConsortium;
    }

    public function setIsKfL(bool $isKfL): void
    {
        $this->isKfL = $isKfL;
    }

    public function getIsKfL(): bool
    {
        return $this->isKfL;
    }

    /*
     *
     *
     */

    public function toAssocArray(): array
    {
        return [
            "ubrId" => $this->ubrId,
            "name" => $this->name,
            "countryCode" => $this->countryCode,
            "region" => $this->region,
            "zipcode" => $this->zipcode,
            "city" => $this->city,
            "contact" => $this->contact,
            "id" => $this->id,
            "adress" => $this->adress,
            "homepage" => $this->homepage,
            "dbisId" => $this->dbisId,
            "color" => $this->color,
            "iconPath" => $this->iconPath,
            "publicIconPath" => $this->getPublicIconPath(),
            "ipRanges" => $this->ipRanges,
            "createdAtDate" => $this->getCreatedAtDate()->format("j-m-Y"),
            "dbisView" => $this->getDbisView() ? $this->getDbisView()->toAssocArray() : null,
            "dbisSettings" => $this->getDbisSettings() ? $this->getDbisSettings()->toAssocArray() : null,
            "externalIds" => array_map(
                function ($a) {
                    return $a->toAssocArray();
                },
                $this->getExternalIds()
            ),
            "links" => array_map(
                function ($a) {
                    return $a->toAssocArray();
                },
                $this->getLinks()
            ),
            "isFID" => $this->isFID,
            "isConsortium" => $this->isConsortium,
            "isKfL" => $this->isKfL
        ];
    }

    public function toI18nAssocArray(string $lang): array
    {
        // Simply override i18nable assoc entries
        $assoc = $this->toAssocArray();

        $assoc["name"] = $this->name ? $this->name[$lang] : null;
        $assoc["region"] = $this->region ? $this->region[$lang] : null;
        $assoc["city"] = $this->city ? $this->city[$lang] : null;
        $assoc["adress"] = $this->adress ? $this->adress[$lang] : null;
        $assoc["homepage"] = $this->homepage ? $this->homepage[$lang] : null;
        $assoc["externalIds"]  = array_map(
            function ($a) use ($lang) {
                return $a->toI18nAssocArray($lang);
            },
            $this->getExternalIds()
        );
        /*
        $assoc["links"]  = array_map(
            function ($a) use ($lang) {
                return $a->toI18nAssocArray($lang);
            },
            $this->getLinks()
        );
        */
        return $assoc;
    }
}
