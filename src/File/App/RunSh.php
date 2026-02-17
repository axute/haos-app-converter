<?php

namespace App\File\App;

use App\File\FileAbstract;
use App\Tools\Webform;

class RunSh extends FileAbstract
{
    protected bool $active = false;

    public function __toString(): string
    {
        return file_get_contents(__DIR__ . '/../../../helper/run.sh');
    }

    public function getFilename(): string
    {
        return 'run.sh';
    }

    public function loadFileContent(): static
    {
        $this->active = $this->isFile();
        return $this;
    }

    public function jsonSerialize(): array
    {
        return [];
    }

    public function updateFromWebui(Webform $webform): static
    {
        $this->active = $webform->isQuirks();
        return $this->saveFileContent();
    }

    public function saveFileContent(): static
    {
        if ($this->active) {
            $this->saveFile();
            return $this;
        }
        return $this->clearFile();
    }
}