<?php

namespace App\Tools;

use App\App\FilesReader;

class Scripts
{
    const string CACHE_DIR = '.cache';
    const string PM_CACHE_JSON = 'pm_cache.json';

    public static function detectPM(string $image): string
    {
        $helperScript = __DIR__ . '/../../helper/detect_pm.sh';
        $command = "bash " . escapeshellarg($helperScript) . " " . escapeshellarg($image) . " 2>&1";
        $pm = trim(shell_exec($command));

        if (empty($pm)) {
            return 'unknown';
        }
        return $pm;
    }

    public static function getDetectPMCache(): array
    {
        $cacheFile = self::getCacheDir() . '/' . self::PM_CACHE_JSON;
        if (file_exists($cacheFile)) {
            return json_decode(file_get_contents($cacheFile), true) ?: [];
        }
        return [];
    }

    public static function getCacheDir(): string
    {
        // Caching
        $cacheDir = FilesReader::getDataDir() . '/' . self::CACHE_DIR;
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }
        return $cacheDir;
    }

    public static function setDetectPmCache(array $cache): bool
    {
        $cacheFile = self::getCacheDir() . '/' . self::PM_CACHE_JSON;
        return file_put_contents($cacheFile, json_encode($cache, JSON_PRETTY_PRINT)) !== false;
    }
}