<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Domain\DocumentStatus;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the DocumentStatus enum: backing values, French labels and
 * badge classes. Pure logic, no DB.
 */
final class DocumentStatusTest extends TestCase
{
    public function testBackingValuesMatchColumn(): void
    {
        self::assertSame('PENDING', DocumentStatus::Pending->value);
        self::assertSame('READY', DocumentStatus::Ready->value);
        self::assertSame('FAILED', DocumentStatus::Failed->value);
    }

    public function testLabels(): void
    {
        self::assertSame('En traitement', DocumentStatus::Pending->label());
        self::assertSame('Indexé', DocumentStatus::Ready->label());
        self::assertSame('Texte non extrait', DocumentStatus::Failed->label());
    }

    public function testBadgeClassDerivesFromValue(): void
    {
        self::assertSame('badge-pending', DocumentStatus::Pending->badgeClass());
        self::assertSame('badge-ready', DocumentStatus::Ready->badgeClass());
        self::assertSame('badge-failed', DocumentStatus::Failed->badgeClass());
    }
}
