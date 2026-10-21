<?php

declare(strict_types=1);

use App\Model\GalleryRepository;
use Nette\DI\Container;

$configurator = require dirname(__DIR__) . '/app/bootstrap.php';
/** @var Container $container */
$container = $configurator->createContainer();
/** @var GalleryRepository $gallery */
$gallery = $container->getByType(GalleryRepository::class);
$created = $gallery->importExisting();
echo "Imported {$created} gallery photos.\n";
