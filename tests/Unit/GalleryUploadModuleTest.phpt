<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Forms\GalleryUploadFormFactory;
use App\Infrastructure\Media\GalleryImageProcessor;
use App\Model\GalleryRepository;
use Nette\Application\UI\Form;
use Tester\Assert;

$form = (new GalleryUploadFormFactory())->create();
Assert::type(Form::class, $form);
Assert::true(isset($form['photo']));
Assert::true(isset($form['save']));
Assert::same(32 * 1024 * 1024, GalleryImageProcessor::MAX_BYTES);
Assert::true(method_exists(GalleryRepository::class, 'addUploaded'));

Assert::same([320, 480, 960, 1600], GalleryImageProcessor::variantWidths(2048));
Assert::same([320, 480, 650], GalleryImageProcessor::variantWidths(650));
Assert::same([200], GalleryImageProcessor::variantWidths(200));
Assert::same([320], GalleryImageProcessor::variantWidths(320));
Assert::exception(static fn () => GalleryImageProcessor::variantWidths(0), InvalidArgumentException::class);
