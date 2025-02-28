<?php

namespace App\Domain\Shared\Exceptions;

use Exception;

class EmailAdressInvalidException extends Exception
{
    /** @var type */
    private $email;

    public function __construct($email)
    {
        $this->email = $email;
    }

    public function errorMessage()
    {
      //error message
        $errorMsg = 'Error on line ' . $this->getLine() . ' in ' . $this->getFile()
        . ': ' . $this->email . ' is an invalid email adress!';
        return $errorMsg;
    }
}
