<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;

/** 
 * Factory for form rendering 
 * */
class FormFactory
{
  /** 
   * @return Form A new CSRF-protected form instance. 
   * */
  public function create(): Form
  {
    $form = new Form();
    $form->addProtection('Platnosť formulára vypršala. Obnovte stránku.');

    return $form;
  }
}
