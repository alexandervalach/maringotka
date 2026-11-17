<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\VoucherCampaignFormFactory;
use App\Model\VoucherCampaignRepository;
use InvalidArgumentException;
use Nette\Application\UI\Form;
use Nette\Application\UI\Multiplier;

/**
 * Authenticated administrator interface for voucher campaigns only.
 */
final class VoucherCampaignPresenter extends BasePresenter
{
  public function __construct(
    private readonly VoucherCampaignRepository $campaigns,
    private readonly VoucherCampaignFormFactory $forms,
  ) {
    parent::__construct();
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
