<?php

namespace App\Tools;

use Exception;

class Bashio
{
    const string REPOSITORY_RELEASES = 'https://api.github.com/repos/hassio-addons/bashio/releases';

    public static function getVersions(): array
    {
        $cacheFile = App::getDataDir() . '/.cache/bashio_versions.json';
        $versions = [];

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
            $versions = json_decode(file_get_contents($cacheFile), true);
        }

        if (empty($versions)) {
            try {
                $opts = [
                    'http' => [
                        'method' => 'GET',
                        'header' => "User-Agent: PHP\r\n"
                    ]
                ];
                $context = stream_context_create($opts);
                $json = @file_get_contents(self::REPOSITORY_RELEASES, false, $context);
                if ($json) {
                    $data = json_decode($json, true);
                    foreach ($data as $release) {
                        $tag = $release['tag_name'];
                        // v entfernen falls vorhanden
                        $versions[] = ltrim($tag, 'v');
                    }

                    if (!is_dir(dirname($cacheFile))) {
                        mkdir(dirname($cacheFile), 0777, true);
                    }
                    file_put_contents($cacheFile, json_encode($versions));
                }
            } catch (Exception) {
                // Fallback
            }
        }

        if (empty($versions)) {
            $versions = [
                '0.16.3',
                '0.14.3'
            ]; // Minimaler Fallback
        }
        return $versions;
    }
}