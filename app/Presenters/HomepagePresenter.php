<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\HomepageFormFactory;
use App\Model\HomepageRepository;
use App\Router\PublicRoutes;
use Contributte\Translation\Translator;
use Nette\Application\UI\Form;

/** 
 * Public pages and authenticated homepage editing live in the same presenter. 
 */
final class HomepagePresenter extends BasePresenter
{
  private string $editingLocale = 'sk';

  /**
   * @param Translator $translator Existing SK/EN translation service.
   * @param HomepageRepository $home Homepage content repository.
   * @param HomepageFormFactory $forms Homepage-specific form factory.
   */
  public function __construct(
    private readonly Translator $translator,
    private readonly HomepageRepository $home,
    private readonly HomepageFormFactory $forms,
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
   * @param string $locale Language being edited (sk or en).
   * @return void
   */
  public function actionEdit(string $locale): void
  {
    if ($locale !== 'sk' && $locale !== 'en') {
      $this->error('Unknown language.', 404);
    }
    $this->editingLocale = $locale;

    if ($this->getHttpRequest()->isMethod('GET')) {
      $this->translator->setLocale($locale);
      $saved = $this->home->findForEditing($locale);
      /** @var Form $form */
      $form = $this->getComponent('homepageForm');
      $form->setDefaults([
        'title' => $saved['title'] ?? (string) $this->translator->translate('messages.hero.headline'),
        'summary' => $saved['summary'] ?? (string) $this->translator->translate('messages.hero.intro'),
      ]);
    }
  }

  /** 
   * @param string $locale Edited language code. 
   */
  public function renderEdit(string $locale): void
  {
    $this->template->add('editingLocale', $locale);
  }

  /** 
   * @return Form Homepage editor with a save handler. 
   */
  protected function createComponentHomepageForm(): Form
  {
    $form = $this->forms->create();
    $form->onSuccess[] = function (Form $form): void {
      $values = $form->getValues('array');
      $this->home->save(
        $this->editingLocale,
        (string) $values['title'],
        (string) $values['summary'],
      );
      $this->flashMessage('Zmeny boli uložené a zverejnené.', 'success');
      $this->redirect('this');
    };

    return $form;
  }

  protected function beforeRender(): void
  {
    parent::beforeRender();
    if ($this->getAction() === 'edit') {
      return;
    }

    $locale = $this->getParameter('locale') === 'en' ? 'en' : 'sk';
    $this->translator->setLocale($locale);
    $this->template->setTranslator($this->translator, $locale);
    $this->template->add('locale', $locale);
    $this->template->add('paths', PublicRoutes::forLocale($locale));
    $action = $this->getAction();
    $this->template->add('languageUrls', [
      'sk' => '/' . PublicRoutes::PATHS[$action]['sk'],
      'en' => '/' . PublicRoutes::PATHS[$action]['en'],
    ]);
    $titleKey = $action === 'default' ? 'hero.headline' : 'navigation.' . $action;
    $published = $action === 'default' ? $this->home->findPublished($locale) : null;
    $this->template->add('homeTitle', $published['title'] ?? null);
    $this->template->add('homeSummary', $published['summary'] ?? null);
    $this->template->add('title', $published['title'] ?? $this->translator->translate('messages.' . $titleKey));
  }
}
