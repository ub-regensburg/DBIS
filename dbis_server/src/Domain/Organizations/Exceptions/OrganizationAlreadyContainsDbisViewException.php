<?php

namespace App\Domain\Organizations\Exceptions;

use Exception;

class OrganizationAlreadyContainsDbisViewException extends Exception
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
        . ': Organization with UBR-ID ' . $this->dbisId . ' already '
                . 'contains a dbis view!';
        return $errorMsg;
    }
}
