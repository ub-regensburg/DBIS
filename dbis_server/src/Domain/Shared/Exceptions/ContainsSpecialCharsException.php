<?php

namespace App\Domain\Shared\Exceptions;

use Exception;

class ContainsSpecialCharsException extends Exception
{
    /** @var type */
    private $fieldName;

    public function __construct(string $fieldName)
    {
        $this->fieldName = $fieldName;
    }

    public function errorMessage()
    {
      //error message
        $errorMsg = 'Error on line ' . $this->getLine() . ' in ' . $this->getFile()
        . ': ' . $this->fieldName . ' must not contain special chars!';
        return $errorMsg;
    }
}
