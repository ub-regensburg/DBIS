<?php

namespace App\Domain\Organizations\Exceptions;

use Exception;

class OrganizationWithIpNotExistingException extends Exception
{
    /** @var string */
    private $ip;

    public function __construct(string $ip)
    {
        $this->ip = $ip;
    }

    public function errorMessage()
    {
      //error message
        $errorMsg = 'Error on line ' . $this->getLine() . ' in ' . $this->getFile()
        . ': Organization with IP ' . $this->ip . ' not existing!';
        return $errorMsg;
    }
}
