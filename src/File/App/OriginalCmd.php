<?php

namespace App\File\App;

use App\File\FileAbstract;
use App\Tools\Converter;
use App\Tools\Crane;
use App\Tools\Webform;

class OriginalCmd extends FileAbstract
{
    public ?string $original_cmd = null;

    public function loadFileContent(): static
    {
        $this->original_cmd = $this->isFile() ? file_get_contents($this->getFilePath()) : '';
        return $this;
    }

    public function getFilename(): string
    {
        return 'original_cmd';
    }

    public function __toString(): string
    {
        if (!empty($this->original_cmd)) {
            return $this->original_cmd;
        }
        return '';
    }

    public function jsonSerialize(): array
    {
        return [
            'original_cmd' => $this->original_cmd,
        ];
    }

    public function updateFromWebui(Webform $webform): static
    {
        $this->original_cmd = $webform->isQuirks() ? Crane::getOriginalCmd($webform->extractFullDockerImageName(),'') : null;
        return $this->saveFileContent();
    }

    public function saveFileContent(): static
    {
        if ($this->original_cmd !== null) {
            Converter::writeFileContent($this->getFilePath(), $this->original_cmd);
            return $this;
        }
        return $this->clearFile();
    }
}