<?php

namespace App\Domain\Organizations\Exceptions;

use Exception;

class OrganizationWithUbrIdNotExistingException extends Exception
{
    /** @var type */
    private $ubrId;

    public function __construct(string $ubrId)
    {
        $this->ubrId = $ubrId;
    }

    public function errorMessage()
    {
      //error message
        $errorMsg = 'Error on line ' . $this->getLine() . ' in ' . $this->getFile()
        . ': Organization with UBR ID ' . $this->ubrId . ' not existing!';
        return $errorMsg;
    }
}
