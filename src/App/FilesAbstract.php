<?php

namespace App\App;

abstract class FilesAbstract
{
    public static function getAppDir(string $slug): string
    {
        return self::getDataDir() . '/' . $slug;
    }

    public static function getDataDir(): string
    {
        return str_replace('\\', '/', getenv('CONVERTER_DATA_DIR') ?: __DIR__ . '/../../data');
    }
}