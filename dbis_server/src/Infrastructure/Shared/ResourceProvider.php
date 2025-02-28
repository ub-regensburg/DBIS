<?php

namespace App\Infrastructure\Shared;

use App\Infrastructure\Shared\Exceptions\LanguageNotFoundException;
use App\Infrastructure\Shared\Exceptions\ResourceNotFoundException;

/**
 * ResourceProvider
 *
 * Very simple implementation for a translation service, using json-based
 * translation files.
 *
 */
class ResourceProvider
{
    private $translations;

    public function __construct($pathTranslationFile)
    {
        $this->translations = $this->loadTranslations($pathTranslationFile);
    }

    private function loadTranslations($pathTranslationFile)
    {
        $str = file_get_contents($pathTranslationFile);
        return json_decode($str, true);
    }

    public function getText(string $key, string $language)
    {
        if (!array_key_exists($key, $this->translations)) {
            throw new ResourceNotFoundException($key);
        }
        if (!array_key_exists($language, $this->translations[$key])) {
            throw new LanguageNotFoundException();
        }
        return $this->translations[$key][$language];
    }

    public function getAssocArrayForLanguage(string $language): array
    {
        $result = [];
        foreach (array_keys($this->translations) as $key) {
            $result[$key] = $this->getText($key, $language);
        }
        return $result;
    }
}
