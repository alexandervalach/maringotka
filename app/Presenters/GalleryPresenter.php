<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\GalleryFormFactory;
use App\Localization\SupportedLocales;
use App\Model\GalleryRepository;
use App\Router\PublicRoutes;
use Contributte\Translation\Translator;
use Nette\Application\UI\Form;

/** 
 * Public gallery and authenticated management handled by the same presenter. 
 */
final class GalleryPresenter extends BasePresenter
{
  private int $editingId = 0;

  /**
   * @param Translator $translator Existing UI translator.
   * @param GalleryRepository $gallery Existing gallery photos and metadata.
   * @param GalleryFormFactory $forms Gallery item editor.
   */
  public function __construct(
    private readonly Translator $translator,
    private readonly GalleryRepository $gallery,
    private readonly GalleryFormFactory $forms,
  ) {
    parent::__construct();
  }

  protected function startup(): void
  {
    parent::startup();
    if (in_array($this->getAction(), ['manage', 'edit'], true)) {
      $this->requireAdmin();
    }
  }

  /** 
   * @param string $locale Public language. 
   */
  public function actionDefault(string $locale): void
  {
    if (!SupportedLocales::isSupported($locale)) {
      $this->error('Unknown language.', 404);
    }
  }

  /** 
   * Gallery overview visible only to the administrator. 
   */
  public function renderManage(): void
  {
    $this->template->add('photos', $this->gallery->all());
  }

  /** 
   * @param int $id Existing gallery item ID. 
   */
  public function actionEdit(int $id): void
  {
    $this->editingId = $id;
    $photo = null;
    foreach ($this->gallery->all() as $item) {
      if ($item['id'] === $id && $id > 0) {
        $photo = $item;
        break;
      }
    }
    if ($photo === null) {
      $this->error('Gallery photo not found.', 404);
    }
    $this->template->add('photo', $photo);
    if ($this->getHttpRequest()->isMethod('GET')) {
      /** @var Form $form */
      $form = $this->getComponent('galleryForm');
      $form->setDefaults($photo);
    }
  }

  /** 
   * Provide public template context before rendering. 
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
    $this->template->add('languageUrls', PublicRoutes::languageUrlsFor('gallery'));
    $this->template->add('title', $this->translator->translate('messages.navigation.gallery'));
  }

  /** 
   * Public gallery, ordered and filtered by PostgreSQL metadata. 
   */
  public function renderDefault(): void
  {
    $locale = $this->getParameter('locale');
    if (!is_string($locale)) {
      $this->error('Unknown language.', 404);
    }
    $this->template->add('photos', $this->gallery->visible($locale));
  }

  /** 
   * @return Form Per-photo editor. 
   */
  protected function createComponentGalleryForm(): Form
  {
    $form = $this->forms->create();
    $form->onSuccess[] = function (Form $form): void {
      $values = $form->getValues('array');
      $this->gallery->save(
        $this->editingId,
        (int) $values['position'],
        (bool) $values['is_visible'],
        (string) $values['alt_sk'],
        (string) $values['alt_en'],
      );
      $this->flashMessage('Fotografia bola uložená.', 'success');
      $this->redirect('manage');
    };
    return $form;
  }
}
