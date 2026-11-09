<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\BookingDecisionFormFactory;
use App\Components\BookingQuoteControl;
use App\Model\BookingManagementRepository;
use App\Model\BookingPaymentRepository;
use App\Model\BookingPaymentDetails;
use App\Model\BookingPaymentInstructionMailer;
use App\Model\BookingPaymentQr;
use DomainException;
use InvalidArgumentException;
use RuntimeException;
use Nette\Application\BadRequestException;
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
    private readonly BookingPaymentRepository $payments,
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
    $this->getSession('booking-admin-view')->set('mode', 'table');
    $status = trim((string) ($this->getParameter('status') ?? ''));
    $search = trim((string) ($this->getParameter('q') ?? ''));
    $before = $this->readCursor('before');
    $after = $this->readCursor('after');
    try {
      $page = $this->bookings->page($status, $search, $before, $after);
    } catch (InvalidArgumentException $error) {
      throw new BadRequestException($error->getMessage(), 400);
    }
    $this->template->requests = $page['requests'];
    $this->template->olderCursor = $page['older'];
    $this->template->newerCursor = $page['newer'];
    $this->template->statusFilter = $status;
    $this->template->searchTerm = $search;
  }

  /**
   * Reads a positive decimal cursor without silently coercing invalid values.
   *
   * @param string $name Query parameter name.
   * @return int|null Parsed cursor.
   */
  private function readCursor(string $name): ?int
  {
    $raw = $this->getParameter($name);
    if ($raw === null || $raw === '') {
      return null;
    }
    if (!is_string($raw) || !ctype_digit($raw) || strlen($raw) > 18 || (int) $raw < 1) {
      throw new BadRequestException('Neplatný kurzor stránkovania.', 400);
    }
    return (int) $raw;
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
    $this->template->request = $request;
    $this->template->statusHistory = $this->bookings->statusHistory($id);
    $payment = $this->payments->forBooking($id);
    $this->template->payment = $payment;
    $account = $this->payments->settings();
    $this->template->paymentAccount = $account;
    $this->template->paymentAmount = $payment !== null && $payment['total_cents'] !== null
      ? number_format($payment['total_cents'] / 100, 2, ',', ' ') . ' €'
      : null;
    $this->template->paymentQrDataUri = null;
    $this->template->paymentQrConfigured = trim((string) getenv('BYSQUARE_API_KEY')) !== '';
    $this->template->paymentQrAvailable = false;
    $this->template->paymentEmailUrl = null;
    $this->template->paymentEmailConfigured = BookingPaymentInstructionMailer::configured();
    if ($payment !== null
      && $account['account_holder'] !== null
      && $this->hasPaymentInstructions(
        $request['status'],
        $payment['total_cents'],
        $account['iban'],
        $account['account_holder'],
      )
      && mb_strlen($account['account_holder']) <= 70) {
      $amount = BookingPaymentDetails::amount((int) $payment['total_cents']);
      $document = $this->paymentQrDocument($account, $payment);
      $qr = $this->paymentQr();
      $cachedPng = $qr->cached($document);
      $this->template->paymentQrAvailable = true;
      if ($cachedPng !== null) {
        $this->template->paymentQrDataUri = 'data:image/png;base64,' . base64_encode($cachedPng);
      }
      $email = (string) $request['guest_email'];
      if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
        $subject = 'Platobné údaje k rezervácii č. ' . $id;
        $body = "Dobrý deň,\n\nzasielame platobné údaje k potvrdenej rezervácii č. " . $id
          . "\nPríjemca: " . $account['account_holder']
          . "\nIBAN: " . $account['iban']
          . ($account['bic'] !== null ? "\nBIC: " . $account['bic'] : '')
          . "\nSuma: " . $amount . " EUR"
          . "\nVariabilný symbol: " . $payment['variable_symbol']
          . "\nSpráva pre príjemcu: Rezervacia VS " . $payment['variable_symbol']
          . "\n\nPri platbe skontrolujte sumu, IBAN a variabilný symbol.\n\nĎakujeme.";
        $this->template->paymentEmailUrl = 'mailto:' . rawurlencode($email)
          . '?subject=' . rawurlencode($subject) . '&body=' . rawurlencode($body);
      }
    }
    $view = $this->getSession('booking-admin-view');
    $month = $view->get('month');
    $this->template->bookingBackUrl = $view->get('mode') === 'calendar'
      ? '/admin/booking-calendar' . (is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month) ? '?month=' . $month : '')
      : '/admin/bookings';
  }

  /**
   * Creates the private QR cache client used by viewing, generation and email.
   *
   * @return BookingPaymentQr QR cache and API client.
   */
  private function paymentQr(): BookingPaymentQr
  {
    return new BookingPaymentQr(dirname(__DIR__, 2) . '/var/temp/payment-qr');
  }

  /**
   * Builds the same validated payment document for all QR-related actions.
   * Callers check eligibility before calling this method.
   *
   * @param array<string, mixed> $account Payment account settings.
   * @param array<string, mixed> $payment Booking payment snapshot.
   * @return array{payments: list<array<string, mixed>>} PAY by square request body.
   */
  private function paymentQrDocument(array $account, array $payment): array
  {
    return BookingPaymentQr::document(
      (string) $account['iban'],
      (string) $account['account_holder'],
      (int) $payment['total_cents'],
      (string) $payment['variable_symbol'],
    );
  }

  /**
   * Common conditions for providing payment instructions.
   * Callers separately check the presence of booking/payment rows and email validity.
   *
   * @param string $bookingStatus Current booking status.
   * @param int|null $totalCents Snapshotted booking price.
   * @param string|null $iban Recipient IBAN.
   * @param string|null $holder Recipient account holder.
   * @return bool
   */
  private function hasPaymentInstructions(
    string $bookingStatus,
    ?int $totalCents,
    ?string $iban,
    ?string $holder,
  ): bool {
    return $bookingStatus === 'confirmed'
      && $totalCents !== null
      && $totalCents > 0
      && $iban !== null
      && $holder !== null;
  }

  /**
   * Creates a price quote component for administrator booking views.
   *
   * @return BookingQuoteControl
   */
  protected function createComponentBookingQuote(): BookingQuoteControl
  {
    return new BookingQuoteControl();
  }


  /**
   * Creates a QR only after an authenticated administrator explicitly requests it.
   * Repeated requests reuse the private cached image without consuming credit.
   *
   * @return Form CSRF-protected QR generation form.
   */
  protected function createComponentQrForm(): Form
  {
    $form = new Form();
    $form->addProtection('Platnosť formulára vypršala. Obnovte stránku.');
    $form->addSubmit('generate', 'Vygenerovať PAY by square QR')
      ->setHtmlAttribute('class', 'admin-save-button');
    $form->onSuccess[] = function (Form $form): void {
      $booking = $this->bookings->requestById($this->editingId);
      $payment = $this->payments->forBooking($this->editingId);
      $account = $this->payments->settings();
      if ($booking === null || $payment === null
        || !$this->hasPaymentInstructions(
          $booking['status'],
          $payment['total_cents'],
          $account['iban'],
          $account['account_holder'],
        )) {
        $form->addError('Pre túto rezerváciu nemožno vygenerovať platobný QR kód.');
        return;
      }
      try {
        $document = $this->paymentQrDocument($account, $payment);
        $this->paymentQr()->generate($document, (string) getenv('BYSQUARE_API_KEY'));
      } catch (InvalidArgumentException | RuntimeException $error) {
        $form->addError($error->getMessage());
        return;
      }
      $this->flashMessage('PAY by square QR kód bol pripravený.', 'success');
      $this->redirect('this');
    };
    return $form;
  }

  /**
   * Sends payment instructions only after an explicit, CSRF-protected admin action.
   * An existing QR may be attached; this never spends an API credit.
   *
   * @return Form Protected payment-email form.
   */
  protected function createComponentPaymentEmailForm(): Form
  {
    $form = new Form();
    $form->addProtection('Platnosť formulára vypršala. Obnovte stránku.');
    $form->addSubmit('send', 'Odoslať platobné údaje hosťovi')
      ->setHtmlAttribute('class', 'admin-save-button');
    $form->onSuccess[] = function (Form $form): void {
      $request = $this->bookings->requestById($this->editingId);
      $payment = $this->payments->forBooking($this->editingId);
      $account = $this->payments->settings();
      if ($request === null || $payment === null
        || !$this->hasPaymentInstructions(
          $request['status'],
          $payment['total_cents'],
          $account['iban'],
          $account['account_holder'],
        )
        || filter_var((string) $request['guest_email'], FILTER_VALIDATE_EMAIL) === false) {
        $form->addError('Platobné údaje nie je možné odoslať pre túto rezerváciu.');
        return;
      }
      try {
        $document = $this->paymentQrDocument($account, $payment);
        $png = $this->paymentQr()->cached($document);
        (new BookingPaymentInstructionMailer())->send(
          $this->editingId,
          (string) $request['guest_email'],
          (string) $account['account_holder'],
          (string) $account['iban'],
          $account['bic'],
          (int) $payment['total_cents'],
          $payment['variable_symbol'],
          $png,
        );
      } catch (\Throwable $error) {
        error_log('Payment instruction mail failed (' . get_class($error) . ').');
        $form->addError('Nepodarilo sa odoslať e-mail. Skontrolujte SMTP a skúste to znovu.');
        return;
      }
      $this->flashMessage('Platobné údaje boli odoslané SMTP serveru.', 'success');
      $this->redirect('this');
    };
    return $form;
  }

  /**
   * Creates the CSRF-protected manual-payment form for an existing booking.
   *
   * @return Form Form for administrator-verified bank transfers.
   */
  protected function createComponentPaymentForm(): Form
  {
    $form = new Form();
    $form->addProtection('Platnosť formulára vypršala. Obnovte stránku.');
    $form->addRadioList('status', 'Stav úhrady', [
      'unpaid' => 'Nezaplatená',
      'paid' => 'Zaplatená',
    ])->setRequired('Vyberte stav úhrady.');
    $form->addSubmit('save', 'Uložiť stav úhrady')
      ->setHtmlAttribute('class', 'admin-save-button');
    $form->onSuccess[] = function (Form $form): void {
      $values = $form->getValues('array');
      try {
        $this->payments->setPaid($this->editingId, $values['status'] === 'paid');
      } catch (DomainException | InvalidArgumentException $error) {
        $form->addError($error->getMessage());
        return;
      }
      $this->flashMessage('Stav úhrady bol uložený.', 'success');
      $this->redirect('this');
    };
    return $form;
  }

  /**
   * @return Form CSRF-protected administrator decision form.
   */
  protected function createComponentDecisionForm(): Form
  {
    $request = $this->editingId > 0 ? $this->bookings->requestById($this->editingId) : null;
    $form = $this->forms->create($request !== null && $request['status'] === 'confirmed' ? 'confirmed' : 'pending');
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
      $view = $this->getSession('booking-admin-view');
      $month = $view->get('month');
      $this->redirectUrl($view->get('mode') === 'calendar'
        ? '/admin/booking-calendar' . (is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month) ? '?month=' . $month : '')
        : '/admin/bookings');
    };
    return $form;
  }
}
