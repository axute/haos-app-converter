<?php
/**
 * Installationsscript für HAOS App Converter
 * Kopiert Vendor-Assets in den public-Ordner
 */

require_once __DIR__ . '/vendor/autoload.php';

if (class_exists(\App\Tools\AssetPublisher::class)) {
    echo "Publishing assets...\n";
    \App\Tools\AssetPublisher::publish(true);
    echo "Done.\n";
} else {
    echo "Error: AssetPublisher class not found. Did you run composer install?\n";
    exit(1);
}
