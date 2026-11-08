<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\BookingPaymentRepository;
use InvalidArgumentException;
use Nette\Application\UI\Form;

/**
 * Admin-only maintenance of the bank account used for manual payments.
 */
final class PaymentSettingsPresenter extends BasePresenter
{
  /**
   * @param BookingPaymentRepository $payments Payment configuration storage.
   */
  public function __construct(private readonly BookingPaymentRepository $payments)
  {
    parent::__construct();
  }

  /**
   * Ensures that public visitors cannot read or change payment settings.
   *
   * @return void
   */
  protected function startup(): void
  {
    parent::startup();
    $this->requireAdmin();
  }

  /**
   * Shows the currently configured recipient bank details.
   *
   * @return void
   */
  public function renderDefault(): void
  {
    $this->template->add('paymentSettings', $this->payments->settings());
  }

  /**
   * Builds a protected form that does not prefill imaginary account details.
   *
   * @return Form Payment-settings form.
   */
  protected function createComponentSettingsForm(): Form
  {
    $settings = $this->payments->settings();
    $form = new Form();
    $form->addProtection('Platnosť formulára vypršala. Obnovte stránku.');
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
    $form->onSuccess[] = function (Form $form): void {
      $values = $form->getValues('array');
      try {
        $this->payments->saveSettings(
          (string) $values['iban'],
          (string) $values['account_holder'],
          (string) $values['bic'],
        );
      } catch (InvalidArgumentException $error) {
        $form->addError($error->getMessage());
        return;
      }
      $this->flashMessage('Platobné údaje boli uložené.', 'success');
      $this->redirect('this');
    };
    return $form;
  }
}
