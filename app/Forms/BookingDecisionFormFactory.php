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
   * @param string $status Current reservation status.
   * @return Form Decision form with CSRF protection.
   */
  public function create(string $status = 'pending'): Form
  {
    $form = parent::create();
    $decisions = $status === 'confirmed'
      ? ['cancelled' => 'Zrušiť rezerváciu']
      : [
        'rejected' => 'Zamietnuť',
        'cancelled' => 'Zrušiť rezerváciu',
      ];

    if ($status === 'pending') {
      $form->addSubmit('confirm', 'Potvrdiť rezerváciu')
        ->setHtmlAttribute('class', 'admin-save-button')
        ->setValidationScope([]);
    }

    $form->addRadioList('decision', 'Zamietnutie alebo zrušenie', $decisions)
      ->setRequired('Vyberte zamietnutie alebo zrušenie.');

    $form->addTextArea('reason', 'Dôvod zamietnutia alebo zrušenia')
      ->setMaxLength(2000)
      ->setHtmlAttribute('rows', 4);
    $form->addSubmit('save', 'Uložiť zmeny')
      ->setHtmlAttribute('class', 'button');
    return $form;
  }

  /**
   * Creates a form for an immediate manual payment-status change.
   *
   * @return Form Form with two explicit administrator actions.
   */
  public function createPaymentStatus(): Form
  {
    $form = parent::create();

    $form->addSubmit('markPaid', 'Označiť ako zaplatenú')
      ->setHtmlAttribute('class', 'admin-save-button');

    $form->addSubmit('markUnpaid', 'Označiť ako nezaplatenú')
      ->setHtmlAttribute('class', 'button');

    return $form;
  }

}
