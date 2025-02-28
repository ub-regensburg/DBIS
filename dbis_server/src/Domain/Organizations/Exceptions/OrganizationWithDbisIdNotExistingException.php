<?php

namespace App\Domain\Organizations\Exceptions;

use Exception;

class OrganizationWithDbisIdNotExistingException extends Exception
{
    /** @var type */
    private $dbisId;

    public function __construct(string $dbisId)
    {
        $this->dbisId = $dbisId;
    }

    public function errorMessage()
    {
      //error message
        $errorMsg = 'Error on line ' . $this->getLine() . ' in ' . $this->getFile()
        . ': Organization with DBIS ID ' . $this->dbisId . ' not existing!';
        return $errorMsg;
    }
}
