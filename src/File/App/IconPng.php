<?php

namespace App\File\App;

use App\File\FileAbstract;
use App\Tools\Webform;

class IconPng extends FileAbstract
{
    public ?string $icon_file = null;


    public function getFilename(): string
    {
        return 'icon.png';
    }

    public function loadFileContent(): static
    {
        if ($this->isFile()) {
            $this->icon_file = 'data:image/png;base64,' . base64_encode($this->loadFile());
        }
        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'icon_file'      => $this->icon_file,
            'has_local_icon' => $this->isFile()
        ];
    }

    public function __toString(): string
    {
        if ($this->icon_file !== null && preg_match('/^data:image\/(\w+);base64,/', $this->icon_file, $type)) {
            $iconData = substr($this->icon_file, strpos($this->icon_file, ',') + 1);
            return base64_decode($iconData);
        }
        return '';
    }

    public function updateFromWebui(Webform $webform): static
    {
        $webform->setIfNotEmpty($this, 'icon_file');
        return $this->saveFileContent();
    }

    public function saveFileContent(): static
    {
        if ($this->icon_file !== null) {
            $this->saveFile();
        } else if ($this->isFile()) {
            $this->clearFile();
        }
        return $this;
    }
}