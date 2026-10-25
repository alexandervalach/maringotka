<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;

/**
 * Public reservation request form with CSRF protection.
 */
final class BookingFormFactory extends FormFactory
{
  /**
   * @param string $locale Language of visible labels.
   * @return Form Guest reservation request form.
   */
  public function create(string $locale = 'sk'): Form
  {
    $form = parent::create();
    $en = $locale === 'en';
    $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
    $form->addText('arrival_date', ($en ? 'Arrival' : 'Príchod'))
      ->setRequired('Vyberte dátum príchodu.')
      ->setHtmlType('date')
      ->setHtmlAttribute('min', $today);
    $form->addText('departure_date', ($en ? 'Departure' : 'Odchod'))
      ->setRequired('Vyberte dátum odchodu.')
      ->setHtmlType('date')
      ->setHtmlAttribute('min', $today);
    $form->addText('guest_name', ($en ? 'Full name' : 'Meno a priezvisko'))
      ->setRequired('Zadajte meno.')
      ->setMaxLength(200);
    $form->addEmail('guest_email', ($en ? 'Email' : 'E-mail'))
      ->setRequired('Zadajte e-mail.')
      ->setMaxLength(254);
    $form->addText('guest_phone', ($en ? 'Phone (nepovinné)' : 'Telefón (nepovinné)'))
      ->setMaxLength(60)
      ->setHtmlType('tel');
    $form->addTextArea('guest_note', ($en ? 'Note (nepovinné)' : 'Poznámka (nepovinné)'))
      ->setMaxLength(5000);
    $form->addSubmit('send', ($en ? 'Send request' : 'Odoslať žiadosť'))
      ->setHtmlAttribute('class', 'button');
    return $form;
  }
}
