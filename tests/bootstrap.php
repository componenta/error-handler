<?php

declare(strict_types=1);

$package = dirname(__DIR__);
$packages = dirname($package);
$workspace = dirname($packages);
$localAutoload = $package . '/vendor/autoload.php';
$loader = require is_file($localAutoload) ? $localAutoload : $packages . '/var-export/vendor/autoload.php';
if (!is_file($localAutoload)) {
    foreach (require $workspace . '/vendor/composer/autoload_psr4.php' as $prefix => $paths) {
        $loader->addPsr4($prefix, $paths);
    }
    $loader->setPsr4('Componenta\\Config\\', $packages . '/config/src');
    $loader->setPsr4('Componenta\\DI\\', $packages . '/di/src');
    require_once $packages . '/config/src/functions.php';
    require_once $packages . '/di/src/Internal/functions.php';
}
$loader->setPsr4('Componenta\\Error\\', $package . '/src');
$loader->setPsr4('Componenta\\Error\\Tests\\', __DIR__);
