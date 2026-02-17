<?php

namespace App\File\Traits;

trait TypeJsonTrait
{
    public function __toString()
    {
        return json_encode($this->jsonSerialize(), JSON_PRETTY_PRINT);
    }

    abstract public function jsonSerialize(): array;

    abstract public function isFile(): bool;

    abstract public function loadFile(): string|false;

    abstract public function saveFile(): bool;

    public function loadFileContent(): static
    {
        if ($this->isFile()) {
            $this->addData(json_decode($this->loadFile(), true));
        }
        return $this;
    }

    abstract protected function addData(array $data): static;

    public function saveFileContent(): static
    {
        $this->saveFile();
        return $this;
    }
}