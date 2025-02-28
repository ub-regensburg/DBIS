<?php

namespace App\Domain\Shared\Exceptions;

use Exception;

class AuthenticationFailedException extends Exception
{
    public function errorMessage()
    {
      //error message
        $errorMsg = 'Error on line ' . $this->getLine() . ' in ' . $this->getFile()
        . ': Authentication has failed!';
        return $errorMsg;
    }
}
