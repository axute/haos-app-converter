<?php

namespace App\Controllers;

use App\Addon\{FilesReader, FilesWriter};
use App\Generator\{HaConfig, HaRepository};
use App\Tools\{Bashio, Converter, Crane, Remover, Scripts, Version};
use Exception;
use Psr\Http\Message\{ResponseInterface as Response, ServerRequestInterface as Request};
use RuntimeException;

class AddonController extends ControllerAbstract
{
    public function list(Request $request, Response $response): Response
    {
        $dataDir = FilesReader::getDataDir();
        $addons = [];

        if (is_dir($dataDir)) {
            $dirs = array_filter(glob($dataDir . '/*'), 'is_dir');
            foreach ($dirs as $dir) {
                $slug = basename($dir);
                try {
                    $reader = new FilesReader($slug);
                    $addons[] = $reader->jsonSerialize();
                } catch (Exception) {
                    continue;
                }
            }
        }

        // Alphabetisch sortieren nach Name
        usort($addons, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        $repository = HaRepository::getInstance()?->jsonSerialize();
        return $this->success($response, [
            'addons'     => $addons,
            'repository' => $repository
        ]);
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        try {
            $reader = new FilesReader($args['slug']);
            return $this->success($response, $reader->jsonSerialize());
        } catch (Exception $e) {
            return $this->errorMessage($response, $e);
        }
    }

    public function getImageTags(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $image = $queryParams['image'] ?? '';

        if (empty($image)) {
            return $this->success($response);
        }
        $tags = Crane::getTags($image);

        if (empty($tags)) {
            return $this->success($response, ['latest']);
        }

        // Tags nach Version sortieren (neueste oben)
        usort($tags, function ($a, $b) {
            if ($a === 'latest') return -1;
            if ($b === 'latest') return 1;

            // Handle versions like "1.2.3" vs "1.2"
            $a_v = preg_replace('/[^0-9.]/', '', $a);
            $b_v = preg_replace('/[^0-9.]/', '', $b);

            if ($a_v && $b_v && $a_v !== $b_v) {
                return version_compare($b_v, $a_v);
            }

            // Fallback: SHA-Tags ans Ende sortieren
            $a_is_sha = (str_starts_with($a, 'sha256-'));
            $b_is_sha = (str_starts_with($b, 'sha256-'));
            if ($a_is_sha && !$b_is_sha) return -1;
            if (!$a_is_sha && $b_is_sha) return 1;

            return strcasecmp($b, $a);
        });
        return $this->success($response, array_values($tags));
    }

    public function getTags(Request $request, Response $response): Response
    {

        $tags = array_filter(Converter::getTags(), function ($tag) {
            if ($tag === 'latest' || Version::fromSemverTag($tag) !== null) {
                return true;
            }
            return false;
        });
        usort($tags, function ($a, $b) {
            if ($a === 'latest') return -1;
            if ($b === 'latest') return 1;
            return Version::fromSemverTag($a)->compare(Version::fromSemverTag($b)) * -1;
        });
        return $this->success($response, array_values($tags));
    }

    public function detectPackageManager(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $image = $queryParams['image'] ?? '';
        $tag = $queryParams['tag'] ?? 'latest';

        if (empty($image)) {
            return $this->success($response, ['pm' => 'unknown']);
        }

        $fullImage = $image . ($tag ? ':' . $tag : '');
        $cache = Scripts::getDetectPMCache();

        if (isset($cache[$fullImage])) {
            return $this->success($response, [
                'pm'     => $cache[$fullImage],
                'cached' => true
            ]);
        }

        // Cache speichern
        $pm = Scripts::detectPM($fullImage);
        $cache[$fullImage] = $pm;
        Scripts::setDetectPmCache($cache);
        return $this->success($response, ['pm' => $pm]);
    }

    public function selfConvert(Request $request, Response $response): Response
    {
        $body = (string)$request->getBody();
        $params = json_decode($body, true);
        $tag = $params['tag'] ?? 'latest';
        $slug = $params['slug'] ?? null;
        if (empty($slug)) {
            return $this->errorMessage($response, new RuntimeException('Slug should not be empty'));
        }
        try {
            if ($slug === Converter::SLUG) {
                // haos-addon-converter exists
                try {
                    $filesReader = new FilesReader($slug);
                    $addonData = $filesReader->setImageTag($tag)->jsonSerialize();
                } catch (Exception) {
                    // catch: create new
                    $addonData = Converter::selfConvert($tag);
                }
            } else {
                // any other existing addon
                $filesReader = new FilesReader($slug);
                $addonData = $filesReader->setImageTag($tag)->jsonSerialize();
            }
//            return $this->debug($response, $addonData);
            $filesWriter = new FilesWriter($addonData);
            $filesWriter->increaseVersion();
            $result = $filesWriter->create();
            return $this->success($response, $result);
        } catch (Exception $e) {
            return $this->errorMessage($response, $e);
        }
    }

    public function getBashioVersions(Request $request, Response $response): Response
    {
        return $this->success($response, Bashio::getVersions());
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];

        // System addon cannot be deleted
        if ($slug === Converter::SLUG) {
            return $this->errorMessage($response, 'System add-on cannot be deleted', 403);
        }
        try {
            Remover::removeAddon($slug);
            return $this->success($response, ['status' => 'success']);
        } catch (Exception $e) {
            return $this->errorMessage($response, $e);
        }
    }


    public function checkImageUpdate(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];
        $dataDir = FilesReader::getDataDir();
        try {
            $addon = new FilesReader($slug);
            $image = $addon->getImage();
            $tag = $addon->getImageTag();
        } catch (Exception $e) {
            return $this->errorMessage($response, $e);
        }

        if (empty($image)) {
            return $this->errorMessage($response, 'Could not detect image in Dockerfile', 200);
        }

        $fullImage = $image . ':' . $tag;
        $cacheFile = $dataDir . '/.cache/update_check_' . md5($fullImage) . '.json';
        $force = $request->getQueryParams()['force'] ?? false;

        // Cache für 6 Stunden (außer force=1)
        if (!$force && file_exists($cacheFile) && (time() - filemtime($cacheFile) < 21600)) {
            return $this->success($response, json_decode(file_get_contents($cacheFile), true));
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
        return $this->success($response, $result);
    }
}
