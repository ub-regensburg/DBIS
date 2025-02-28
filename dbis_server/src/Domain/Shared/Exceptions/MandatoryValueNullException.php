<?php

namespace App\Domain\Shared\Exceptions;

use Exception;

class MandatoryValueNullException extends Exception
{
    /** @var type */
    private $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function errorMessage()
    {
      //error message
        $errorMsg = 'Error on line ' . $this->getLine() . ' in ' . $this->getFile()
        . ': ' . $this->value . ' must not be null!';
        return $errorMsg;
    }
}
