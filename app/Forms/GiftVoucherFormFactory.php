<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;

/**
 * Form for issuing a voucher with editable defaults from a campaign.
 */
final class GiftVoucherFormFactory extends FormFactory
{
  /**
   * @return Form
   */
  public function createIssue(): Form
  {
    $form = parent::create();

    $form->addText('price', 'Cena poukazu (€)')
      ->setRequired('Vyplňte cenu.')
      ->addRule(Form::PATTERN, 'Neplatná cena.', '[0-9]{1,5}([.,][0-9]{1,2})?');

    $form->addInteger('nights', 'Počet nocí')
      ->setRequired()
      ->addRule(Form::RANGE, 'Zadajte 1 až 30 nocí.', [1, 30]);

    $form->addInteger('validity_months', 'Platnosť (mesiace)')
      ->setRequired()
      ->addRule(Form::RANGE, 'Zadajte 1 až 36 mesiacov.', [1, 36]);

    $form->addCheckbox('includes_wellness', 'Súkromný wellness v cene');
    $form->addCheckbox('includes_wine', 'Fľaša vína v cene');
    $form->addSubmit('issue', 'Vystaviť a stiahnuť PDF')->setHtmlAttribute('class', 'button');

    return $form;
  }
}
