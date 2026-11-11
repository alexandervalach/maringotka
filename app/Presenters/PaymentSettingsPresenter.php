<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\BookingPaymentRepository;
use App\Forms\PaymentSettingsFormFactory;
use App\Helpers\PaymentEmailTemplate;
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
  public function __construct(
    private readonly BookingPaymentRepository $payments,
    private readonly PaymentSettingsFormFactory $forms,
  )
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
    $this->template->paymentSettings = $this->payments->settings();
    $template = $this->payments->emailTemplate() ?? PaymentEmailTemplate::defaults();
    $sample = PaymentEmailTemplate::content(
      1,
      'Fiktivny prijemca',
      'SK0000000000000000000000',
      null,
      12345,
      '2099000001',
      $template,
    );
    // Preview uses a deliberately fake IBAN and never contacts SMTP or by square.
    $this->template->emailPreviewSubject = $sample['subject'];
    $this->template->emailPreviewHtml = PaymentEmailTemplate::htmlDocument(
      $sample['html'],
      $sample['show_logo'],
      true,
      '/assets/images/logo.png',
    );
  }

  /**
   * Admin-editable payment email text and branding.
   *
   * @return Form
   */
  protected function createComponentEmailTemplateForm(): Form
  {
    $template = $this->payments->emailTemplate() ?? PaymentEmailTemplate::defaults();
    $form = $this->forms->createEmailTemplate($template);
    $form->onSuccess[] = function (Form $form): void {
      $restore = $form->getComponent('restore');
      if ($restore instanceof \Nette\Forms\Controls\SubmitButton && $restore->isSubmittedBy()) {
        $this->payments->resetEmailTemplate();
        $this->flashMessage('Predvolená e-mailová šablóna bola obnovená.', 'success');
        $this->redirect('this');
      }
      $values = $form->getValues('array');
      try {
        $this->payments->saveEmailTemplate(
          (string) $values['subject'],
          (string) $values['body'],
          (bool) $values['show_logo'],
        );
      } catch (InvalidArgumentException $error) {
        $form->addError($error->getMessage());
        return;
      }
      $this->flashMessage('E-mailová šablóna bola uložená.', 'success');
      $this->redirect('this');
    };
    return $form;
  }

  /**
   * Builds a protected form that does not prefill imaginary account details.
   *
   * @return Form Payment-settings form.
   */
  protected function createComponentSettingsForm(): Form
  {
    $form = $this->forms->createSettings($this->payments->settings());
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
