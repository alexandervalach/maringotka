<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Forms\FormFactory;
use App\Model\Repository;
use App\Presenters\BasePresenter;
use Nette\Application\UI\Form;
use Nette\Database\Explorer;
use Tester\Assert;

$form = (new FormFactory())->create();
Assert::type(Form::class, $form);

$repository = new ReflectionClass(Repository::class);
Assert::true($repository->isAbstract());
Assert::same(Explorer::class, (string) $repository->getConstructor()?->getParameters()[0]->getType());
Assert::true($repository->hasMethod('findAll'));
Assert::true($repository->hasMethod('findBy'));
Assert::true($repository->hasMethod('findById'));

Assert::same('maringotka_admin_v2', (new ReflectionClass(BasePresenter::class))->getConstant('ADMIN_SESSION_SECTION'));
