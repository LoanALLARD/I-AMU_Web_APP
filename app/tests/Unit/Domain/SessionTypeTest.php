<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Domain\SessionType;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the SessionType enum: French labels, badge classes and the
 * exam predicate. FREE_STUDY exercises the underscore-to-dash badge mapping.
 */
final class SessionTypeTest extends TestCase
{
    public function testBackingValuesMatchColumn(): void
    {
        self::assertSame('EXAM', SessionType::Exam->value);
        self::assertSame('TUTORIAL', SessionType::Tutorial->value);
        self::assertSame('LAB', SessionType::Lab->value);
        self::assertSame('FREE_STUDY', SessionType::FreeStudy->value);
    }

    public function testLabels(): void
    {
        self::assertSame('Examen', SessionType::Exam->label());
        self::assertSame('TD', SessionType::Tutorial->label());
        self::assertSame('TP', SessionType::Lab->label());
        self::assertSame('Étude libre', SessionType::FreeStudy->label());
    }

    public function testBadgeClassReplacesUnderscoreWithDash(): void
    {
        self::assertSame('badge-exam', SessionType::Exam->badgeClass());
        self::assertSame('badge-free-study', SessionType::FreeStudy->badgeClass());
    }

    public function testIsExamOnlyForExam(): void
    {
        self::assertTrue(SessionType::Exam->isExam());

        self::assertFalse(SessionType::Tutorial->isExam());
        self::assertFalse(SessionType::Lab->isExam());
        self::assertFalse(SessionType::FreeStudy->isExam());
    }
}
