<?php

namespace App\File\Traits;

use Symfony\Component\Yaml\Yaml;

trait TypeYamlTrait
{
    public function __toString(): string
    {

        return Yaml::dump($this->jsonSerialize(), 10, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_COMPACT_NESTED_MAPPING);
    }

    abstract public function jsonSerialize(): array;

    abstract public function isFile(): bool;

    abstract public function loadFile(): string|false;

    abstract public function saveFile(): bool;

    public function loadFileContent(): static
    {
        if ($this->isFile()) {
            $this->addData(Yaml::parse($this->loadFile()));
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