<?php

namespace App\Tools;

class AssetPublisher
{
    private const ASSETS = [
        'npm-asset/bootstrap/dist/js/bootstrap.bundle.min.js' => 'public/js/vendor/bootstrap.bundle.min.js',
        'npm-asset/bootstrap/dist/css/bootstrap.min.css'      => 'public/css/vendor/bootstrap.min.css',
        'npm-asset/htmx.org/dist/htmx.min.js'                => 'public/js/vendor/htmx.min.js',
        'npm-asset/easymde/dist/easymde.min.js'              => 'public/js/vendor/easymde.min.js',
        'npm-asset/easymde/dist/easymde.min.css'             => 'public/css/vendor/easymde.min.css',
        'npm-asset/codemirror/lib/codemirror.js'             => 'public/js/vendor/codemirror/lib/codemirror.js',
        'npm-asset/codemirror/lib/codemirror.css'            => 'public/css/vendor/codemirror/lib/codemirror.css',
        'npm-asset/codemirror/mode/shell/shell.js'           => 'public/js/vendor/codemirror/mode/shell/shell.js',
        'npm-asset/codemirror/theme/monokai.css'             => 'public/css/vendor/codemirror/theme/monokai.css',
        'npm-asset/font-awesome/css/font-awesome.min.css'    => 'public/css/vendor/font-awesome.min.css',
        'npm-asset/font-awesome/fonts/'                      => 'public/css/vendor/fonts/',
        'npm-asset/mdi__font/css/materialdesignicons.min.css'=> 'public/css/vendor/materialdesignicons.min.css',
        'npm-asset/mdi__font/fonts/'                         => 'public/css/vendor/fonts/',
    ];

    public static function publish(bool $force = false): void
    {
        $baseDir = __DIR__ . '/../../';
        $vendorDir = $baseDir . 'vendor/';

        foreach (self::ASSETS as $source => $dest) {
            $sourcePath = $vendorDir . $source;
            $destPath = $baseDir . $dest;

            if (!file_exists($sourcePath)) {
                continue;
            }

            if (is_dir($sourcePath)) {
                self::copyDir($sourcePath, $destPath);
            } else {
                if ($force || !file_exists($destPath) || filemtime($sourcePath) > filemtime($destPath)) {
                    $dir = dirname($destPath);
                    if (!is_dir($dir)) {
                        mkdir($dir, 0777, true);
                    }
                    copy($sourcePath, $destPath);
                }
            }
        }
    }

    private static function copyDir(string $src, string $dst): void
    {
        if (!is_dir($dst)) {
            mkdir($dst, 0777, true);
        }
        $dir = opendir($src);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    self::copyDir($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }
}
