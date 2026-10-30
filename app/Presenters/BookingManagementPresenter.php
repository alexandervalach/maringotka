<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\BookingDecisionFormFactory;
use App\Model\BookingManagementRepository;
use DomainException;
use InvalidArgumentException;
use Nette\Application\UI\Form;

/**
 * Authenticated review and approval of accommodation requests.
 */
final class BookingManagementPresenter extends BasePresenter
{
  private int $editingId = 0;

  /**
   * @param BookingManagementRepository $bookings Reservation management storage.
   * @param BookingDecisionFormFactory $forms Decision form factory.
   */
  public function __construct(
    private readonly BookingManagementRepository $bookings,
    private readonly BookingDecisionFormFactory $forms,
  ) {
    parent::__construct();
  }

  /**
   * Protect both the list and individual request details.
   */
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
    $this->template->add('requests', $this->bookings->allRequests());
  }

  /**
   * @param int $id Request being decided.
   * @return void
   */
  public function actionEdit(int $id): void
  {
    if ($id < 1 || $this->bookings->requestById($id) === null) {
      $this->error('Rezervácia neexistuje.', 404);
    }
    $this->editingId = $id;
  }

  /**
   * @param int $id Request being decided.
   * @return void
   */
  public function renderEdit(int $id): void
  {
    $request = $this->bookings->requestById($id);
    if ($request === null) {
      $this->error('Rezervácia neexistuje.', 404);
    }
    $this->template->add('request', $request);
  }

  /**
   * @return Form CSRF-protected administrator decision form.
   */
  protected function createComponentDecisionForm(): Form
  {
    $form = $this->forms->create();
    $form->onSuccess[] = function (Form $form): void {
      $values = $form->getValues('array');
      try {
        $this->bookings->decide(
          $this->editingId,
          (string) $values['decision'],
          (string) $values['reason'],
        );
      } catch (InvalidArgumentException | DomainException $error) {
        $form->addError($error->getMessage());
        return;
      }
      $this->flashMessage('Rozhodnutie bolo uložené.', 'success');
      $this->redirect('default');
    };
    return $form;
  }
}
