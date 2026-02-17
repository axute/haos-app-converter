<?php

namespace App\Tools\Archive;

use App\Interfaces\ArchiveInterface;
use App\Tools\Converter;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class DataFolder implements ArchiveInterface
{
    use ArchiveTrait;

    protected string $directory = '/tmp/';
    protected array $files = [];
    public int $numFiles = 0;

    public function open(string $filename, int $flags = 0): bool|int
    {
        if (is_dir($filename)) {
            $this->directory = $filename;
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($filename, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
            $files = [];
            foreach ($iterator as $file) {
                /** @var $file SplFileInfo */
                if ($file->isDir()) {
                    $files[] = $file->getPathname() . '/';
                } else {
                    $files[] = $file->getPathname();
                }
            }
            $this->files = array_values(array_map(fn($file) => str_replace(rtrim($filename, '/') . '/', '', $file), $files));
            $this->numFiles = sizeof($this->files);
            return true;
        }
        return false;
    }

    public function close(): bool
    {
        return true;
    }

    public function addFile(string $filepath, string $entryname = "", int $start = 0, int $length = 0, int $flags = 0): bool
    {
        if (is_file($filepath)) {
            $targetPath = $this->directory . '/' . (empty($entryname) ? $filepath : $entryname);
            Converter::writeFileContent($targetPath, file_get_contents($filepath));
            return true;
        }
        return false;
    }

    public function extractTo(string $pathto, array|string|null $files = null): bool
    {
        if (is_dir($pathto)) {
            return true;
        }
        return false;
    }

    public function getFromName(string $name, int $len = 0, int $flags = 0): string|false
    {
        $str = $this->directory . '/' . $name;
        if (is_file($str)) {
            return file_get_contents($str);
        }
        return false;
    }

    public function locateName(string $name, int $flags = 0): int|false
    {
        return array_search($name, $this->files);
    }

    public function deleteName(string $name): bool
    {
        $targetFile = $this->directory . '/' . $name;
        if (is_file($targetFile)) {
            unlink($targetFile);
        }
        if (in_array($name, $this->files)) {
            $key = array_search($name, $this->files);
            if ($key !== false) {
                unset($this->files[$key]);
            }
        }
        return true;
    }

    public function addFromString(string $name, string $content, int $flags = 0): bool
    {
        $targetFile = $this->directory . '/' . $name;
        Converter::writeFileContent($targetFile, $content);
        return true;
    }

    public function addEmptyDir(string $dirname, int $flags = 0): bool
    {
        $targetDir = $this->directory . '/' . $dirname . '/';
        if (is_dir($targetDir)) {
            return true;
        }
        mkdir($targetDir, 0777, true);
        return true;
    }

    public function setPassword(string $password): bool
    {
        return true;
    }

    public function getStream(string $name): mixed
    {
        $targetFile = $this->directory . '/' . $name;
        if (is_file($targetFile)) {
            return fopen($targetFile, 'rb');
        }
        return false;
    }

    public function getNameIndex(int $index, int $flags = 0): string|false
    {
        if ($index >= count($this->files)) {
            return false;
        }
        return $this->files[$index];
    }

    public function statName(string $name, int $flags = 0): array|false
    {
        $targetFile = $this->directory . '/' . $name;
        if (is_file($targetFile)) {
            return stat($targetFile);
        }
        return false;
    }

    public function statIndex(int $index, int $flags = 0): array|false
    {
        if ($index >= count($this->files)) {
            return false;
        }
        return stat($this->directory . '/' . $this->files[$index]);
    }

    public function getStatusString(): string
    {
        return '';
    }

    public function getExternalAttributesName(string $name, int &$opsys, int &$attr, int $flags = 0): bool
    {
        return true;
    }

    public function setExternalAttributesName(string $name, int $opsys, int $attr, int $flags = 0): bool
    {
        return true;
    }

    public function isDir(string $directory): bool
    {
        return is_dir($this->directory . '/' . $directory);
    }
}