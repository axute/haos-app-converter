<?php

namespace App\Controllers;

use App\App\FilesReader;
use App\Generator\HaRepository;
use App\Tools\Converter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SettingsController extends ControllerAbstract
{

    public static function get(Request $request, Response $response): Response
    {
        $repoFile = FilesReader::getDataDir() . '/' . HaRepository::FILENAME;
        $haRepository = new HaRepository();

        if (file_exists($repoFile)) {
            $existing = HaRepository::fromFile($repoFile);
            $haRepository->setName($existing->getName());
            $haRepository->setMaintainer($existing->getMaintainer());
            $haRepository->setUrl($existing->getUrl());
        }
        return self::success($response, $haRepository->jsonSerialize());
    }

    public static function update(Request $request, Response $response): Response
    {
        $body = (string)$request->getBody();
        $data = json_decode($body, true);

        $name = $data['name'] ?? Converter::DEFAULT_REPOSITORY_NAME;
        $maintainer = $data['maintainer'] ?? Converter::NAME;
        $url = $data['url'] ?? null;

        $dataDir = FilesReader::getDataDir();
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0777, true);
        }

        $repoFile = $dataDir . '/' . HaRepository::FILENAME;
        $haRepository = new HaRepository($name);
        $haRepository->setMaintainer($maintainer);
        if (!empty($url)) {
            $haRepository->setUrl($url);
        }

        file_put_contents($repoFile, $haRepository);
        return self::success($response, ['status' => 'success']);
    }
}
