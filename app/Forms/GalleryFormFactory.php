<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;
use App\Infrastructure\Media\GalleryImageProcessor;

/** 
 * Edits one existing gallery item's order, visibility and accessible descriptions. 
 */
final class GalleryFormFactory extends FormFactory
{
  /** 
   * @return Form CSRF-protected gallery item editor. 
   */
  public function create(): Form
  {
    $form = parent::create();
    $form->addInteger('position', 'Poradie')->setRequired()->addRule(Form::RANGE, 'Poradie musí byť od 1 do 10000.', [1, 10000]);
    $form->addCheckbox('is_visible', 'Zobraziť fotografiu vo verejnej galérii');
    $form->addText('alt_sk', 'Alternatívny text – SK')->setMaxLength(300);
    $form->addText('alt_en', 'Alternative text – EN')->setMaxLength(300);
    $form->addSubmit('save', 'Uložiť zmeny')->setHtmlAttribute('class', 'button');
    return $form;
  }

  /**
   * Create the gallery ordering form.
   *
   * @return Form CSRF-protected ordering form.
   */
  public function createOrder(): Form
  {
    $form = parent::create();
    $form->addHidden('ordered_ids')->setRequired();
    $form->setHtmlAttribute('class', 'gallery-order-form');
    $form->addSubmit('save', 'Uložiť zmeny')->setHtmlAttribute('class', 'button');
    return $form;
  }

  /**
   * Create the gallery JPEG or PNG upload form.
   *
   * @return Form CSRF-protected image upload form.
   */
  public function createUpload(): Form
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
