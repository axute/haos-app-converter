<?php

use App\Controllers\AppController;
use App\Controllers\ConverterController;
use App\Controllers\FragmentController;
use App\Controllers\ImageController;
use App\Controllers\IndexController;
use App\Controllers\SettingsController;
use App\Tools\Logger;
use Psr\Http\Message\RequestInterface;
use Slim\Factory\AppFactory;
use Slim\Handlers\Strategies\RequestResponseArgs;
use Slim\Routing\RouteCollectorProxy;

require_once __DIR__ . '/../setup.php';

$app = AppFactory::create();

$app->addRoutingMiddleware();
$routeCollector = $app->getRouteCollector();
$routeCollector->setDefaultInvocationStrategy(new RequestResponseArgs());
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler(function (RequestInterface $request, $exception, $displayErrorDetails, $logErrors, $logErrorDetails) use ($app) {
    Logger::error("Slim Middleware Caught Exception: [{$request->getMethod()}]{$request->getUri()}", $exception);
    $response = $app->getResponseFactory()->createResponse();
    $data = [
        'status' => 'error',
        'message' => "[{$request->getMethod()}]{$request->getUri()}: ".$exception->getMessage(),
    ];
    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
});

// Hauptseite - Converter Interface
$app->get('/', [
    IndexController::class,
    'index'
]);

// htmx Fragmente
$app->group('/fragments', function (RouteCollectorProxy $group) {
    $group->get('/app-list', FragmentController::appList(...));
    $group->get('/app-details/{slug}', FragmentController::appDetails(...));
    $group->get('/check-update/{slug}', FragmentController::checkUpdate(...));
    $group->get('/logs', FragmentController::logs(...));
});

$app->group('/apps', function (RouteCollectorProxy $group) {
    $group->get('/list',  AppController::list(...));
    $group->get('/{slug}/icon.png', function ($request, $response, string $slug) {
        $dataDir = getenv('CONVERTER_DATA_DIR') ?: __DIR__ . '/../data';
        $iconFile = $dataDir . '/' . $slug . '/icon.png';
        if (file_exists($iconFile)) {
            $response->getBody()->write(file_get_contents($iconFile));
            return $response->withHeader('Content-Type', 'image/png');
        }
        return $response->withStatus(404);
    });
    $group->get('/{slug}',  AppController::get(...));
    $group->delete('/{slug}',  AppController::delete(...));
    $group->post('/generate', AppController::generate(...));
    $group->post('/upload', AppController::upload(...));
    $group->get('/{slug}/download', AppController::download(...));
    $group->get('/{slug}/convert/{tag}', AppController::convert(...));
    $group->post('/{slug}/metadata', AppController::updateMetadata(...));
});

$app->get('/logo.png', function ($request, $response) {
    $logoFile = __DIR__ . '/../icon.png';
    if (file_exists($logoFile)) {
        $response->getBody()->write(file_get_contents($logoFile));
        return $response->withHeader('Content-Type', 'image/png');
    }
    return $response->withStatus(404);
});

$app->group('/converter', function (RouteCollectorProxy $group) {
    $group->get('/tags', ConverterController::getTags(...));
});

$app->group('/image', function (RouteCollectorProxy $group) {
    $group->get('/{image:.+}/tags', ImageController::getImageTags(...));
    $group->get('/{image:.+}/env-vars/{tag}', ImageController::getImageEnvVars(...));
    $group->get('/{image:.+}/ports/{tag}', ImageController::getImagePorts(...));
    $group->get('/{image:.+}/pm/{tag}', ImageController::detectPackageManager(...));
});

$app->get('/bashio-versions', AppController::getBashioVersions(...));


// Repository Einstellungen
$app->get('/settings', SettingsController::get(...));
$app->post('/settings', SettingsController::update(...));

$app->run();
