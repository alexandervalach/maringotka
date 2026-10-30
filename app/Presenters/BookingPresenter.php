<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Forms\BookingFormFactory;
use App\Localization\SupportedLocales;
use App\Model\BookingRepository;
use App\Model\BookingUnavailableException;
use App\Model\PricingRepository;
use App\Router\PublicRoutes;
use Contributte\Translation\Translator;
use InvalidArgumentException;
use Nette\Application\UI\Form;

/**
 * Accepts reservation requests without automatically confirming them.
 */
final class BookingPresenter extends BasePresenter
{
  private bool $requestSubmitted = false;
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
  }

  /**
   * @return void Provides the existing public layout context.
   */
  protected function beforeRender(): void
  {
    parent::beforeRender();
    $locale = $this->getParameter('locale');
    if (!is_string($locale) || !SupportedLocales::isSupported($locale)) {
      $this->error('Unknown language.', 404);
    }
    $this->translator->setLocale($locale);
    $this->template->setTranslator($this->translator, $locale);
    $this->template->add('locale', $locale);
    $this->template->add('paths', PublicRoutes::forLocale($locale));
    $this->template->add('languageUrls', PublicRoutes::languageUrlsFor('booking'));
    $this->template->add('title', $locale === 'en' ? 'Reservation request' : 'Žiadosť o rezerváciu');
    $this->template->add('minimumNights', $this->pricing->getPrices()['minimum_nights']);
    $this->template->add('requestSubmitted', $this->requestSubmitted);
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
        if ($this->bookings->isOccupied($arrival, $departure)) {
          $form->addError('Zvolený termín je už obsadený alebo čaká na potvrdenie.');
          return;
        }
        $this->bookings->request([
          'arrival_date' => $arrival,
          'departure_date' => $departure,
          'guest_name' => (string) $values['guest_name'],
          'guest_email' => (string) $values['guest_email'],
          'guest_phone' => (string) $values['guest_phone'],
          'guest_note' => (string) $values['guest_note'],
        ], $minimum);
      } catch (BookingUnavailableException $error) {
        $form->addError('Zvolený termín bol medzičasom obsadený.');
        return;
      } catch (InvalidArgumentException $error) {
        $form->addError($error->getMessage());
        return;
      }
      $this->requestSubmitted = true;
    };
    return $form;
  }
}
