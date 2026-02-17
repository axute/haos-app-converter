<?php

namespace App\Tools\Archive;

use App\Interfaces\ArchiveInterface;
use ZipArchive;

/**
 * Wrapper für ZipArchive, um ZipArchiveInterface zu implementieren.
 *
 * Dies ermöglicht das Testen von Code, der mit ZIP-Dateien arbeitet.
 */
class ZipFolder extends ZipArchive implements ArchiveInterface
{
    use ArchiveTrait;

    // Die meisten Methoden werden automatisch von ZipArchive geerbt.
    // Falls ZipArchive in Zukunft signifikante Änderungen hat, kann hier angepasst werden.

    public function getStream(string $name): mixed
    {
        return parent::getStream($name);
    }

    public function isDir(string $directory): bool
    {
        return $this->getFromName(rtrim($directory, '/') . '/') !== false;
    }
}
