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
    $form = new Form();

    $en = $locale === 'en';
    $form->addText('arrival_date', ($en ? 'Arrival' : 'Príchod'))
      ->setRequired('Vyberte dátum príchodu.')
      ->setHtmlType('date');
    $form->addText('departure_date', ($en ? 'Departure' : 'Odchod'))
      ->setRequired('Vyberte dátum odchodu.')
      ->setHtmlType('date');
    // Extras are optional. Prices are always recalculated on the server.
    $form->addCheckbox('wellness', $en ? 'Wellness (per night)' : 'Wellness (za noc)');
    $form->addCheckbox('breakfast', $en ? 'Breakfast basket (per night)' : 'Raňajkový kôš (za noc)');
    $form->addCheckbox('romance', $en ? 'Romantic surprise (once)' : 'Romantické prekvapenie (jednorazovo)');
    $form->addInteger('frizzante', $en ? 'Frizzante bottles' : 'Frizzante – počet fliaš')
      ->setDefaultValue(0)
      ->addRule(Form::Range, $en ? 'Choose 0 to 20 bottles.' : 'Zvoľte 0 až 20 fliaš.', [0, 20])
      ->setHtmlAttribute('min', 0)
      ->setHtmlAttribute('max', 20);
    $form->addInteger('canvas_bag', $en ? 'Canvas bags' : 'Plátené tašky – počet kusov')
      ->setDefaultValue(0)
      ->addRule(Form::Range, $en ? 'Choose 0 to 20 bags.' : 'Zvoľte 0 až 20 tašiek.', [0, 20])
      ->setHtmlAttribute('min', 0)
      ->setHtmlAttribute('max', 20);
    $form->addText('guest_name', ($en ? 'Full name' : 'Meno a priezvisko'))
      ->setRequired('Zadajte meno.')
      ->setMaxLength(200);
    $form->addEmail('guest_email', ($en ? 'Email' : 'E-mail'))
      ->setRequired('Zadajte e-mail.')
      ->setMaxLength(254);
    $form->addText('guest_phone', ($en ? 'Phone (optional)' : 'Telefón (nepovinné)'))
      ->setMaxLength(60)
      ->setHtmlType('tel');
    $form->addTextArea('guest_note', ($en ? 'Note (optional)' : 'Poznámka (nepovinné)'))
      ->setMaxLength(5000);
    $form->addSubmit('send', ($en ? 'Send request' : 'Odoslať žiadosť'))
      ->setHtmlAttribute('class', 'button');
    return $form;
  }
}
