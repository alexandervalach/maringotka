<?php

declare(strict_types=1);

use Nette\Bootstrap\Configurator;

require dirname(__DIR__) . '/vendor/autoload.php';

$configurator = new Configurator();
$configurator->setDebugMode(getenv('APP_ENV') === 'development');
$configurator->enableTracy(dirname(__DIR__) . '/var/log');
$configurator->setTempDirectory(dirname(__DIR__) . '/var/temp');
$configurator->addConfig(dirname(__DIR__) . '/config/common.neon');

return $configurator;
