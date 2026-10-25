<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;
use App\Infrastructure\Media\GalleryImageProcessor;

/**
 * Creates the CSRF-protected form for one JPEG or PNG gallery upload.
 */
final class GalleryUploadFormFactory extends FormFactory
{
  /**
   * @return Form JPEG and PNG upload form.
   */
  public function create(): Form
  {
    $form = parent::create();
    $form->addUpload('photo', 'Fotografia (JPG alebo PNG)')
      ->setRequired()
      ->addRule(Form::Image, 'Vyber platnú fotografiu.')
      ->addRule(Form::MaxFileSize, 'Maximálna veľkosť je 32 MB.', GalleryImageProcessor::MAX_BYTES);
    $form->addSubmit('save', 'Uložiť zmeny')->setHtmlAttribute('class', 'button');
    return $form;
  }
}
