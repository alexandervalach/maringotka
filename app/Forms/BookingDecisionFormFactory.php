<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;

/**
 * Administrator decision form for one reservation.
 */
final class BookingDecisionFormFactory extends FormFactory
{
  /**
   * @return Form Decision form with CSRF protection.
   */
  public function create(): Form
  {
    $form = parent::create();
    $form->addRadioList('decision', 'Rozhodnutie', [
      'confirmed' => 'Potvrdiť',
      'rejected' => 'Zamietnuť',
    ])->setRequired('Vyberte rozhodnutie.');
    $form->addTextArea('reason', 'Dôvod zamietnutia')
      ->setMaxLength(2000)
      ->setHtmlAttribute('rows', 4);
    $form->addSubmit('save', 'Uložiť zmeny')
      ->setHtmlAttribute('class', 'button');
    return $form;
  }
}
