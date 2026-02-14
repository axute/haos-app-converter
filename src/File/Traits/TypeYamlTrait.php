<?php

namespace App\File\Traits;

use App\Tools\Converter;
use Symfony\Component\Yaml\Yaml;

trait TypeYamlTrait
{
    public function __toString(): string
    {

        return Yaml::dump($this->jsonSerialize(), 10, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_COMPACT_NESTED_MAPPING);
    }

    abstract public function jsonSerialize(): array;

    public function loadFileContent(): static
    {
        if ($this->isFile()) {
            $this->addData(Yaml::parseFile($this->getFilePath()));
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