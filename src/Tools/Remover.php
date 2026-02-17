<?php

namespace App\Tools;

use App\App;

class Remover
{
    public static function removeApp(string $slug): void
    {
        // System app cannot be deleted
        if ($slug === Converter::SLUG) {
            throw new \RuntimeException('System app cannot be deleted');
        }

        $appDir =  App::get($slug)->getAppDir();

        if (!is_dir($appDir)) {
            throw new \RuntimeException("$appDir is not a directory");
        }

        // Verzeichnis rekursiv löschen
        self::recursiveRmdir($appDir);
    }
    public static function recursiveRmdir($dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . "/" . $object)) {
                        self::recursiveRmdir($dir . DIRECTORY_SEPARATOR . $object);
                    } else {
                        unlink($dir . DIRECTORY_SEPARATOR . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
}