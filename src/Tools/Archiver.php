<?php

namespace App\Tools;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;
use RuntimeException;

class Archiver
{
    public static function zipApp(string $slug): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException("PHP ZipArchive extension is not installed.");
        }

        $app = App::get($slug);
        $appDir = $app->getAppDir();
        
        if (!is_dir($appDir)) {
            throw new RuntimeException("App directory not found: $appDir");
        }

        $tempDir = App::getDataDir() . '/.cache';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $zipFile = $tempDir . '/' . $slug . '.zip';
        
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Could not create zip file: $zipFile");
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($appDir),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            // Skip directories (they are added automatically)
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen(realpath($appDir)) + 1);

                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();

        return $zipFile;
    }
}
