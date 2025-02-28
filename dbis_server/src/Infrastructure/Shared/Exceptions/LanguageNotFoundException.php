<?php

namespace App\Infrastructure\Shared\Exceptions;

use Exception;

class LanguageNotFoundException extends Exception
{
    public function errorMessage()
    {
      //error message
        $errorMsg = 'Error on line ' . $this->getLine() . ' in ' . $this->getFile()
        . ': Language not found!';
        return $errorMsg;
    }
}
