<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;

/**
 * Forms for the administrative voucher campaign editor.
 */
final class VoucherCampaignFormFactory extends FormFactory
{
  /**
   * @return Form Campaign definition editor.
   */
  public function createCampaign(): Form
  {
    $form = parent::create();
    $form->addText('name', 'Názov kampane')
      ->setRequired('Vyplňte názov kampane.')
      ->setMaxLength(120);
    $form->addText('name_en', 'Názov kampane (EN)')
      ->setMaxLength(120);
    $form->addText('price', 'Cena poukazu (€)')
      ->setRequired('Vyplňte cenu.')
      ->addRule(Form::PATTERN, 'Cena musí byť číslo, napr. 130 alebo 130,50.', '[0-9]{1,5}([.,][0-9]{1,2})?');
    $form->addInteger('nights', 'Počet nocí')
      ->setRequired()
      ->addRule(Form::RANGE, 'Zadajte 1 až 30 nocí.', [1, 30]);
    $form->addInteger('validity_months', 'Platnosť (mesiace)')
      ->setRequired()
      ->addRule(Form::RANGE, 'Zadajte 1 až 36 mesiacov.', [1, 36]);
    $form->addCheckbox('includes_wellness', 'Wellness v cene');
    $form->addCheckbox('includes_wine', 'Fľaša vína v cene');
    $form->addCheckbox('is_active', 'Aktívna kampaň');
    $form->addSubmit('save', 'Uložiť kampaň')->setHtmlAttribute('class', 'button');

    return $form;
  }

}
