<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;

/** Defines the reusable administrator editor for transactional emails. */
final class MailTemplateFormFactory extends FormFactory
{
  /**
   * @param array{subject: string, body: string, show_logo: bool} $template
   */
  public function createTemplate(array $template): Form
  {
    $form = parent::create();
    $form->addText('subject', 'Predmet e-mailu')
      ->setRequired('Zadajte predmet.')
      ->setMaxLength(180);
    $form->addTextArea('body', 'Text e-mailu (Markdown)')
      ->setRequired('Zadajte text e-mailu.')
      ->setHtmlAttribute('rows', 16);
    $form->addCheckbox('show_logo', 'Priložiť logo pod text e-mailu');
    $form->addSubmit('save', 'Uložiť šablónu')->setHtmlAttribute('class', 'admin-save-button');
    $form->addSubmit('restore', 'Obnoviť predvolenú šablónu')
      ->setHtmlAttribute('class', 'button')
      ->setValidationScope([]);
    $form->setDefaults($template);
    return $form;
  }
}
