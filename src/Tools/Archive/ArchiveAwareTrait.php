<?php

namespace App\Tools\Archive;

use App\Interfaces\ArchiveInterface;

trait ArchiveAwareTrait
{
    protected ArchiveInterface $archive;

    public function getArchive(): ArchiveInterface
    {
        return $this->archive;
    }

    abstract public function prepareFilename(string $filename): string;

    public function isFile(string $filePath): bool
    {
        return $this->archive->locateName($this->prepareFilename($filePath)) !== false;
    }

    public function loadFile(string $filePath): string|false
    {
        return $this->archive->getFromName($this->prepareFilename($filePath));
    }

    public function saveFile(string $filePath, string $content): bool
    {
        return $this->archive->addFromString($this->prepareFilename($filePath), $content);
    }

    public function deleteFile(string $filePath): bool
    {
        return $this->archive->deleteName($this->prepareFilename($filePath));
    }
}