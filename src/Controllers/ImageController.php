<?php

namespace App\Controllers;

use App\Tools\Crane;
use App\Tools\Scripts;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ImageController extends ControllerAbstract
{

    public static function getImageTags(Request $request, Response $response, string $image = ''): Response
    {

        if (empty($image)) {
            return self::success($response);
        }
        $tags = Crane::getTags($image);

        if (empty($tags)) {
            return self::success($response, ['latest']);
        }

        // Tags nach Version sortieren (neueste oben)
        usort($tags, function ($a, $b) {
            if ($a === 'latest') return -1;
            if ($b === 'latest') return 1;

            // Handle versions like "1.2.3" vs. "1.2"
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
        return self::success($response, array_values($tags));
    }

    public static function detectPackageManager(Request $request, Response $response, string $image = '', string $tag = ''): Response
    {

        if (empty($image)) {
            return self::success($response, ['pm' => 'unknown']);
        }

        $fullImage = $image . ($tag ? ':' . $tag : '');
        $cache = Scripts::getDetectPMCache();

        if (isset($cache[$fullImage])) {
            return self::success($response, [
                'pm'     => $cache[$fullImage],
                'cached' => true
            ]);
        }

        // Cache speichern
        $pm = Scripts::detectPM($fullImage);
        $cache[$fullImage] = $pm;
        Scripts::setDetectPmCache($cache);
        return self::success($response, ['pm' => $pm]);
    }
}