<?php

namespace App\File;

use App\Tools\App;
use App\Tools\Webform;
use JsonSerializable;
use Stringable;

abstract class FileAbstract implements JsonSerializable, Stringable
{

    public function __construct(protected App $app)
    {
        $this->loadFileContent();
    }

    abstract public function loadFileContent(): static;

    public function clearFile(): static
    {
        if ($this->isFile()) {
            unlink($this->getFilePath());
        }
        return $this;
    }

    public function isFile(): bool
    {
        return is_file($this->getFilePath());
    }

    public function getFilePath(): string
    {
        return $this->app->getAppDir() . '/' . $this->getFilename();
    }

    abstract public function getFilename(): string;

    abstract public function saveFileContent(): static;

    abstract public function updateFromWebui(Webform $webform): static;

    abstract public function jsonSerialize(): array;


}