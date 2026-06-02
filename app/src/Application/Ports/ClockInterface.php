<?php

declare(strict_types=1);

namespace App\Application\Ports;

use DateTimeImmutable;

/**
 * Wall-clock abstraction.
 *
 * Lives in the Application layer so any service that needs "now" depends on
 * this interface instead of `new DateTimeImmutable()`. Tests can swap a
 * `FakeClock` to make time deterministic.
 */
interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
