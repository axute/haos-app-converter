<?php

namespace App\Tools\Archive;

trait ArchiveTrait
{

    protected bool $repository = false;
    public function isApp():bool
    {
        return !$this->repository;
    }
    public function isRepository(): bool
    {
        return $this->repository;
    }

    public function setRepository(bool $repository): static
    {
        $this->repository = $repository;
        return $this;
    }
}