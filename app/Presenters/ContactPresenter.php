<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\ContactFormFactory;
use App\Localization\SupportedLocales;
use App\Model\ContactRepository;
use App\Router\PublicRoutes;
use Contributte\Translation\Translator;
use Nette\Application\UI\Form;

/** 
 * Public contact page and authenticated editing in one feature presenter. 
 */
final class ContactPresenter extends BasePresenter
{
  /**
   * @param Translator $translator Public SK/EN translations.
   * @param ContactRepository $contacts Contact settings storage.
   * @param ContactFormFactory $forms Contact-specific form factory.
   */
  public function __construct(
    private readonly Translator $translator,
    private readonly ContactRepository $contacts,
    private readonly ContactFormFactory $forms,
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
   * @param string $locale Language of the public contact page. 
   */
  public function actionDefault(string $locale): void
  {
    if (!SupportedLocales::isSupported($locale)) {
      $this->error('Unknown language.', 404);
    }
  }

  /** 
   * @return void Prepare the contact editor with current values, not an empty form. 
   */
  public function actionEdit(): void
  {
    if ($this->getHttpRequest()->isMethod('GET')) {
      /** @var Form $form */
      $form = $this->getComponent('contactForm');
      $form->setDefaults($this->contacts->getDetails());
    }
  }

  /** 
   * @return void Public template context, matching existing layout requirements. 
   */
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
    $this->template->add('languageUrls', PublicRoutes::languageUrlsFor('contact'));
    $this->template->add('title', $this->translator->translate('messages.navigation.contact'));
  }

  /** 
   * @return void Provide public contact details and a sanitized telephone URI.
   */
  public function renderDefault(): void
  {
    $details = $this->contacts->getDetails();
    $this->template->add('contact', $details);
    $this->template->add('telephoneNumber', preg_replace('/[^+0-9]/', '', $details['phone']) ?? '');
  }

  /** 
   * @return Form Contact settings with save handler. 
   */
  protected function createComponentContactForm(): Form
  {
    $form = $this->forms->create();
    $form->onSuccess[] = function (Form $form): void {
      $values = $form->getValues('array');
      $this->contacts->save([
        'phone' => (string) $values['phone'],
        'email' => (string) $values['email'],
        'instagram_url' => (string) $values['instagram_url'],
        'booking_url' => (string) $values['booking_url'],
        'maps_url' => (string) $values['maps_url'],
        'youtube_url' => (string) $values['youtube_url'],
      ]);
      $this->flashMessage('Kontaktné údaje boli uložené.', 'success');
      $this->redirect('this');
    };

    return $form;
  }
}
