<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\BookingFormFactory;
use App\Localization\SupportedLocales;
use App\Model\BookingRepository;
use App\Model\BookingPaymentDetails;
use App\Model\BookingQuoteView;
use App\Model\BookingPriceCalculator;
use App\Model\BookingUnavailableException;
use App\Model\PricingRepository;
use Contributte\Translation\Translator;
use InvalidArgumentException;
use Nette\Application\UI\Form;

/**
 * Accepts reservation requests without automatically confirming them.
 */
final class BookingPresenter extends BasePresenter
{
  /**
   * @var array{reference: string, arrival: string, departure: string, quote: array<string, mixed>}|null
   */
  private ?array $requestReceipt = null;

  /**
   * @param Translator $translator Shared SK/EN translator.
   * @param BookingRepository $bookings Reservation storage.
   * @param PricingRepository $pricing Current minimum stay.
   * @param BookingFormFactory $forms Request form factory.
   */
  public function __construct(
    private readonly Translator $translator,
    private readonly BookingRepository $bookings,
    private readonly PricingRepository $pricing,
    private readonly BookingFormFactory $forms,
  ) {
    parent::__construct();
  }

  /**
   * @param string $locale Public language code.
   * @return void
   */
  public function actionDefault(string $locale): void
  {
    if (!SupportedLocales::isSupported($locale)) {
      $this->error('Unknown language.', 404);
    }

    if (!$this->getHttpRequest()->isMethod('GET')) {
      return;
    }

    $section = $this->getSession()->getSection('booking_request_receipt');
    $receipt = $section->get('receipt');
    $section->remove('receipt');

    if (is_array($receipt)
      && isset($receipt['reference'], $receipt['arrival'], $receipt['departure'], $receipt['quote'])) {
      $this->requestReceipt = $receipt;
      $this->getHttpResponse()->setHeader('Cache-Control', 'private, no-store');
    }
  }

  /**
   * @return void Provides the existing public layout context.
   */
  protected function beforeRender(): void
  {
    parent::beforeRender();
    $locale = $this->configurePublicTemplate($this->translator, $this->getParameter('locale'), 'booking');
    $this->template->add('title', $locale === 'en' ? 'Reservation request' : 'Žiadosť o rezerváciu');
    $this->template->add('minimumNights', $this->pricing->getPrices()['minimum_nights']);
    $this->template->add('bookingPrices', $this->pricing->getPrices());
    $this->template->add('maxNights', BookingPriceCalculator::MAX_NIGHTS);
    // Only future blocking stays are exposed; no guest information is returned.
    $today = new \DateTimeImmutable('today');
    $calendarEnd = $today->modify('first day of this month')->modify('+12 months');
    $this->template->add('calendarToday', $today->format('Y-m-d'));
    $this->template->add('calendarUntil', $calendarEnd->format('Y-m-d'));
    $this->template->add('blockedIntervals', $this->bookings->blockedIntervals(
      $today->format('Y-m-d'),
      $calendarEnd->format('Y-m-d'),
    ));
    $this->template->add('requestSubmitted', $this->requestReceipt !== null);
    $this->template->add('requestReceipt', $this->requestReceipt);
  }

  /**
   * @return Form CSRF-protected public request form.
   */
  protected function createComponentBookingForm(): Form
  {
    $locale = $this->getParameter('locale');
    $form = $this->forms->create($locale === 'en' ? 'en' : 'sk');
    $form->onSuccess[] = function (Form $form): void {
      $values = $form->getValues('array');
      $arrival = (string) $values['arrival_date'];
      $departure = (string) $values['departure_date'];
      $minimum = $this->pricing->getPrices()['minimum_nights'];
      try {
        BookingRepository::validateStay($arrival, $departure, $minimum);
        // The browser total is display-only. Recalculate and snapshot server-side.
        $quote = (new BookingPriceCalculator())->calculate($arrival, $departure, $this->pricing->getPrices(), [
          'wellness' => (bool) $values['wellness'],
          'breakfast' => (bool) $values['breakfast'],
          'romance' => (bool) $values['romance'],
          'frizzante' => (int) ($values['frizzante'] ?? 0),
          'canvas_bag' => (int) ($values['canvas_bag'] ?? 0),
        ]);
        if ($this->bookings->isOccupied($arrival, $departure)) {
          $form->addError('Zvolený termín je už obsadený alebo čaká na potvrdenie.');
          return;
        }
        $created = $this->bookings->request([
          'arrival_date' => $arrival,
          'departure_date' => $departure,
          'guest_name' => (string) $values['guest_name'],
          'guest_email' => (string) $values['guest_email'],
          'guest_phone' => (string) $values['guest_phone'],
          'guest_note' => (string) $values['guest_note'],
        ], $minimum, $quote);

        $view = (new BookingQuoteView())->parse($quote, $quote['total_cents']);

        if ($view === null) {
          throw new \LogicException('Cenový rozpis novej rezervácie nie je dostupný.');
        }

        $this->getSession()->getSection('booking_request_receipt')->set('receipt', [
          'reference' => BookingPaymentDetails::variableSymbol($created['id'], $created['created_year']),
          'arrival' => $arrival,
          'departure' => $departure,
          'quote' => $view,
        ]);
      } catch (BookingUnavailableException $error) {
        $form->addError('Zvolený termín bol medzičasom obsadený.');
        return;
      } catch (InvalidArgumentException $error) {
        $form->addError($error->getMessage());
        return;
      }
      // GET confirmation avoids accidental resubmission when the page is refreshed.
      $this->redirect('this');
    };
    return $form;
  }
}
