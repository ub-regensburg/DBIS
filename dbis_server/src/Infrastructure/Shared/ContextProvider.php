<?php

namespace App\Infrastructure\Shared;

/**
 * Manager Class for delivering infos for request contexts
 *
 */
class ContextProvider
{
    private ? string $controllerClass;
    private ? array $queryParams;
    private ? string $url;
    private ? array $resultIds;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->url = $_SESSION['context_url'] ?? null;
        $this->controllerClass = $_SESSION['context_controller_class'] ?? null;
        $this->queryParams = $_SESSION['context_query'] ?? null;
        $this->resultIds = $_SESSION['context_result_ids'] ?? null;
    }

    public function setContext(string $class, array $queryParams, array $resultIds)
    {
        $this->url = "$_SERVER[REQUEST_URI]";
        $this->controllerClass = $class;
        $this->queryParams = $queryParams;
        $this->resultIds = $resultIds;
        $this->persistContext();
    }

    private function persistContext()
    {
        $_SESSION['context_url'] = $this->url;
        $_SESSION['context_controller_class'] = $this->controllerClass;
        $_SESSION['context_query'] = $this->queryParams;
        $_SESSION['context_result_ids'] = $this->resultIds;
    }

    public function hasContext() : bool
    {
        return $_SESSION['context_url'] != null;
    }

    public function getUrl() : ?string
    {
        return $this->url;
    }

    public function getControllerClass() : ?string
    {
        return $this->controllerClass;
    }

    public function getQueryParams() : ?array
    {
        return $this->queryParams;
    }

    public function getResultIds(): ?array
    {
        return $this->resultIds;
    }
}
