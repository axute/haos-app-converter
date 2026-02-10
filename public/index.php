<?php

use App\Controllers\AppController;
use App\Controllers\ConverterController;
use App\Controllers\FragmentController;
use App\Controllers\ImageController;
use App\Controllers\IndexController;
use App\Controllers\SettingsController;
use Slim\Factory\AppFactory;
use Slim\Handlers\Strategies\RequestResponseArgs;
use Slim\Routing\RouteCollectorProxy;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

$app->addRoutingMiddleware();
$routeCollector = $app->getRouteCollector();
$routeCollector->setDefaultInvocationStrategy(new RequestResponseArgs());
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

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
    $group->get('/{slug}/convert/{tag}', AppController::convert(...));
});

$app->group('/converter', function (RouteCollectorProxy $group) {
    $group->get('/tags', ConverterController::getTags(...));
});

$app->group('/image', function (RouteCollectorProxy $group) {
    $group->get('/{image:.+}/tags', ImageController::getImageTags(...));
    $group->get('/{image:.+}/pm/{tag}', ImageController::detectPackageManager(...));
});

$app->get('/bashio-versions', AppController::getBashioVersions(...));


// Repository Einstellungen
$app->get('/settings', SettingsController::get(...));
$app->post('/settings', SettingsController::update(...));

$app->run();
