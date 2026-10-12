<?php

declare(strict_types=1);

use App\Infrastructure\Monitoring\ErrorNotifier;
use Nette\Bootstrap\Configurator;

require dirname(__DIR__) . '/vendor/autoload.php';

$configurator = new Configurator();
$isDevelopment = getenv('APP_ENV') === 'development';
$configurator->setDebugMode($isDevelopment);
$configurator->enableTracy(dirname(__DIR__) . '/var/log');
$configurator->setTempDirectory(dirname(__DIR__) . '/var/temp');
$configurator->addConfig(dirname(__DIR__) . '/config/common.neon');
$configurator->addConfig(dirname(__DIR__) . '/config/translation.neon');

// Production only: Tracy keeps full exception details in protected logs;
// Developer notifier stores a sanitized fingerprint and queues an SMTP alert.
if (!$isDevelopment) {
    ErrorNotifier::attach(
        dirname(__DIR__) . '/var/monitoring',
        getenv('APP_RELEASE') ?: 'unknown',
    );
}

return $configurator;
