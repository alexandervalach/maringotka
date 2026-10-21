<?php

declare(strict_types=1);

namespace App\Forms;

use Nette\Application\UI\Form;

/** 
 * Defines only the editable rates supported by the current pricing page. 
 */
final class PricingFormFactory extends FormFactory
{
  /** 
   * @return Form CSRF-protected pricing editor. 
   */
  public function create(): Form
  {
    $form = parent::create();
    foreach ([
      'first_night' => 'Prvá noc (€)',
      'additional_night' => 'Každá ďalšia noc (€)',
      'wellness' => 'Wellness – deň (€)',
      'breakfast' => 'Raňajkový košík pre dvoch – deň (€)',
      'romance' => 'Romantický balíček – pobyt (€)',
    ] as $field => $label) {
      $form->addText($field, $label)
        ->setRequired('Vyplňte cenu.')
        ->setMaxLength(8)
        ->addRule(Form::PATTERN, 'Použite cenu v eurách, napr. 20 alebo 20,50.', '[0-9]{1,5}([.,][0-9]{1,2})?');
    }
    $form->addInteger('minimum_nights', 'Minimálny počet nocí')
      ->setRequired('Vyplňte minimálny počet nocí.')
      ->addRule(Form::RANGE, 'Zadajte 1 až 365 nocí.', [1, 365]);
    $form->addSubmit('save', 'Uložiť zmeny')->setHtmlAttribute('class', 'button');
    return $form;
  }
}
