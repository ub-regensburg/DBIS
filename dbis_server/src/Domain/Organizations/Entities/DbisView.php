<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Entities;


/**
 * DBIS View
 *
 * Data representing a DBIS view (for an organization)
 *
 */
class DbisView
{
    /**
     * Assoc Array with config data
     * @var array
     */
    private $config = array();

    public function __construct() {
        // $this->config = $config;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getConfigValue($key): ?string
    {
        return array_key_exists($key, $this->config) ? $this->config[$key] : null;
    }

    public function toAssocArray(): array
    {
        return [
            "config" => $this->config
        ];
    }

    public function toI18NAssocArray($lang): array
    {
        $assoc = $this->toAssocArray();
        // overwrite all i18n-fields with local value
        return $assoc;
    }
}
