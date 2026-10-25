<?php

declare(strict_types=1);

namespace App\Model;

use RuntimeException;

/**
 * Indicates that another active reservation already occupies the interval.
 */
final class BookingUnavailableException extends RuntimeException
{
}
