<?php

namespace App\Controllers;

use App\File\App\Defaults\Dockerfile;
use App\File\Repository\RepositoryYaml;
use App\Tools\{App, Bashio, Converter, Crane, Remover};
use Exception;
use Psr\Http\Message\{ResponseInterface as Response, ServerRequestInterface as Request};
use RuntimeException;

class AppController extends ControllerAbstract
{
    public static function list(Request $request, Response $response): Response
    {
        return self::success($response, [
            'apps'       => App::list(),
            'repository' => RepositoryYaml::instance()->getData()
        ]);
    }

    public static function convert(Request $request, Response $response, string $slug, string $tag): Response
    {
        if (empty($slug)) {
            return self::errorMessage($response, new RuntimeException('Slug should not be empty'));
        }
        try {
            $app = null;
            if ($slug === Converter::SLUG) {
                // haos-app-converter exists
                $app = App::get($slug)->update(Converter::selfConvert($tag));
            } else {
                // any other existing app
                $app = App::get($slug);
            }
            $app->configYaml->increaseVersion($app->metadataJson->version_fixation);
            return self::success($response, [
                'status' => 'success',
                'path'   => realpath($app->getAppDir())
            ]);
        } catch (Exception $e) {
            return self::errorMessage($response, $e);
        }
    }

    public static function get(Request $request, Response $response, string $slug): Response
    {
        try {
            return self::success($response, App::get($slug)->getData());
        } catch (Exception $e) {
            return self::errorMessage($response, $e);
        }
    }

    public static function getBashioVersions(Request $request, Response $response): Response
    {
        return self::success($response, Bashio::getVersions());
    }

    public static function updateMetadata(Request $request, Response $response, string $slug): Response
    {
        try {
            $body = (string)$request->getBody();
            $data = json_decode($body, true) ?? [];
            $metadataJson = App::get($slug)->metadataJson;
            foreach ($data as $key => $value) {
                $metadataJson->{$key} = $value;
            }
            $metadataJson->saveFileContent();

            App::get($slug)->mergeVersion();
            return self::success($response, [
                'status' => 'success',
                'slug'   => $slug,
                'saved'  => $data
            ]);
        } catch (Exception $e) {
            return self::errorMessage($response, $e);
        }
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
        try {
            $data = json_decode((string)$request->getBody(), true);
            $app = App::detectOrCrate($data)?->update($data)->save();
            return self::success($response, [
                'status' => 'success',
                'path'   => realpath($app->getAppDir())
            ]);
        } catch (Exception $e) {
            return self::errorMessage($response, $e);
        }
    }

    public static function checkImageUpdate(Request $request, Response $response, string $slug): Response
    {
        $dataDir = App::getDataDir();
        try {
            $image = App::get($slug)->dockerfile->image;
            $tag = App::get($slug)->dockerfile->image_tag;
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
        $crane = new Crane($fullImage);
        $updates = $crane->getPossibleUpdates($force);
        $result = array_merge($result, $updates);

        $architectures = $crane->getArchitectures();
        $result['architectures'] = [];
        foreach ($architectures as $arch) {
            $result['architectures'][] = [
                'name' => $arch,
                'lts'  => in_array($arch, Dockerfile::ARCHITECTURES_SUPPORTED_LONGTERM)
            ];
        }

        if (!is_dir(dirname($cacheFile))) {
            mkdir(dirname($cacheFile), 0777, true);
        }
        file_put_contents($cacheFile, json_encode($result));
        return self::success($response, $result);
    }
}
