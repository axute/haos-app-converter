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

    public function open(string $filename, ?int $flags = 0): bool|int
    {
        return parent::open($filename, $flags ?? 0);
    }

    public function getFromName(string $name, int $len = 0, ?int $flags = 0): string|false
    {
        return parent::getFromName($name, $len, $flags ?? 0);
    }

    public function locateName(string $name, ?int $flags = 0): int|false
    {
        return parent::locateName($name, $flags ?? 0);
    }

    public function statName(string $name, ?int $flags = 0): array|false
    {
        return parent::statName($name, $flags ?? 0);
    }

    public function getStream(string $name): mixed
    {
        return parent::getStream($name);
    }

    public function isDir(string $directory): bool
    {
        return $this->getFromName(rtrim($directory, '/') . '/') !== false;
    }
}
