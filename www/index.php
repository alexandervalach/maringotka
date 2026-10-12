<?php

declare(strict_types=1);

$configurator = require dirname(__DIR__) . '/app/bootstrap.php';
$container = $configurator->createContainer();
$container->getByType(Nette\Application\Application::class)->run();
