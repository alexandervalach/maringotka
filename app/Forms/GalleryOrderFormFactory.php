<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;

/**
 * Creates the CSRF-protected gallery ordering form.
 */
final class GalleryOrderFormFactory extends FormFactory
{
  /**
   * Create an order form without imposing Bootstrap-specific classes.
   *
   * @return Form Gallery ordering form.
   */
  public function create(): Form
  {
    $form = parent::create();
    $form->addHidden('ordered_ids')->setRequired();
    $form->addSubmit('save', 'Uložiť poradie')->setHtmlAttribute('class', 'button');
    return $form;
  }
}
