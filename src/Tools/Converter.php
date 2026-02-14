<?php

namespace App\Tools;

use Symfony\Component\Yaml\Yaml;

class Converter
{
    public const string NAME = 'HAOS App Converter';
    public const string DEFAULT_REPOSITORY_NAME = 'My HAOS App Repository';
    public const string PUBLIC_IMAGE_NAME = 'ghcr.io/axute/haos-app-converter';
    public const string SLUG = 'haos_app_converter';

    public static function getTags(): array
    {
        return Crane::getTags(self::PUBLIC_IMAGE_NAME);
    }

    public static function selfConvert(string $tag): array
    {
        $slug = self::SLUG;
        $configFile = App::get($slug)->dockerfile->getFilePath();
        $currentVersion = '1.0.0';

        if (file_exists($configFile)) {
            $config = Yaml::parseFile($configFile);
            $currentVersion = $config['version'] ?? '1.0.0';
        }

        // Daten für die Generierung vorbereiten
        $data = [
            'name'             => 'HAOS App Converter',
            'image'            => self::PUBLIC_IMAGE_NAME,
            'image_tag'        => $tag,
            'description'      => 'Web-Converter zum Konvertieren von Docker-Images in Home Assistant Apps.',
            'version'          => $currentVersion,
            'url'              => 'https://github.com/axute/haos-app-converter',
            'ingress'          => true,
            'ingress_port'     => 80,
            'ingress_entry'    => '/',
            'timeout'          => 20,
            'watchdog'         => 'http://[HOST]:[PORT:80]/',
            'ingress_stream'   => false,
            'panel_icon'       => 'mdi:toy-brick',
            'panel_title'      => 'App Converter',
            'backup'           => 'hot',
            'self_convert'     => true,
            'version_fixation' => true,
            'map'              => [
                [
                    'folder' => 'addons',
                    'mode'   => 'rw'
                ]
            ],
            'env_vars'         => [
                [
                    'key'   => 'CONVERTER_DATA_DIR',
                    'value' => '/addons'
                ]
            ]
        ];

        // Icon hinzufügen, falls vorhanden
        $iconPath = __DIR__ . '/../../icon.png';
        if (file_exists($iconPath)) {
            $iconData = file_get_contents($iconPath);
            $data['icon_file'] = 'data:image/png;base64,' . base64_encode($iconData);
        }
        return $data;
    }

    public static function writeFileContent(string $filepath, string $content): false|int
    {
        if (is_dir(dirname($filepath)) === false) {
            mkdir(dirname($filepath), 0777, true);
        }
        return file_put_contents($filepath, $content);
    }
}