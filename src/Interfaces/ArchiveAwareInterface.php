<?php

namespace App\Interfaces;

interface ArchiveAwareInterface
{
    public function getArchive(): ArchiveInterface;
    public function prepareFilename(string $filename): string;
    public function isFile(string $filePath): bool;

    public function loadFile(string $filePath): string|false;

    public function saveFile(string $filePath, string $content): bool;

    public function deleteFile(string $filePath): bool;
}