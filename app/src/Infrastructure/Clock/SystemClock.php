<?php

declare(strict_types=1);

namespace App\Infrastructure\Clock;

use App\Application\Ports\ClockInterface;
use DateTimeImmutable;

/**
 * Default real-time implementation of {@see ClockInterface}.
 *
 * Returns the actual system time. Wired in `bootstrap.php`; tests use a fake
 * implementation to control time.
 */
final class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
