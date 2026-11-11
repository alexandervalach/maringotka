<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;

/**
 * Defines administrative forms for bank account and payment email template.
 * Submission handlers remain in the presenter.
 */
final class PaymentSettingsFormFactory extends FormFactory
{
  /**
   * @param array{iban: string|null, account_holder: string|null, bic: string|null} $settings
   * @return Form
   */
  public function createSettings(array $settings): Form
  {
    $form = parent::create();
    $form->addText('iban', 'IBAN')
      ->setRequired('Zadajte IBAN.')
      ->setMaxLength(70);
    $form->addText('account_holder', 'Príjemca platby')
      ->setRequired('Zadajte príjemcu platby.')
      ->setMaxLength(160);
    $form->addText('bic', 'BIC / SWIFT (nepovinné)')
      ->setMaxLength(11);
    $form->addSubmit('save', 'Uložiť platobné údaje')
      ->setHtmlAttribute('class', 'admin-save-button');
    $form->setDefaults([
      'iban' => $settings['iban'] ?? '',
      'account_holder' => $settings['account_holder'] ?? '',
      'bic' => $settings['bic'] ?? '',
    ]);
    return $form;
  }

  /**
   * @param array{subject: string, body: string, show_logo: bool} $template
   * @return Form
   */
  public function createEmailTemplate(array $template): Form
  {
    $form = parent::create();
    $form->addText('subject', 'Predmet e-mailu')
      ->setRequired('Zadajte predmet.')
      ->setMaxLength(180);
    $form->addTextArea('body', 'Text e-mailu (základný Markdown)')
      ->setRequired('Zadajte text e-mailu.')
      ->setHtmlAttribute('rows', 15);
    $form->addCheckbox('show_logo', 'Priložiť logo Maringotky na Závadke');
    $form->addSubmit('save', 'Uložiť e-mailovú šablónu')
      ->setHtmlAttribute('class', 'admin-save-button');
    $form->addSubmit('restore', 'Obnoviť predvolenú šablónu')
      ->setHtmlAttribute('class', 'button')
      ->setValidationScope([]);
    $form->setDefaults($template);
    return $form;
  }
}
