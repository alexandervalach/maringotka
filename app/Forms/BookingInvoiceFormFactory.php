<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;

/**
 * Forms used for supplier settings and exceptional invoice billing overrides.
 */
final class BookingInvoiceFormFactory extends FormFactory
{
  /**
   * @param array<string, mixed> $settings
   */
  public function createSupplierSettings(array $settings): Form
  {
    $form = parent::create();

    $form->addText('supplier_name', 'Dodávateľ – meno alebo obchodné meno')
      ->setRequired('Zadajte názov dodávateľa.')
      ->setMaxLength(200);

    $form->addTextArea('supplier_address', 'Adresa alebo sídlo dodávateľa')
      ->setRequired('Zadajte adresu dodávateľa.')
      ->setMaxLength(500)
      ->setHtmlAttribute('rows', 2);

    $form->addText('supplier_ico', 'IČO dodávateľa')
      ->setRequired('Zadajte IČO.')
      ->setMaxLength(16);

    $form->addText('supplier_dic', 'DIČ dodávateľa (nepovinné)')
      ->setMaxLength(16);

    $form->addSubmit('save', 'Uložiť fakturačné údaje')
      ->setHtmlAttribute('class', 'admin-save-button');

    $form->setDefaults([
      'supplier_name' => $settings['name'] ?? '',
      'supplier_address' => $settings['address'] ?? '',
      'supplier_ico' => $settings['ico'] ?? '',
      'supplier_dic' => $settings['dic'] ?? '',
    ]);

    return $form;
  }

  /**
   * Optional customer override when the guest requests different billing details.
   *
   * @param array<string, string> $customer
   */
  public function createCustomer(array $customer): Form
  {
    $form = parent::create();

    $form->addText('customer_name', 'Odberateľ – meno alebo firma')
      ->setRequired('Zadajte odberateľa.')
      ->setMaxLength(200);

    $form->addTextArea('customer_address', 'Fakturačná adresa (nepovinné)')
      ->setMaxLength(500)
      ->setHtmlAttribute('rows', 2);

    $form->addText('customer_ico', 'IČO odberateľa (firma)')
      ->setMaxLength(16);

    $form->addText('customer_dic', 'DIČ odberateľa (nepovinné)')
      ->setMaxLength(16);

    $form->addSubmit('issue', 'Vystaviť a stiahnuť PDF faktúru')
      ->setHtmlAttribute('class', 'admin-save-button');

    $form->setDefaults($customer);

    return $form;
  }

  /**
   * One-click invoice action using the guest name from the booking.
   */
  public function createQuickIssue(): Form
  {
    $form = parent::create();

    $form->addSubmit('issue', 'Stiahnuť PDF faktúru')
      ->setHtmlAttribute('class', 'admin-save-button');

    return $form;
  }
}
