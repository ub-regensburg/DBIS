<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Entities;


class DbisSettings
{
    /**
     * Assoc Array with config data
     * @var bool
     */
    private bool $autoaddflag = true;

    public function __construct() {
        
    }

    public function getAutoAddFlag(): bool
    {
        return $this->autoaddflag;
    }

    public function setAutoAddFlag($autoaddflag)
    {
        $this->autoaddflag = $autoaddflag;
    }

    public function toAssocArray(): array
    {
        return [
            "autoaddflag" => $this->autoaddflag
        ];
    }
}
