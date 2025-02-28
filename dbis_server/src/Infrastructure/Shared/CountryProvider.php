<?php

namespace App\Infrastructure\Shared;

use PDO;
use IntlChar;

/**
 * Country Provider
 *
 * Reads internationalized country names from DB and converts it into neat
 * dictionaries.
 *
 */
class CountryProvider
{
    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getCountryAssocArray(): array
    {
        $sql = "SELECT * FROM Countries;";
        $statement = $this->pdo->prepare($sql);
        $statement->execute();
        $entries = $statement->fetchall(PDO::FETCH_ASSOC);
        $resultArray = [];
        foreach ($entries as $entry) {
            $resultArray[$entry['code']] = [
                "de" => $entry['de'],
                "en" => $entry['en']
            ];
        }
        return $resultArray;
    }

    public function getTranslatedCountryAssocArray(string $lang): array
    {
        $array = $this->getCountryAssocArray();
        foreach (array_keys($array) as $key) {
            $array[$key] = $array[$key][$lang];
        }
        // sort associative array by country names instead of codes
        arsort($array, SORT_LOCALE_STRING);
        $array = array_reverse($array);
        return $array;
    }

    public function getUTF8FlagCountryAssocArray(): array
    {
        # Code Provided by Nick Momrik
        # https://nick.blog/2018/07/27/php-display-country-flag-emoji-from-iso-3166-1-alpha-2-country-codes/
        $flagOffset = 127397;
        $countries = $this->getCountryAssocArray();
        $flags = [];
        foreach (array_keys($countries) as $key) {
            $firstChar = mb_convert_encoding('&#' . ( $flagOffset + ord($key[0]) ) . ';', 'UTF-8', 'HTML-ENTITIES');
            $secondChar = mb_convert_encoding('&#' . ( $flagOffset + ord($key[1]) ) . ';', 'UTF-8', 'HTML-ENTITIES');
            $flags[$key] = $firstChar . $secondChar;
        }
        return $flags;
    }
}
