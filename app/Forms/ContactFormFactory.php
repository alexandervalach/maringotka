<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;

/** 
 * Defines and validates editable, language-independent public contact details. 
 */
final class ContactFormFactory extends FormFactory
{
  /** @return Form CSRF-protected contact settings editor. */
  public function create(): Form
  {
    $form = parent::create();
    $form->addText('phone', 'Telefón')
      ->setRequired('Zadajte telefónne číslo.')
      ->setMaxLength(40)
      ->setHtmlType('tel')
      ->addRule(Form::PATTERN, 'Zadajte platné telefónne číslo.', '\+?[0-9][0-9\s().-]{5,39}');
    $form->addEmail('email', 'E-mail')
      ->setRequired('Zadajte e-mailovú adresu.')
      ->setMaxLength(254);

    foreach ([
      'instagram_url' => 'Instagram (HTTPS)',
      'booking_url' => 'Booking.com (HTTPS)',
      'maps_url' => 'Google Maps (HTTPS)',
      'youtube_url' => 'YouTube (HTTPS)',
    ] as $name => $label) {
      $form->addText($name, $label)
        ->setMaxLength(2048)
        ->setHtmlType('url')
        ->addRule(Form::URL, 'Zadajte platnú URL adresu.')
        ->addRule(Form::PATTERN, 'Odkaz musí používať HTTPS.', 'https://.+');
    }

    $form->addSubmit('save', 'Uložiť zmeny')
      ->setHtmlAttribute('class', 'button');

    return $form;
  }
}
