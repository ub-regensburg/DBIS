<?php

namespace App\Domain\Resources\Exceptions;

use Exception;

class ResourceNotFoundException extends Exception
{
    /** @var type */
    private $resourceId;

    public function __construct(int $resourceId)
    {
        $this->resourceId = $resourceId;
    }

    public function errorMessage()
    {
      //error message
        $errorMsg = 'Error on line ' . $this->getLine() . ' in ' . $this->getFile()
        . ': Resource with id ' . $this->resourceId . ' not found!';
        return $errorMsg;
    }
}
