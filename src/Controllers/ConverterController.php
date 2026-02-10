<?php

namespace App\Controllers;

use App\Tools\Converter;
use App\Tools\Version;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ConverterController extends ControllerAbstract
{

    public static function getTags(Request $request, Response $response): Response
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
        return ControllerAbstract::success($response, array_values($tags));
    }
}