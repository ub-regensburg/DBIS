<?php

namespace App\Domain\Resources\Entities;

class AccessMapping
{
    /** @var string */
    private string $dbis_id;
    /** @var int */
    private int $resource_id;
    /** @var int */
    private int $zugang_id;
    /** @var string */
    private string $nutzung;
    /** @var string */
    private string $kurznutzung;
    /** @var string */
    private string $long_text;

    public function __construct(
        string $dbis_id,
        int $resource_id
    ) {
        $this->dbis_id = $dbis_id;
        $this->resource_id = $resource_id;
    }

    public function getNutzung(): string
    {
        return $this->nutzung;
    }

    public function setNutzung(string $nutzung): void
    {
        $this->nutzung = $nutzung;
    }

    public function getKurznutzung(): string
    {
        return $this->kurznutzung;
    }

    public function setKurznutzung(string $kurznutzung): void
    {
        $this->kurznutzung = $kurznutzung;
    }

    public function getResourceId(): int
    {
        return $this->resource_id;
    }

    public function setResourceId(int $resource_id): void
    {
        $this->resource_id = $resource_id;
    }

    public function getLongText(): string
    {
        return $this->long_text;
    }

    public function setLongText(string $long_text): void
    {
        $this->long_text = $long_text;
    }

    public function getZugangId(): int
    {
        return $this->zugang_id;
    }

    /**
     * @return string with the format <pre>access_<zugang_id></pre>
     */
    public function getPrefixedZugangId(): string
    {
        return 'access_'.$this->zugang_id;
    }

    public function setZugangId(int $zugang_id): void
    {
        $this->zugang_id = $zugang_id;
    }

    public function getDbisId(): string
    {
        return $this->dbis_id;
    }

    public function setDbisId(string $dbis_id): void
    {
        $this->dbis_id = $dbis_id;
    }

    public function toAssocArray(): array
    {
        return [
            "bib_id" => $this->dbis_id,
            "titel_id" => $this->resource_id,
            "zugang_id" => $this->zugang_id,
            "nutzung" => $this->nutzung,
            "kurznutzung" => $this->kurznutzung,
            "long_text" => $this->long_text
        ];
    }
}
