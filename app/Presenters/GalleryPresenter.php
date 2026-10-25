<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\GalleryFormFactory;
use App\Forms\GalleryOrderFormFactory;
use App\Forms\GalleryUploadFormFactory;
use App\Infrastructure\Media\GalleryImageProcessor;
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
   * @param GalleryOrderFormFactory $orderForms Gallery order form.
   */
  public function __construct(
    private readonly Translator $translator,
    private readonly GalleryRepository $gallery,
    private readonly GalleryFormFactory $forms,
    private readonly GalleryOrderFormFactory $orderForms,
    private readonly GalleryUploadFormFactory $uploadForms,
    private readonly GalleryImageProcessor $imageProcessor,
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
    $photos = $this->gallery->all();
    $this->template->add('photos', $photos);
    if ($this->getHttpRequest()->isMethod('GET')) {
      /** @var Form $form */
      $form = $this->getComponent('galleryOrderForm');
      $form->setDefaults(['ordered_ids' => implode(',', array_column($photos, 'id'))]);
    }
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

  /** Public gallery, ordered and filtered by PostgreSQL metadata. */
  public function renderDefault(): void
  {
    $locale = $this->getParameter('locale');
    if (!is_string($locale)) {
      $this->error('Unknown language.', 404);
    }
    $this->template->add('photos', $this->gallery->visible($locale));
  }

  /**
   * Create the order form and validate all submitted gallery IDs server-side.
   *
   * @return Form CSRF-protected gallery ordering form.
   */
  protected function createComponentGalleryOrderForm(): Form
  {
    $form = $this->orderForms->create();
    $form->onSuccess[] = function (Form $form): void {
      $values = $form->getValues('array');
      $raw = (string) $values['ordered_ids'];
      if (preg_match('/^[1-9][0-9]*(,[1-9][0-9]*)*$/D', $raw) !== 1) {
        $form->addError('Neplatné poradie fotografií.');
        return;
      }
      $parts = explode(',', $raw);
      $ids = array_map(static fn (string $id): int => (int) $id, $parts);
      try {
        $this->gallery->reorder($ids);
      } catch (\InvalidArgumentException $exception) {
        $form->addError('Galéria sa zmenila. Obnov stránku a skús to znova.');
        return;
      }
      $this->flashMessage('Poradie fotografií bolo uložené.', 'success');
      $this->redirect('manage');
    };
    return $form;
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
  /**
   * Creates a protected JPEG upload form for the existing gallery manager.
   *
   * @return Form Upload form.
   */
  protected function createComponentGalleryUploadForm(): Form
  {
    $form = $this->uploadForms->create();
    $form->onSuccess[] = function (Form $form): void {
      $values = $form->getValues('array');
      $upload = $values['photo'];
      if (!$upload instanceof \Nette\Http\FileUpload) {
        $form->addError('Neplatná fotografia.');
        return;
      }
      try {
        $image = $this->imageProcessor->process($upload);
        try {
          $this->gallery->addUploaded($image);
        } catch (\Throwable $exception) {
          foreach ($image['files'] as $file) {
            @unlink($file);
          }
          throw $exception;
        }
      } catch (\RuntimeException $exception) {
        $form->addError($exception->getMessage());
        return;
      }
      $this->flashMessage('Fotografia bola nahraná.', 'success');
      $this->redirect('manage');
    };
    return $form;
  }

}
