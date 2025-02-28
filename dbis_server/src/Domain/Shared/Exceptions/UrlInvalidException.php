<?php

namespace App\Domain\Shared\Exceptions;

use Exception;

class UrlInvalidException extends Exception
{
    /** @var type */
    private $url;

    public function __construct($url)
    {
        $this->url = $url;
    }

    public function errorMessage()
    {
      //error message
        $errorMsg = 'Error on line ' . $this->getLine() . ' in ' . $this->getFile()
        . ': ' . $this->url . ' is an invalid url!';
        return $errorMsg;
    }
}
