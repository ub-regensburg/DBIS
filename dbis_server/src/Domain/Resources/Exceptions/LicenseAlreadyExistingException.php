<?php

namespace App\Domain\Resources\Exceptions;

use Exception;

class LicenseAlreadyExistingException extends Exception
{
    private string $licenseId;

    public function __construct(int $licenseId)
    {
        $this->resourceId = $licenseId;
    }

    public function errorMessage()
    {
      //error message
        $errorMsg = 'Error on line ' . $this->getLine() . ' in ' . $this->getFile()
        . ': License with id ' . $this->licenseId . ' already existing on resource!';
        return $errorMsg;
    }
}
