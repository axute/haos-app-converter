<?php

namespace App\Interfaces;

/**
 * Interface ZipArchiveInterface
 *
 * Ein Interface für die PHP-interne ZipArchive-Klasse, um Mocking und Dependency Injection zu ermöglichen.
 */
interface ArchiveInterface
{
    public function isDir(string $directory): bool;
    public function open(string $filename, ?int $flags = 0): bool|int;

    public function isApp(): bool;

    public function isRepository(): bool;

    public function setRepository(bool $repository): static;

    public function close(): bool;

    public function addFile(string $filepath, string $entryname = "", int $start = 0, int $length = 0, int $flags = 0): bool;

    public function extractTo(string $pathto, array|string|null $files = null): bool;

    public function getFromName(string $name, int $len = 0, ?int $flags = 0): string|false;

    public function locateName(string $name, ?int $flags = 0): int|false;

    public function deleteName(string $name): bool;

    public function addFromString(string $name, string $content, int $flags = 0): bool;

    public function addEmptyDir(string $dirname, int $flags = 0): bool;

    public function setPassword(string $password): bool;

    public function getStream(string $name): mixed;

    public function getNameIndex(int $index, int $flags = 0): string|false;

    public function statName(string $name, ?int $flags = 0): array|false;

    public function statIndex(int $index, int $flags = 0): array|false;

    public function getStatusString(): string;

    public function getExternalAttributesName(string $name, int &$opsys, int &$attr, int $flags = 0): bool;

    public function setExternalAttributesName(string $name, int $opsys, int $attr, int $flags = 0): bool;
}
