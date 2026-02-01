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
