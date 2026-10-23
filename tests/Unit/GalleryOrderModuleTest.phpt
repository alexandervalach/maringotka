<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Forms\GalleryOrderFormFactory;
use App\Model\GalleryRepository;
use Nette\Application\UI\Form;
use Tester\Assert;

$form = (new GalleryOrderFormFactory())->create();
Assert::type(Form::class, $form);
Assert::true(isset($form['ordered_ids']));
Assert::true(isset($form['save']));
Assert::true(method_exists(GalleryRepository::class, 'reorder'));
