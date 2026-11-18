<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\VoucherCampaignFormFactory;
use App\Forms\GiftVoucherFormFactory;
use App\Infrastructure\Database\ConnectionFactory;
use App\Model\GiftVoucherRepository;
use App\Model\GiftVoucherOrderRepository;
use App\Components\Voucher\VoucherPdf;
use App\Model\VoucherCampaignRepository;
use InvalidArgumentException;
use DomainException;
use Nette\Application\UI\Form;
use Nette\Application\UI\Multiplier;

/**
 * Authenticated administrator interface for voucher campaigns only.
 */
final class VoucherCampaignPresenter extends BasePresenter
{
  private readonly GiftVoucherRepository $vouchers;
  private readonly GiftVoucherOrderRepository $voucherOrders;
  private readonly GiftVoucherFormFactory $voucherForms;
  private readonly VoucherPdf $voucherPdf;

  public function __construct(
    private readonly VoucherCampaignRepository $campaigns,
    private readonly VoucherCampaignFormFactory $forms,
    ConnectionFactory $connections,
  ) {
    parent::__construct();
    $this->vouchers = new GiftVoucherRepository($connections);
    $this->voucherOrders = new GiftVoucherOrderRepository($connections);
    $this->voucherForms = new GiftVoucherFormFactory();
    $this->voucherPdf = new VoucherPdf();
  }

  protected function startup(): void
  {
    parent::startup();
    $this->requireAdmin();
  }

  /**
   * @return void
   */
  public function renderDefault(): void
  {
    $campaigns = $this->campaigns->all();
    foreach ($campaigns as &$campaign) {
      $campaign['price_display'] = VoucherCampaignRepository::euros((int) $campaign['price_cents']);
      $campaign['is_active'] = in_array($campaign['is_active'], [true, 1, '1', 't', 'true'], true);
    }
    unset($campaign);
    $this->template->add('campaigns', $campaigns);
  }

  /**
   * Display a new campaign or edit an existing one.
   */
  public function actionEdit(?int $id = null): void
  {
    if ($id === null) {
      if ($this->getHttpRequest()->isMethod('GET')) {
        /** @var Form $form */
        $form = $this->getComponent('campaignForm');
        $form->setDefaults([
          'price' => '130',
          'nights' => 1,
          'validity_months' => 6,
          'includes_wellness' => true,
          'includes_wine' => true,
          'is_active' => false,
        ]);
      }

      return;
    }

    $campaign = $this->campaigns->find($id);
    if ($campaign === null) {
      $this->error('Kampaň neexistuje.', 404);
    }

    if ($this->getHttpRequest()->isMethod('GET')) {
      /** @var Form $form */
      $form = $this->getComponent('campaignForm');
      $form->setDefaults([
        'name' => $campaign['name'],
        'name_en' => $campaign['name_en'] ?? '',
        'price' => VoucherCampaignRepository::euros((int) $campaign['price_cents']),
        'nights' => (int) $campaign['nights'],
        'validity_months' => (int) $campaign['validity_months'],
        'includes_wellness' => $campaign['includes_wellness'],
        'includes_wine' => $campaign['includes_wine'],
        'is_active' => $campaign['is_active'],
      ]);
    }
  }

  /**
   * Load immutable vouchers issued from this campaign.
   */
  public function renderEdit(?int $id = null): void
  {
    $this->template->add('issuedVouchers', $id === null ? [] : $this->vouchers->forCampaign($id));
    $this->template->add('voucherOrders', $id === null ? [] : $this->voucherOrders->forCampaign($id));
  }

  /**
   * Prefill issuance with the campaign's current commercial parameters.
   */
  public function actionIssue(int $id): void
  {
    $campaign = $this->campaigns->find($id);

    if ($campaign === null) {
      $this->error('Kampaň neexistuje.', 404);
    }

    $this->template->add('campaign', $campaign);

    if ($this->getHttpRequest()->isMethod('GET')) {
      /** @var Form $form */
      $form = $this->getComponent('issueVoucherForm');
      $form->setDefaults([
        'price' => VoucherCampaignRepository::euros((int) $campaign['price_cents']),
        'nights' => (int) $campaign['nights'],
        'validity_months' => (int) $campaign['validity_months'],
        'includes_wellness' => $campaign['includes_wellness'],
        'includes_wine' => $campaign['includes_wine'],
      ]);
    }
  }

