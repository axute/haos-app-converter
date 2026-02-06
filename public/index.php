<?php

use App\Controllers\AddonController;
use App\Controllers\GenerateController;
use App\Controllers\IndexController;
use App\Controllers\SettingsController;
use App\Controllers\FragmentController;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// Hauptseite - Converter Interface
$app->get('/', [IndexController::class, 'index']);

// htmx Fragmente
$app->group('/fragments', function ($group) {
    $group->get('/addon-list', [FragmentController::class, 'addonList']);
    $group->get('/addon-details/{slug}', [FragmentController::class, 'addonDetails']);
    $group->get('/check-update/{slug}', [FragmentController::class, 'checkUpdate']);
});

// Liste der Add-ons
$app->get('/addons', [AddonController::class, 'list']);

// Details eines Add-ons
$app->get('/addons/{slug}', [AddonController::class, 'get']);

// Icon eines Add-ons ausliefern
$app->get('/addons/{slug}/icon.png', function ($request, $response, $args) {
    $slug = $args['slug'];
    $dataDir = getenv('CONVERTER_DATA_DIR') ?: __DIR__ . '/../data';
    $iconFile = $dataDir . '/' . $slug . '/icon.png';
    if (file_exists($iconFile)) {
        $response->getBody()->write(file_get_contents($iconFile));
        return $response->withHeader('Content-Type', 'image/png');
    }
    return $response->withStatus(404);
});

// Add-on löschen
$app->delete('/addons/{slug}', [AddonController::class, 'delete']);

// Selbst-Konvertierung
$app->get('/tags', [AddonController::class, 'getTags']);
$app->get('/image-tags', [AddonController::class, 'getImageTags']);
$app->get('/detect-pm', [AddonController::class, 'detectPackageManager']);
$app->get('/bashio-versions', [AddonController::class, 'getBashioVersions']);
$app->post('/self-convert', [AddonController::class, 'selfConvert']);

// Add-on Generierung
$app->post('/generate', [GenerateController::class, 'generate']);

// Repository Einstellungen
$app->get('/settings', [SettingsController::class, 'get']);
$app->post('/settings', [SettingsController::class, 'update']);

$app->run();
