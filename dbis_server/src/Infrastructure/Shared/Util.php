<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared;

/**
 * Util
 *
 * Class for useful util functions
 *
 */
class Util
{
        /**
     * Util for getting assoc values after checking if it is set
     * @return string|null
     */
    public static function getValueSafely(array $assocArray, string $key): ?string
    {
        return $assocArray[$key] ?? null;
    }
    //put your code here
}
