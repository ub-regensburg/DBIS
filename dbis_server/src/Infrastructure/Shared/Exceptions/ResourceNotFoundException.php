<?php

namespace App\Infrastructure\Shared\Exceptions;

use Exception;
use Throwable;

class ResourceNotFoundException extends Exception
{
    /** @var string */
    private $key;

    public function __construct(string $key, string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->key = $key;
    }

    public function errorMessage()
    {
      //error message
        $errorMsg = 'Error on line ' . $this->getLine() . ' in ' . $this->getFile()
        . ': Resource ' . $this->key . ' not found!';
        return $errorMsg;
    }
}
