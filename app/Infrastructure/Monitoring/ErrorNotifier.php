<?php

declare(strict_types=1);

namespace App\Infrastructure\Monitoring;

use Throwable;
use Tracy\Debugger;

final class ErrorNotifier
{
    public function __construct(private readonly ErrorStore $store, private readonly string $release)
    {
    }

    public function handle(Throwable $error): void
    {
        try {
            $this->store->record($error, $this->release);
        } catch (Throwable $monitorFailure) {
            // Never throw from the Tracy fatal-error handler.
            error_log('Monitoring state update failed: ' . $monitorFailure::class);
        }
    }

    public static function attach(string $directory, string $release): void
    {
        $notifier = new self(new ErrorStore($directory), $release);
        Debugger::$onFatalError[] = $notifier->handle(...);
    }
}
