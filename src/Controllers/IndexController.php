<?php

namespace App\Controllers;

use App\Tools\Converter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class IndexController extends ControllerAbstract
{
    public function index(Request $request, Response $response): Response
    {
        return self::render($response, 'index', [
            'title'     => Converter::NAME,
            'converter' => [
                'image' => Converter::PUBLIC_IMAGE_NAME,
                'slug'  => Converter::SLUG
            ]
        ]);
    }
}
