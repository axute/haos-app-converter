<?php

namespace App\Controllers;

use App\File\Repository\RepositoryYaml;
use App\Tools\Converter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SettingsController extends ControllerAbstract
{

    public static function get(Request $request, Response $response): Response
    {
        return self::success($response, RepositoryYaml::instance()->getData());
    }

    public static function update(Request $request, Response $response): Response
    {
        $body = (string)$request->getBody();
        $data = json_decode($body, true);
        $repositoryYaml = RepositoryYaml::instance();
        $repositoryYaml->name = $data['name'] ?? Converter::DEFAULT_REPOSITORY_NAME;
        $repositoryYaml->url = $data['url'] ?? null;
        $repositoryYaml->maintainer = $data['maintainer'] ?? Converter::NAME;
        $repositoryYaml->saveFileContent();
        return self::success($response, ['status' => 'success']);
    }
}
