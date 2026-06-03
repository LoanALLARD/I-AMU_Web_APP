<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Domain\SessionStatus;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the SessionStatus enum: French labels, badge classes and
 * terminal-state detection used by views and the lifecycle guards.
 */
final class SessionStatusTest extends TestCase
{
    public function testBackingValuesMatchColumn(): void
    {
        self::assertSame('DRAFT', SessionStatus::Draft->value);
        self::assertSame('SCHEDULED', SessionStatus::Scheduled->value);
        self::assertSame('ACTIVE', SessionStatus::Active->value);
        self::assertSame('ENDED', SessionStatus::Ended->value);
        self::assertSame('CANCELLED', SessionStatus::Cancelled->value);
    }

    public function testLabels(): void
    {
        self::assertSame('Brouillon', SessionStatus::Draft->label());
        self::assertSame('Planifiée', SessionStatus::Scheduled->label());
        self::assertSame('En cours', SessionStatus::Active->label());
        self::assertSame('Terminée', SessionStatus::Ended->label());
        self::assertSame('Annulée', SessionStatus::Cancelled->label());
    }

    public function testBadgeClassDerivesFromValue(): void
    {
        self::assertSame('badge-draft', SessionStatus::Draft->badgeClass());
        self::assertSame('badge-active', SessionStatus::Active->badgeClass());
        self::assertSame('badge-cancelled', SessionStatus::Cancelled->badgeClass());
    }

    public function testIsTerminalOnlyForEndedAndCancelled(): void
    {
        self::assertTrue(SessionStatus::Ended->isTerminal());
        self::assertTrue(SessionStatus::Cancelled->isTerminal());

        self::assertFalse(SessionStatus::Draft->isTerminal());
        self::assertFalse(SessionStatus::Scheduled->isTerminal());
        self::assertFalse(SessionStatus::Active->isTerminal());
    }
}