  /**
   * Return the same voucher PDF on every subsequent download.
   */
  public function actionDownload(int $id): void
  {
    $voucher = $this->vouchers->find($id);

    if ($voucher === null) {
      $this->error('Poukaz neexistuje.', 404);
    }

    /** @var array<string, mixed> $document */
    $document = $voucher['document'];
    $bytes = $this->voucherPdf->render($document);
    $this->getHttpResponse()->setContentType('application/pdf');
    $this->getHttpResponse()->setHeader('Content-Disposition', 'attachment; filename="poukaz-' . $id . '.pdf"');
    $this->getHttpResponse()->setHeader('Cache-Control', 'no-store, private');
    echo $bytes;
    $this->terminate();
  }

  /**
   * @return Form Admin voucher issuance form.
   */
  protected function createComponentIssueVoucherForm(): Form
  {
    $form = $this->voucherForms->createIssue();

    $form->onSuccess[] = function (Form $form): void {
      $id = (int) $this->getParameter('id');
      $campaign = $this->campaigns->find($id);

      if ($campaign === null) {
        $this->error('Kampaň neexistuje.', 404);
      }

      try {
        $voucherId = $this->vouchers->issue($campaign, $form->getValues('array'));
      } catch (InvalidArgumentException $error) {
        $form->addError($error->getMessage());

        return;
      }

      $this->redirect('download', ['id' => $voucherId]);
    };

    return $form;
  }

  /**
   * @return Form Campaign form.
   */
  protected function createComponentCampaignForm(): Form
  {
    $form = $this->forms->createCampaign();
    $form->onSuccess[] = function (Form $form): void {
      try {
        $values = $form->getValues('array');
        $id = $this->getParameter('id');
        $this->campaigns->save(is_numeric($id) ? (int) $id : null, $values);
      } catch (InvalidArgumentException $error) {
        $form->addError($error->getMessage());
        return;
      }

      $this->flashMessage('Kampaň bola uložená.', 'success');
      $this->redirect('default');
    };

    return $form;
  }

  /**
   * Payment confirmation is explicit, CSRF protected and idempotent.
   *
   * @return Multiplier<Form>
   */
  protected function createComponentConfirmVoucherPaymentForm(): Multiplier
  {
    return new Multiplier(function (string $id): Form {
      $form = $this->forms->createConfirmVoucherPayment();

      $form->onSuccess[] = function () use ($id): void {
        try {
          $this->voucherOrders->confirmPayment((int) $id);
        } catch (DomainException $error) {
          $this->flashMessage($error->getMessage(), 'error');
          $this->redirect('edit', ['id' => $this->getParameter('id')]);
        }

        $this->flashMessage('Platba za poukaz bola potvrdená.', 'success');
        $this->redirect('edit', ['id' => $this->getParameter('id')]);
      };

      return $form;
    });
  }

  /**
   * Individual CSRF-protected visibility button for each campaign.
   *
   * @return Multiplier<Form>
   */
  protected function createComponentToggleCampaignForm(): Multiplier
  {
    return new Multiplier(function (string $id): Form {
      $form = new Form();
      $form->addProtection();
      $form->addSubmit('toggle', '');

      $form->onSuccess[] = function () use ($id): void {
        $campaign = $this->campaigns->find((int) $id);
        if ($campaign === null) {
          $this->error('Kampaň neexistuje.', 404);
        }

        $active = in_array($campaign['is_active'], [true, 1, '1', 't', 'true'], true);
        $this->campaigns->setActive((int) $id, !$active);
        $this->flashMessage($active ? 'Kampaň bola vypnutá.' : 'Kampaň bola aktivovaná.', 'success');
        $this->redirect('default');
      };

      return $form;
    });
  }

}
