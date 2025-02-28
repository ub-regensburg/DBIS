<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Entities;

/**
 * DBIS View
 *
 * Data representing a DBIS view (for an organization)
 *
 */
class Link
{
    /**
     *
     * @var array
     */
    private array $url;

    /**
     *
     * @var array
     */
    private array $text;

    public function __construct(
        $url,
        $text
    ) {
        $this->url = $url;
        $this->text = $text;
    }

    public function getUrl(): ?array
    {
        return $this->url;
    }

    public function setUrl($url): void
    {
        $this->url = $url;
    }

    public function getText(): ?array
    {
        return $this->text;
    }

    public function setText($text): void
    {
        $this->text = $text;
    }

    public function isEmpty(): bool
    {
        if (!is_null($this->url) && is_array($this->url)) {
            return ((array_key_exists('de', $this->url) && $this->url['de'] && strlen($this->url['de']) > 0) || (array_key_exists('en', $this->url) && $this->url['en'] && strlen($this->url['en']) > 0)) ? false : true;
        }
        return true;
    }

    public function toAssocArray(): array
    {
        return [
            "url" => $this->url,
            "text" => $this->text
        ];
    }
}
