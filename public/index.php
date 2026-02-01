<?php

use App\Controllers\AddonController;
use App\Controllers\GenerateController;
use App\Controllers\IndexController;
use App\Controllers\SettingsController;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// Hauptseite - Wizard Interface
$app->get('/', [IndexController::class, 'index']);

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
$app->post('/self-convert', [AddonController::class, 'selfConvert']);

// Add-on Generierung
$app->post('/generate', [GenerateController::class, 'generate']);

// Repository Einstellungen
$app->get('/settings', [SettingsController::class, 'get']);
$app->post('/settings', [SettingsController::class, 'update']);

$app->run();
