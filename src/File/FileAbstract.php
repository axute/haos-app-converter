<?php

namespace App\File;

use App\App;
use App\Interfaces\ArchiveAwareInterface;
use App\Repository;
use App\Tools\Webform;
use JsonSerializable;
use Stringable;

/**
 * @property ArchiveAwareInterface|App|Repository $archive
 */
abstract class FileAbstract implements JsonSerializable, Stringable
{

    public function __construct(protected ArchiveAwareInterface $archive)
    {
        $this->loadFileContent();
    }

    public function getArchive(): ArchiveAwareInterface
    {
        return $this->archive;
    }

    abstract public function loadFileContent(): static;

    public function clearFile(): static
    {
        if ($this->getArchive()->isFile($this->getFilename())) {
            $this->getArchive()->deleteFile($this->getFilename());
        }
        return $this;
    }

    public function loadFile(): string|false
    {
        return $this->getArchive()->loadFile($this->getFilename());
    }

    public function saveFile(): bool
    {
        return $this->getArchive()->saveFile($this->getFilename(), $this);
    }

    public function isFile(): bool
    {
        return $this->getArchive()->isFile($this->getFilename());
    }

    abstract public function getFilename(): string;

    abstract public function saveFileContent(): static;

    abstract public function updateFromWebui(Webform $webform): static;

    abstract public function jsonSerialize(): array;
}