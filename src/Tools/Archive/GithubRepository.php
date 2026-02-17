<?php

namespace App\Tools\Archive;

use App\Interfaces\ArchiveInterface;
use ZipArchive;

class GithubRepository extends ZipArchive implements ArchiveInterface
{
    use ArchiveTrait;

    protected string $tempZip = '';
    protected string $basePath = '';

    public function __destruct()
    {
        if (!empty($this->tempZip) && file_exists($this->tempZip)) {
            @unlink($this->tempZip);
        }
    }

    public function open(string $filename, ?int $flags = 0): bool|int
    {
        if (!str_starts_with($filename, 'https://github.com/')) {
            // Wenn es keine GitHub URL ist, versuchen wir es als normale Datei zu öffnen
            return parent::open($filename, $flags ?? 0);
        }

        $url = rtrim($filename, '/');
        $zipUrl = $url . '/archive/refs/heads/main.zip';
        $content = @file_get_contents($zipUrl);

        if ($content === false) {
            $zipUrl = $url . '/archive/refs/heads/master.zip';
            $content = @file_get_contents($zipUrl);
        }

        if ($content === false) {
            return false;
        }

        $this->tempZip = tempnam(sys_get_temp_dir(), 'ghrepo_');
        file_put_contents($this->tempZip, $content);

        $opened = parent::open($this->tempZip, $flags);
        if ($opened === true) {
            // GitHub ZIPs haben immer einen Wurzelordner: "RepositoryName-BranchName/"
            // Wir finden den Namen des ersten Eintrags heraus, um ihn als BasePath zu nutzen
            $firstEntry = $this->getNameIndex(0);
            if ($firstEntry && str_contains($firstEntry, '/')) {
                $this->basePath = explode('/', $firstEntry)[0] . '/';
            }
        }

        return $opened;
    }

    protected function prepareFilename(string $filename): string
    {
        return $this->basePath . ltrim($filename, '/');
    }

    public function getFromName(string $name, int $len = 0, ?int $flags = 0): string|false
    {
        return parent::getFromName($this->prepareFilename($name), $len, $flags ?? 0);
    }

    public function locateName(string $name, ?int $flags = 0): int|false
    {
        return parent::locateName($this->prepareFilename($name), $flags ?? 0);
    }

    public function deleteName(string $name): bool
    {
        return parent::deleteName($this->prepareFilename($name));
    }

    public function addFromString(string $name, string $content, int $flags = 0): bool
    {
        return parent::addFromString($this->prepareFilename($name), $content, $flags);
    }

    public function addEmptyDir(string $dirname, int $flags = 0): bool
    {
        return parent::addEmptyDir($this->prepareFilename($dirname), $flags);
    }

    public function getStream(string $name): mixed
    {
        return parent::getStream($this->prepareFilename($name));
    }

    public function statName(string $name, ?int $flags = 0): array|false
    {
        return parent::statName($this->prepareFilename($name), $flags ?? 0);
    }

    public function isDir(string $directory): bool
    {
        return $this->locateName(rtrim($directory, '/') . '/') !== false;
    }

    public function extractTo(string $pathto, array|string|null $files = null): bool
    {
        // Wenn bestimmte Dateien extrahiert werden sollen, müssen wir deren Namen anpassen
        if ($files !== null) {
            if (is_array($files)) {
                $files = array_map(fn($f) => $this->prepareFilename($f), $files);
            } else {
                $files = $this->prepareFilename($files);
            }
        }
        return parent::extractTo($pathto, $files);
    }
}
