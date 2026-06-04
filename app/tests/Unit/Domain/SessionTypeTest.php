<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Domain\SessionType;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the SessionType enum. The pedagogical types were merged into
 * two: COURSE (ex-TD/TP/Étude libre) and EXAM.
 */
final class SessionTypeTest extends TestCase
{
    public function testOnlyTwoCases(): void
    {
        self::assertSame(['Course', 'Exam'], array_map(static fn (SessionType $t): string => $t->name, SessionType::cases()));
    }

    public function testBackingValuesMatchColumn(): void
    {
        self::assertSame('COURSE', SessionType::Course->value);
        self::assertSame('EXAM', SessionType::Exam->value);
    }

    public function testLabels(): void
    {
        self::assertSame('Cours', SessionType::Course->label());
        self::assertSame('Examen', SessionType::Exam->label());
    }

    public function testBadgeClass(): void
    {
        self::assertSame('badge-course', SessionType::Course->badgeClass());
        self::assertSame('badge-exam', SessionType::Exam->badgeClass());
    }

    public function testIsExamOnlyForExam(): void
    {
        self::assertTrue(SessionType::Exam->isExam());
        self::assertFalse(SessionType::Course->isExam());
    }
}
