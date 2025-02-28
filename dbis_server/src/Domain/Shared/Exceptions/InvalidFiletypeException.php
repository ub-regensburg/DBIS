<?php

namespace App\Domain\Shared\Exceptions;

use Exception;

class InvalidFiletypeException extends Exception
{
    /** @var type */
    private $filename;

    public function __construct($filename)
    {
        $this->filename = $filename;
    }

    public function errorMessage()
    {
      //error message
        $errorMsg = 'Error on line ' . $this->getLine() . ' in ' . $this->getFile()
        . ': ' . $this->filename . ' is of invalid type!';
        return $errorMsg;
    }
}
