<?php

namespace App\Domain\Resources\Entities;

class ExternalID
{
    private string $namespace = "";
    private string $id = "";
    private string $id_name = "";

    public function __construct(
        string $namespace,
        string $id,
        string $id_name
    ) {
        $this->namespace = $namespace;
        $this->id = $id;
        $this->id_name = $id_name;
    }

    public function getNamespace(): string {
        return $this->namespace;
    }

    public function getId(): string {
        return $this->id;
    }

    public function getIdName(): string {
        return $this->id_name;
    }

    public function toAssocArray(): array
    {
        return [
            "namespace" => $this->namespace,
            "id" => $this->id,
            "id_name" => $this->id_name
        ];
    }
}