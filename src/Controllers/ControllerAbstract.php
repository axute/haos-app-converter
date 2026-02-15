<?php

namespace App\Controllers;

use App\View;
use App\Tools\Logger;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Throwable;

abstract class ControllerAbstract
{
    protected static function render(Response $response, string $template, array $data = []): Response
    {
        $view = new View();
        $html = $view->render($template . '.html.twig', $data);
        $response->getBody()->write($html);
        return $response;
    }
    protected static function success(Response $response, array $result = []): MessageInterface|Response
    {
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json');
    }

    protected static function debug(Response $response, mixed $data = []): MessageInterface|Response {
        $result = ['status'  => 'error',
                   'message' => 'see console for details',
                   'details' => $data,
        ];
        return self::error($response, $result);
    }
    protected static function errorMessage(Response $response, string|Throwable $message, int $status = 400): MessageInterface|Response
    {
        if($message instanceof Throwable) {
            Logger::error("UI Error", $message);
            if(getenv('HAOS_DEBUG') !== null && in_array(getenv('HAOS_DEBUG'), ['true', true, '1',1], true)) {
                $message = (string)$message;
            } else {
                $message = $message->getMessage();
            }
        } else {
            Logger::log("UI Error Message: " . $message, 'ERROR');
        }
        $result = ['status'  => 'error',
                   'message' => $message
        ];
        return self::error($response, $result, $status);
    }

    protected static function error(Response $response, array $result, int $status = 400): MessageInterface|Response
    {
        $response->getBody()->write(json_encode($result));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}