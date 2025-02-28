<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Domain\Shared;

use App\Domain\Shared\Exceptions\EmailAdressInvalidException;
use App\Domain\Shared\Exceptions\MandatoryValueNullException;
use App\Domain\Shared\Exceptions\StringIsEmptyException;
use App\Domain\Shared\Exceptions\UrlInvalidException;
use App\Domain\Shared\Exceptions\InvalidFiletypeException;
use App\Domain\Shared\Exceptions\ContainsSpecialCharsException;

/**
 * Description of ValidatorUtil
 *
 */
class ValidatorUtil
{
    /**
     * @throws EmailAdressInvalidException
     */
    public static function isEMailAdressValid(
        string $email = null,
        bool $isThrowingError = true
    ): bool {
        if (!$email) {
            return true;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if ($isThrowingError) {
                throw new EmailAdressInvalidException($email);
            }
            return false;
        }
        return true;
    }

    /**
     * @throws UrlInvalidException
     */
    public static function isUrlValid(
        string $url = null,
        bool $isThrowingError = true
    ): bool {
        if (!$url) {
            return true;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            if ($isThrowingError) {
                throw new UrlInvalidException($url);
            }
            return false;
        }
        return true;
    }

    /**
     * @throws MandatoryValueNullException
     */
    public static function isValueNotNull(
        $value,
        bool $isThrowingError = true
    ): bool {
        if ($value == null) {
            if ($isThrowingError) {
                throw new MandatoryValueNullException($value);
            }
            return false;
        }
        return true;
    }

    /**
     * @throws StringIsEmptyException
     */
    public static function isStringNotEmpty(
        $value,
        bool $isThrowingError = true
    ): bool {
        if (strlen($value) == 0) {
            if ($isThrowingError) {
                throw new StringIsEmptyException($value);
            }
            return false;
        }
        return true;
    }

    /**
     * @throws ContainsSpecialCharsException
     */
    public static function hasNoSpecialChars(
        string $value,
        bool $isThrowingError = true
    ): bool {
        if (!$value) {
            return true;
        }
        // code taken from https://stackoverflow.com/questions/3938021/how-to-check-for-special-characters-php
        if (preg_match('/[\'^£$%&*()}{@#~?><>,|=_+¬-]/', $value)) {
            if ($isThrowingError) {
                throw new ContainsSpecialCharsException($value);
            }
            return false;
        }
        return true;
    }


    /**
     * @throws InvalidFiletypeException
     */
    public static function isFileOfType(
        $filename,
        $types,
        bool $isThrowingError = true
    ) {
        $splitString = explode('.', $filename);
        $extension = end($splitString);
        $extensionFormatted = strtolower($extension);

        if (!$filename) {
            return true;
        }
        if (!in_array($extensionFormatted, $types)) {
            if ($isThrowingError) {
                throw new InvalidFiletypeException($extension);
            }
            return false;
        }
        return true;
    }
}
