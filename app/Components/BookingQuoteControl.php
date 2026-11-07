<?php

declare(strict_types=1);

namespace App\Components;

use App\Model\BookingQuoteView;
use InvalidArgumentException;
use Nette\Application\UI\Control;

/**
 * Displays saved line items without modifying booking decisions or prices.
 * Only instantiate on admin-authenticated booking pages.
 */
final class BookingQuoteControl extends Control
{
  /**
   * @param array<string, mixed> $request Existing request data, including snapshot fields.
   * @param bool $compact Render compact extras icons for the table instead of full price list.
   * @return void
   */
  public function render(array $request, bool $compact = false): void
  {
    try {
      $quote = (new BookingQuoteView())->parse($request['price_quote'] ?? null, $request['price_total_cents'] ?? null);
      $error = null;
    } catch (InvalidArgumentException $exception) {
      $quote = null;
      $error = $exception->getMessage();
    }

    $this->template->add('quote', $quote);
    $this->template->add('quoteError', $error);
    $this->template->add('compact', $compact);
    $this->template->setFile(__DIR__ . '/templates/bookingQuote.latte');
    $this->template->render();
  }
}
