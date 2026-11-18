<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;

/**
 * Public customer form for ordering one advertised voucher campaign.
 */
final class VoucherOrderFormFactory extends FormFactory
{
  /**
   * @return Form CSRF-protected voucher request form.
   */
  public function createOrder(string $locale): Form
  {
    $en = $locale === 'en';
    $form = parent::create();
    $form->addText('customer_name', $en ? 'Full name' : 'Meno a priezvisko')
      ->setRequired($en ? 'Enter your name.' : 'Zadajte meno.')
      ->setMaxLength(200);
    $form->addEmail('customer_email', 'E-mail')
      ->setRequired($en ? 'Enter your email address.' : 'Zadajte e-mail.')
      ->setMaxLength(254);
    $form->addSubmit('send', $en ? 'Order gift voucher' : 'Objednať darčekový poukaz')
      ->setHtmlAttribute('class', 'button');

    return $form;
  }
}
