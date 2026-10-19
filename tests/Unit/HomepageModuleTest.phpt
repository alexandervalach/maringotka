<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Forms\HomepageFormFactory;
use App\Model\HomepageRepository;
use App\Model\Repository;
use App\Presenters\BasePresenter;
use App\Presenters\HomepagePresenter;
use Nette\Application\UI\Form;
use Tester\Assert;

Assert::true(is_subclass_of(HomepagePresenter::class, BasePresenter::class));
Assert::true(is_subclass_of(HomepageRepository::class, Repository::class));
Assert::true(method_exists(HomepagePresenter::class, 'actionEdit'));
Assert::true(method_exists(HomepagePresenter::class, 'createComponentHomepageForm'));

$form = (new HomepageFormFactory())->create();
Assert::type(Form::class, $form);
foreach (['title', 'summary', 'save'] as $name) {
  Assert::true(isset($form[$name]));
}