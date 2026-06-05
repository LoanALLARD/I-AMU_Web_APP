<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Domain\SessionException;
use DomainException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the SessionException named factories: each builds a
 * DomainException with the expected user-facing French message. Pure logic.
 */
final class SessionExceptionTest extends TestCase
{
    public function testIsDomainException(): void
    {
        self::assertInstanceOf(DomainException::class, SessionException::alreadyStarted());
    }

    public function testNotFoundIncludesId(): void
    {
        $message = SessionException::notFound(42)->getMessage();

        self::assertStringContainsString('42', $message);
        self::assertStringContainsString('introuvable', $message);
    }

    public function testNotFoundByCodeIncludesCode(): void
    {
        self::assertStringContainsString('ABC-123', SessionException::notFoundByCode('ABC-123')->getMessage());
    }

    public function testAlreadyStartedMessage(): void
    {
        self::assertStringContainsString('déjà démarrée', SessionException::alreadyStarted()->getMessage());
    }

    public function testAlreadyEndedMessage(): void
    {
        self::assertStringContainsString('déjà terminée', SessionException::alreadyEnded()->getMessage());
    }

    public function testCancelledMessage(): void
    {
        self::assertStringContainsString('annulée', SessionException::cancelled()->getMessage());
    }

    public function testNotEditableMessage(): void
    {
        self::assertStringContainsString('ne peut plus être modifiée', SessionException::notEditable()->getMessage());
    }

    public function testNotAvailablePassesThroughMessage(): void
    {
        self::assertSame('Quota dépassé.', SessionException::notAvailable('Quota dépassé.')->getMessage());
    }
}
