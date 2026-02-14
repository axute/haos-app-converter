<?php

namespace App\File\Traits;

use App\Tools\Converter;

trait TypeJsonTrait
{
    public function __toString()
    {
        return json_encode($this->jsonSerialize(), JSON_PRETTY_PRINT);
    }

    abstract public function jsonSerialize(): array;

    public function loadFileContent(): static
    {
        if ($this->isFile()) {
            $this->addData(json_decode(file_get_contents($this->getFilePath()), true));
        }
        return $this;
    }

    abstract public function isFile(): bool;

    abstract protected function addData(array $data): static;

    abstract public function getFilePath(): string;

    public function saveFileContent(): static
    {
        Converter::writeFileContent($this->getFilePath(), $this);
        return $this;
    }
}