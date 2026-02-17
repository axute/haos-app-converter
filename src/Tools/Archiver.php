<?php

namespace App\Tools;

use App\App;
use App\Tools\Archive\ZipFolder;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use ZipArchive;

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

    public static function unzip(string $zipFilePath, string $destinationDir): void
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException("PHP ZipArchive extension is not installed.");
        }

        $zip = new ZipArchive();
        if ($zip->open($zipFilePath) !== true) {
            throw new RuntimeException("Could not open zip file: $zipFilePath");
        }

        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0777, true);
        }

        $zip->extractTo($destinationDir);
        $zip->close();
    }

    public static function checkFiles(string $zipFilePath): ?string
    {
        $zip = new ZipFolder();
        if ($zip->open($zipFilePath) !== true) {
            return null;
        }
        if ($zip->locateName('config.yaml') !== false) {
            $yaml = $zip->getFromName('config.yaml');
            $content = Yaml::parse($yaml);
        } else if ($zip->locateName('config.json') !== false) {
            $json = $zip->getFromName('config.json');
            $content = json_decode($json, true);
        } else {
            $zip->close();
            return "No config.yaml or config.json found!";
        }
        if ($content === null) {
            $zip->close();
            return "Invalid config content";
        }
        if (array_key_exists('image', $content)) {
            $zip->close();
            if (empty($content['image']) === false) {
                return null;
            }
            return "Docker Image name empty!";
        }
        if ($zip->locateName('Dockerfile') === false) {
            $zip->close();
            return "Dockerimage not found (no Dockerfile, not in config)!";
        }
        $dockerfileContent = $zip->getFromName('Dockerfile');
        if (str_starts_with($dockerfileContent, 'FROM') === false) {
            $zip->close();
            return "Dockerfile does not start with FROM (no simple content?)";
        }
        $zip->close();
        return null;

    }
}
