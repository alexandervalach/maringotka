<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\VoucherCampaignRepository;
use App\Model\GiftVoucherOrderRepository;
use App\Forms\VoucherOrderFormFactory;
use App\Infrastructure\Database\ConnectionFactory;
use DomainException;
use Nette\Application\UI\Form;
use Contributte\Translation\Translator;

/**
 * Public gift voucher campaigns page.
 */
final class VoucherPresenter extends BasePresenter
{
  private readonly GiftVoucherOrderRepository $orders;
  private readonly VoucherOrderFormFactory $orderForms;
  /**
   * @param Translator $translator Public translations.
   * @param VoucherCampaignRepository $campaigns Available voucher campaigns.
   */
  public function __construct(
    private readonly Translator $translator,
    private readonly VoucherCampaignRepository $campaigns,
    ConnectionFactory $connections,
  ) {
    parent::__construct();
    $this->orders = new GiftVoucherOrderRepository($connections);
    $this->orderForms = new VoucherOrderFormFactory();
  }

  /**
   * Prepare localized page navigation and title.
   */
  protected function beforeRender(): void
  {
    parent::beforeRender();
    $this->configurePublicTemplate($this->translator, $this->getParameter('locale'), 'voucher');
    $this->template->add('title', $this->translator->translate('messages.navigation.voucher'));
  }

  /**
   * Display active voucher campaigns without affecting reservations or CMS.
   */
  public function renderDefault(): void
  {
    $locale = $this->getParameter('locale') === 'en' ? 'en' : 'sk';
    $this->translator->setLocale($locale);
    $campaigns = $this->campaigns->active();

    foreach ($campaigns as &$campaign) {
      $nights = (int) $campaign['nights'];
      $months = (int) $campaign['validity_months'];
      $nameEn = trim((string) ($campaign['name_en'] ?? ''));

      $campaign['display_name'] = $locale === 'en' && $nameEn !== ''
        ? $nameEn
        : (string) $campaign['name'];
      $campaign['price_display'] = VoucherCampaignRepository::euros((int) $campaign['price_cents']);
      $campaign['stay_text'] = $this->localizedCount('stay', $nights, $locale);
      $campaign['validity_text'] = $this->localizedCount('validity', $months, $locale);
    }
    unset($campaign);

    $this->template->add('voucherCampaigns', $campaigns);
  }

  /**
   * Accept orders only for campaigns that are currently publicly available.
   */
  public function actionOrder(int $id): void
  {
    $campaign = $this->campaigns->find($id);

    if ($campaign === null || !in_array($campaign['is_active'], [true, 1, '1', 't', 'true'], true)) {
      $this->error('Poukaz nie je dostupný.', 404);
    }

    $this->template->add('selectedCampaign', $campaign);
    $this->template->add('selectedPrice', VoucherCampaignRepository::euros((int) $campaign['price_cents']));
  }

  /**
   * Display only a public reference, never the customer's contact details.
   */
  public function actionOrdered(string $reference): void
  {
    $confirmation = $this->orders->publicConfirmation($reference);

    if ($confirmation === null) {
      $this->error('Objednávka neexistuje.', 404);
    }

    $this->template->add('orderConfirmation', $confirmation);
  }

  /**
   * @return Form Customer voucher request form.
   */
  protected function createComponentOrderForm(): Form
  {
    $locale = $this->getParameter('locale') === 'en' ? 'en' : 'sk';
    $form = $this->orderForms->createOrder($locale);

    $form->onSuccess[] = function (Form $form) use ($locale): void {
      try {
        $reference = $this->orders->request((int) $this->getParameter('id'), [
          ...$form->getValues('array'),
          'locale' => $locale,
        ]);
      } catch (DomainException $error) {
        $form->addError($error->getMessage());

        return;
      }

      $this->redirect('ordered', ['reference' => $reference, 'locale' => $locale]);
    };

    return $form;
  }

  /**
   * Use correct localized singular, few and plural expressions.
   *
   * @param string $kind Translation prefix for nights or validity.
   * @param int $count Number of nights or months.
   * @param string $locale Active locale.
   * @return string Localized marketing text.
   */
  private function localizedCount(string $kind, int $count, string $locale): string
  {
    $form = 'Many';

    if ($count === 1) {
      $form = 'One';
    } elseif ($locale === 'sk' && $count >= 2 && $count <= 4) {
      $form = 'Few';
    }

    $template = (string) $this->translator->translate('messages.voucher.' . $kind . $form);

    return sprintf($template, $count);
  }
}
