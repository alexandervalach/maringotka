<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;

/** 
 * The Homepage form fields and validation 
 */
final class HomepageFormFactory extends FormFactory
{
  /** 
   * @return Form CSRF-protected bilingual homepage editor. 
   */
  public function create(): Form
  {
    $form = parent::create();
    $form->addText('title', 'Nadpis')
      ->setRequired('Zadajte nadpis.')
      ->setMaxLength(200);
    $form->addTextArea('summary', 'Úvodný text')
      ->setMaxLength(4000);
    $form->addSubmit('save', 'Uložiť zmeny')
      ->setHtmlAttribute('class', 'button');

    return $form;
  }
}
