<?php

namespace App\Domain\Organizations\Exceptions;

use Exception;

/**
 * This is the same as OrganizationWithUbrIdExistingException, but the
 * organization has been deleted
 */
class OrganizationWithUbrIdTakenException extends Exception
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
        . ': UBR ID ' . $this->ubrId . ' already taken!';
        return $errorMsg;
    }
}
