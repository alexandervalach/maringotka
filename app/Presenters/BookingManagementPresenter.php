<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\BookingDecisionFormFactory;
use App\Forms\BookingInvoiceFormFactory;
use App\Infrastructure\Database\ConnectionFactory;
use App\Model\BookingInvoiceRepository;
use App\Model\PricingRepository;
use App\Components\Invoice\InvoicePdf;
use Nette\Application\Responses\CallbackResponse;
use App\Components\BookingQuoteControl;
use App\Model\BookingManagementRepository;
use App\Model\BookingPaymentRepository;
use App\Model\BookingPaymentDetails;
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

  private readonly BookingInvoiceRepository $invoices;

  private readonly BookingInvoiceFormFactory $invoiceForms;

  /**
   * @param BookingManagementRepository $bookings Reservation management storage.
   * @param BookingDecisionFormFactory $forms Decision form factory.
   */
  public function __construct(
    private readonly BookingManagementRepository $bookings,
    private readonly BookingDecisionFormFactory $forms,
    private readonly BookingPaymentRepository $payments,
    ConnectionFactory $connections,
    PricingRepository $pricing,
  ) {
    parent::__construct();
    $this->invoices = new BookingInvoiceRepository($connections, $pricing);
    $this->invoiceForms = new BookingInvoiceFormFactory();
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
    $this->template->bookingHistory = $this->bookings->history($id);
    $payment = $this->payments->forBooking($id);
    $this->template->payment = $payment;
    $this->template->invoice = $this->invoices->forBooking($id);
    $supplier = $this->invoices->supplierSettings();
    $this->template->invoiceSupplierReady = $supplier !== [];
    $this->template->invoiceEdit = $this->getParameter('billing') === '1';
    $account = $this->payments->settings();
    $this->template->paymentAccount = $account;
    $this->template->paymentAmount = $payment !== null && $payment['total_cents'] !== null
      ? number_format($payment['total_cents'] / 100, 2, ',', ' ') . ' €'
      : null;
    $this->template->paymentQrDataUri = null;
    $this->template->paymentQrAvailable = false;
    if ($payment !== null
      && $account['account_holder'] !== null
      && $this->hasPaymentInstructions(
        $request['status'],
        $payment['total_cents'],
        $account['iban'],
        $account['account_holder'],
      )
      && mb_strlen($account['account_holder']) <= 70) {
      $document = $this->paymentQrDocument($account, $payment);
      $qr = $this->paymentQr();
      $cachedPng = $qr->cached($document);
      $this->template->paymentQrAvailable = true;
      if ($cachedPng !== null) {
        $this->template->paymentQrDataUri = 'data:image/png;base64,' . base64_encode($cachedPng);
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
   * Creates the CSRF-protected manual-payment form for an existing booking.
   *
   * @return Form Form for administrator-verified bank transfers.
   */
  protected function createComponentPaymentForm(): Form
  {
    $form = $this->forms->createPaymentStatus();

    $form->onSuccess[] = function (Form $form): void {
      $markPaid = $form->getComponent('markPaid');
      $markUnpaid = $form->getComponent('markUnpaid');

      if (!$markPaid instanceof \Nette\Forms\Controls\SubmitButton
        || !$markUnpaid instanceof \Nette\Forms\Controls\SubmitButton) {
        throw new \LogicException('Chýbajú akcie formulára úhrady.');
      }

      if (!$markPaid->isSubmittedBy() && !$markUnpaid->isSubmittedBy()) {
        $form->addError('Vyberte požadovanú zmenu úhrady.');
        return;
      }

      try {
        $this->payments->setPaid($this->editingId, $markPaid->isSubmittedBy());
      } catch (DomainException | InvalidArgumentException $error) {
        $form->addError($error->getMessage());
        return;
      }

      $this->flashMessage(
        $markPaid->isSubmittedBy()
          ? 'Rezervácia bola označená ako zaplatená.'
          : 'Rezervácia bola označená ako nezaplatená.',
        'success',
      );

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
      $confirm = $form->getComponent('confirm', false);
      $isConfirmation = $confirm instanceof \Nette\Forms\Controls\SubmitButton
        && $confirm->isSubmittedBy();

      if ($isConfirmation) {
        $decision = 'confirmed';
        $reason = '';
      } else {
        $values = $form->getValues('array');
        $decision = (string) $values['decision'];
        $reason = (string) $values['reason'];
      }

      try {
        $this->bookings->decide($this->editingId, $decision, $reason);
      } catch (InvalidArgumentException | DomainException $error) {
        $form->addError($error->getMessage());
        return;
      }

      $this->flashMessage(
        $isConfirmation ? 'Rezervácia bola potvrdená.' : 'Rozhodnutie bolo uložené.',
        'success',
      );

      $this->redirect('this');
    };
    return $form;
  }

  /**
   * Downloads the already issued invoice without regenerating its contents.
   */
  public function handleDownloadInvoice(): void
  {
    if ($this->editingId < 1) {
      $this->error('Faktúra neexistuje.', 404);
    }

    $invoice = $this->invoices->forBooking($this->editingId);

    if ($invoice === null) {
      $this->error('Faktúra ešte nebola vystavená.', 404);
    }

    $bytes = (new InvoicePdf())->render($invoice['document']);
    $fileName = 'faktura-' . $invoice['number'] . '.pdf';

    $this->sendResponse(new CallbackResponse(
      static function (\Nette\Http\IRequest $request, \Nette\Http\IResponse $response) use ($bytes, $fileName): void {
        $response->setContentType('application/pdf');
        $response->setHeader('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        $response->setHeader('Cache-Control', 'no-store, private');
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        echo $bytes;
      },
    ));
  }

  /**
   * Issues the invoice immediately using the guest name from the booking.
   * A missing billing address does not block issuance.
   */
  protected function createComponentQuickInvoiceForm(): Form
  {
    $form = $this->invoiceForms->createQuickIssue();

    $form->onSuccess[] = function (Form $form): void {
      try {
        $this->invoices->issue($this->editingId, []);
      } catch (DomainException | InvalidArgumentException $error) {
        $form->addError($error->getMessage());
        return;
      }

      $this->handleDownloadInvoice();
    };

    return $form;
  }

  /**
   * Optional prefilled customer billing form used before the first issue.
   */
  protected function createComponentInvoiceForm(): Form
  {
    $request = $this->editingId > 0 ? $this->bookings->requestById($this->editingId) : null;
    $form = $this->invoiceForms->createCustomer([
      'customer_name' => (string) ($request['guest_name'] ?? ''),
      'customer_address' => (string) ($request['guest_billing_address'] ?? ''),
      'customer_ico' => '',
      'customer_dic' => '',
    ]);

    $form->onSuccess[] = function (Form $form): void {
      try {
        $this->invoices->issue($this->editingId, $form->getValues('array'));
      } catch (DomainException | InvalidArgumentException $error) {
        $form->addError($error->getMessage());
        return;
      }

      $this->handleDownloadInvoice();
    };

    return $form;
  }
}
