<?php

namespace App\Domain\Resources\Exceptions;

use Exception;
use Throwable;

class CollectionNotFoundException extends Exception
{
    /** @var type */
    private $collectionId;

    public function __construct(int $resourceId)
    {
        $this->collectionId = $resourceId;
    }

    public function errorMessage()
    {
        //error message
        $errorMsg = 'Error on line ' . $this->getLine() . ' in ' . $this->getFile()
            . ': Collection with id ' . $this->collectionId . ' not found!';
        return $errorMsg;
    }
}
