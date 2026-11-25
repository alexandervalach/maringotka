<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\MailTemplateFormFactory;
use App\Infrastructure\Database\ConnectionFactory;
use App\Model\VoucherMailTemplateRepository;
use App\Components\Mail\VoucherOrderEmailTemplate;
use App\Helpers\PaymentEmailTemplate;
use InvalidArgumentException;
use Nette\Application\UI\Form;

/** Separate administrator section for voucher-specific email templates. */
final class VoucherMailTemplatePresenter extends BasePresenter
{
  private readonly VoucherMailTemplateRepository $templates;
  private readonly MailTemplateFormFactory $forms;

  public function __construct(ConnectionFactory $connections)
  {
    parent::__construct();
    $this->templates = new VoucherMailTemplateRepository($connections);
    $this->forms = new MailTemplateFormFactory();
  }

  protected function startup(): void
  {
    parent::startup();
    $this->requireAdmin();
  }

  public function actionDefault(): void
  {
    $this->redirect('edit', ['event' => 'ordered', 'locale' => 'sk']);
  }

  public function renderEdit(string $event, string $locale): void
  {
    $sample = [
      'customer_name' => 'Testovací zákazník',
      'campaign_name' => 'Víkendový pobyt',
      'reference' => 'DEMO00000000000000000000',
      'amount' => '130,00 EUR',
      'recipient' => 'Testovací príjemca',
      'iban' => 'SK00 0000 0000 0000 0000 0000',
      'variable_symbol' => '60000000001',
    ];
    $template = $this->templates->get($event, $locale);
    $preview = (new VoucherOrderEmailTemplate())->render($event, $locale, $sample, $template);
    $this->template->add('selectedEvent', $event);
    $this->template->add('selectedLocale', $locale);
    $this->template->add('previewSubject', $preview['subject']);
    $this->template->add('previewHtml', PaymentEmailTemplate::htmlDocument(
      PaymentEmailTemplate::renderMarkdown($preview['body']), $template['show_logo'], true,
      '/assets/images/logo.png',
    ));
  }

  protected function createComponentTemplateForm(): Form
  {
    $event = (string) $this->getParameter('event');
    $locale = (string) $this->getParameter('locale');
    $form = $this->forms->createTemplate($this->templates->get($event, $locale));
    $form->onSuccess[] = function (Form $form) use ($event, $locale): void {
      $restore = $form->getComponent('restore');

      if ($restore instanceof \Nette\Forms\Controls\SubmitButton && $restore->isSubmittedBy()) {
        $this->templates->reset($event, $locale);
        $this->flashMessage('Predvolená šablóna bola obnovená.', 'success');
        $this->redirect('this');
      }

      $data = $form->getValues('array');
      try {
        $this->templates->save($event, $locale, (string) $data['subject'],
          (string) $data['body'], (bool) $data['show_logo']);
      } catch (InvalidArgumentException $error) {
        $form->addError($error->getMessage());
        return;
      }

      $this->flashMessage('Šablóna bola uložená.', 'success');
      $this->redirect('this');
    };

    return $form;
  }
}
