<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\PricingFormFactory;
use App\Localization\SupportedLocales;
use App\Model\PricingRepository;
use App\Router\PublicRoutes;
use Contributte\Translation\Translator;
use Nette\Application\UI\Form;

/** 
 * Public pricing and authenticated editing handled by one presenter. 
 */
final class PricingPresenter extends BasePresenter
{
  /**
   * @param Translator $translator Existing translated UI strings.
   * @param PricingRepository $pricing Prices in PostgreSQL.
   * @param PricingFormFactory $forms Pricing-specific form factory.
   */
  public function __construct(
    private readonly Translator $translator,
    private readonly PricingRepository $pricing,
    private readonly PricingFormFactory $forms,
  ) {
    parent::__construct();
  }

  protected function startup(): void
  {
    parent::startup();
    if ($this->getAction() === 'edit') {
      $this->requireAdmin();
    }
  }

  /** 
   * @param string $locale Current public language. 
   */
  public function actionDefault(string $locale): void
  {
    if (!SupportedLocales::isSupported($locale)) {
      $this->error('Unknown language.', 404);
    }
  }

  /** 
   * Pre-fill only on GET to preserve submitted values on validation errors. 
   */
  public function actionEdit(): void
  {
    if ($this->getHttpRequest()->isMethod('GET')) {
      /** @var Form $form */
      $form = $this->getComponent('pricingForm');
      $form->setDefaults($this->pricing->getEditorValues());
    }
  }

  protected function beforeRender(): void
  {
    parent::beforeRender();
    if ($this->getAction() !== 'default') {
      return;
    }
    $locale = $this->getParameter('locale');
    if (!is_string($locale) || !SupportedLocales::isSupported($locale)) {
      $this->error('Unknown language.', 404);
    }
    $this->translator->setLocale($locale);
    $this->template->setTranslator($this->translator, $locale);
    $this->template->add('locale', $locale);
    $this->template->add('paths', PublicRoutes::forLocale($locale));
    $this->template->add('languageUrls', PublicRoutes::languageUrlsFor('pricing'));
    $this->template->add('title', $this->translator->translate('messages.navigation.pricing'));
  }

  /** 
   * Public prices, shared across languages. 
   */
  public function renderDefault(): void
  {
    $this->template->add('prices', $this->pricing->getDisplayPrices());
  }

  /** 
   * @return Form Pricing editor with an atomic save. 
   */
  protected function createComponentPricingForm(): Form
  {
    $form = $this->forms->create();
    $form->onSuccess[] = function (Form $form): void {
      $values = $form->getValues('array');
      $this->pricing->save([
        'first_night' => (string) $values['first_night'],
        'additional_night' => (string) $values['additional_night'],
        'wellness' => (string) $values['wellness'],
        'breakfast' => (string) $values['breakfast'],
        'romance' => (string) $values['romance'],
        'minimum_nights' => (int) $values['minimum_nights'],
      ]);
      $this->flashMessage('Cenník bol uložený.', 'success');
      $this->redirect('this');
    };
    return $form;
  }
}
