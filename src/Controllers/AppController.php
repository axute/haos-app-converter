<?php

namespace App\Controllers;

use App\App\{FilesReader, FilesWriter};
use App\Generator\{HaConfig, HaRepository};
use App\Tools\{Bashio, Converter, Crane, Remover};
use Exception;
use Psr\Http\Message\{ResponseInterface as Response, ServerRequestInterface as Request};
use RuntimeException;

class AppController extends ControllerAbstract
{
    public static function list(Request $request, Response $response): Response
    {
        $dataDir = FilesReader::getDataDir();
        $apps = [];

        if (is_dir($dataDir)) {
            $dirs = array_filter(glob($dataDir . '/*'), 'is_dir');
            foreach ($dirs as $dir) {
                $slug = basename($dir);
                try {
                    $reader = new FilesReader($slug);
                    $apps[] = $reader->jsonSerialize();
                } catch (Exception) {
                    continue;
                }
            }
        }

        // Alphabetisch sortieren nach "name"
        usort($apps, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        $repository = HaRepository::getInstance()?->jsonSerialize();
        return self::success($response, [
            'apps'       => $apps,
            'repository' => $repository
        ]);
    }

    public static function get(Request $request, Response $response, string $slug): Response
    {
        try {
            $reader = new FilesReader($slug);
            return self::success($response, $reader->jsonSerialize());
        } catch (Exception $e) {
            return self::errorMessage($response, $e);
        }
    }

    public static function convert(Request $request, Response $response, string $slug, string $tag): Response
    {
        if (empty($slug)) {
            return self::errorMessage($response, new RuntimeException('Slug should not be empty'));
        }
        try {
            if ($slug === Converter::SLUG) {
                // haos-app-converter exists
                try {
                    $filesReader = new FilesReader($slug);
                    $appData = $filesReader->setImageTag($tag)->jsonSerialize();
                } catch (Exception) {
                    // catch: create new
                    $appData = Converter::selfConvert($tag);
                }
            } else {
                // any other existing app
                $filesReader = new FilesReader($slug);
                $appData = $filesReader->setImageTag($tag)->jsonSerialize();
            }
            $filesWriter = new FilesWriter($appData);
            $filesWriter->increaseVersion();
            $result = $filesWriter->create();
            return self::success($response, $result);
        } catch (Exception $e) {
            return self::errorMessage($response, $e);
        }
    }

    public static function getBashioVersions(Request $request, Response $response): Response
    {
        return self::success($response, Bashio::getVersions());
    }

    public static function delete(Request $request, Response $response, string $slug): Response
    {
        // System app cannot be deleted
        if ($slug === Converter::SLUG) {
            return self::errorMessage($response, 'System app cannot be deleted', 403);
        }
        try {
            Remover::removeApp($slug);
            return self::success($response, ['status' => 'success']);
        } catch (Exception $e) {
            return self::errorMessage($response, $e);
        }
    }

    public static function generate(Request $request, Response $response): Response
    {
        $body = (string)$request->getBody();
        $data = json_decode($body, true);

        try {
            $appWriter = new FilesWriter($data);
            $result = $appWriter->create();
            return self::success($response, $result);
        } catch (Exception $e) {
            return self::errorMessage($response, $e);
        }
    }

    public static function checkImageUpdate(Request $request, Response $response, string $slug): Response
    {
        $dataDir = FilesReader::getDataDir();
        try {
            $app = new FilesReader($slug);
            $image = $app->getImage();
            $tag = $app->getImageTag();
        } catch (Exception $e) {
            return self::errorMessage($response, $e);
        }

        if (empty($image)) {
            return self::errorMessage($response, 'Could not detect image in Dockerfile', 200);
        }

        $fullImage = $image . ':' . $tag;
        $cacheFile = $dataDir . '/.cache/update_check_' . md5($fullImage) . '.json';
        $force = $request->getQueryParams()['force'] ?? false;

        // Cache für 6 Stunden (außer force=1)
        if (!$force && file_exists($cacheFile) && (time() - filemtime($cacheFile) < 21600)) {
            return self::success($response, json_decode(file_get_contents($cacheFile), true));
        }

        $result = [
            'status'      => 'success',
            'has_update'  => false,
            'new_tag'     => null,
            'slug'        => $slug,
            'image'       => $fullImage,
            'current_tag' => $tag
        ];
        $updates = Crane::getUpdateDetailed($image, $tag);
        $result = array_merge($result, $updates);

        $architectures = Crane::getArchitectures($fullImage);
        $result['architectures'] = [];
        foreach ($architectures as $arch) {
            $result['architectures'][] = [
                'name' => $arch,
                'lts'  => in_array($arch, HaConfig::ARCHITECTURES_SUPPORTED_LONGTERM)
            ];
        }

        if (!is_dir(dirname($cacheFile))) {
            mkdir(dirname($cacheFile), 0777, true);
        }
        file_put_contents($cacheFile, json_encode($result));
        return self::success($response, $result);
    }
}
