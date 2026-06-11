<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Models\ResearcherAnalyticsRepository;
use Models\ResearcherAuthorizationRepository;
use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Services\ResearcherAnalyticsService;

/**
 * Unit tests for the researcher export() path. The repositories are mocked, so
 * these cover the service's own logic: scope validation (anti-IDOR), the
 * default "whole perimeter" scope, and the export-time pseudonymisation (no
 * direct identifier, stable opaque token). No DB.
 */
final class ResearcherAnalyticsServiceTest extends TestCase
{
    /** @var ResearcherAnalyticsRepository&MockObject */
    private ResearcherAnalyticsRepository $analytics;

    /** @var ResearcherAuthorizationRepository&MockObject */
    private ResearcherAuthorizationRepository $auth;

    private ResearcherAnalyticsService $service;

    protected function setUp(): void
    {
        $this->analytics = $this->createMock(ResearcherAnalyticsRepository::class);
        $this->auth      = $this->createMock(ResearcherAuthorizationRepository::class);
        $this->service   = new ResearcherAnalyticsService(
            $this->createStub(PDO::class),
            $this->analytics,
            $this->auth
        );
    }

    /**
     * Two active grants (departments 1 and 2 on one campus), as the auth repo
     * returns them. Used to drive both the scope check and the labels.
     *
     * @return list<array<string, mixed>>
     */
    private static function activeGrants(): array
    {
        return [
            ['place_id' => 1, 'place_name' => 'Campus A', 'department_id' => 1, 'department_name' => 'Informatique'],
            ['place_id' => 1, 'place_name' => 'Campus A', 'department_id' => 2, 'department_name' => 'Mathematiques'],
        ];
    }

    /**
     * A raw export row as the analytics repo returns it (DB shape).
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function rawRow(int $studentId, array $overrides = []): array
    {
        return array_merge([
            'place_name'          => 'Campus A',
            'department_name'     => 'Informatique',
            'session_id'          => 100,
            'session_name'        => 'Algo TD',
            'session_access_code' => 'ABC123',
            'student_id'          => $studentId,
            'conversation_id'     => 500,
            'conversation_name'   => 'Conv',
            'conversation_created'=> '2026-01-01T10:00:00+00:00',
            'is_archived'         => false,
            'interaction_id'      => 900,
            'prompt'              => 'question',
            'response'            => 'reponse',
            'model_name'          => 'llama3.2:1b',
            'input_tokens'        => 12,
            'output_tokens'       => 34,
            'latency'             => 250,
            'user_feedback'       => 1,
            'sent_at'             => '2026-01-01T10:05:00+00:00',
        ], $overrides);
    }

    /**
     * Wraps fixture rows in a Generator, matching exportRows()'s streaming
     * return type (the repository yields rows, it no longer returns an array).
     *
     * @param list<array<string, mixed>> $rows
     * @return \Generator<int, array<string, mixed>>
     */
    private static function rowsGenerator(array $rows): \Generator
    {
        yield from $rows;
    }

    public function testNoActiveGrantReturnsError(): void
    {
        $this->auth->method('findActiveByResearcher')->willReturn([]);
        $this->analytics->expects(self::never())->method('exportRows');

        $result = $this->service->export(13, []);

        self::assertFalse($result['success']);
        self::assertStringContainsString('aucun département', mb_strtolower($result['error']));
    }

    public function testEmptyRequestExportsTheWholePerimeter(): void
    {
        $this->auth->method('findActiveByResearcher')->willReturn(self::activeGrants());
        $this->analytics->expects(self::once())
            ->method('exportRows')
            ->with([1, 2])
            ->willReturn(self::rowsGenerator([]));

        $result = $this->service->export(13, []);

        self::assertTrue($result['success']);
        // rows is a lazy Generator: draining it drives exportRows([1, 2]).
        self::assertSame([], iterator_to_array($result['rows']));
    }

    public function testValidSubsetScopesToRequestedDepartments(): void
    {
        $this->auth->method('findActiveByResearcher')->willReturn(self::activeGrants());
        $this->analytics->expects(self::once())
            ->method('exportRows')
            ->with([1])
            ->willReturn(self::rowsGenerator([]));

        $result = $this->service->export(13, [1]);

        self::assertTrue($result['success']);
        self::assertSame([['place' => 'Campus A', 'department' => 'Informatique']], $result['scope']);
        // Drain the lazy rows so the exportRows([1]) expectation is met.
        self::assertSame([], iterator_to_array($result['rows']));
    }

    public function testAntiIdorRejectsAnUngrantedDepartment(): void
    {
        $this->auth->method('findActiveByResearcher')->willReturn(self::activeGrants());
        $this->analytics->expects(self::never())->method('exportRows');

        $result = $this->service->export(13, [1, 99]);

        self::assertFalse($result['success']);
        self::assertStringContainsString('autoris', mb_strtolower($result['error']));
    }

    public function testRowsArePseudonymisedWithNoDirectIdentifier(): void
    {
        $this->auth->method('findActiveByResearcher')->willReturn(self::activeGrants());
        $this->analytics->method('exportRows')->willReturn(self::rowsGenerator([self::rawRow(9)]));

        $result = $this->service->export(13, [1]);

        self::assertTrue($result['success']);
        $row = iterator_to_array($result['rows'])[0];

        self::assertArrayHasKey('student', $row);
        self::assertMatchesRegularExpression('/^etudiant-[0-9a-f]+$/', $row['student']);
        foreach (['student_id', 'first_name', 'last_name', 'email', 'student_number'] as $leak) {
            self::assertArrayNotHasKey($leak, $row);
        }
    }

    public function testPseudonymIsStablePerStudentAndDistinctAcrossStudents(): void
    {
        $this->auth->method('findActiveByResearcher')->willReturn(self::activeGrants());
        $this->analytics->method('exportRows')->willReturn(self::rowsGenerator([
            self::rawRow(9, ['interaction_id' => 901]),
            self::rawRow(9, ['interaction_id' => 902]),
            self::rawRow(10, ['interaction_id' => 903]),
        ]));

        $rows = iterator_to_array($this->service->export(13, [1])['rows']);

        // Same student -> same token (grouping preserved).
        self::assertSame($rows[0]['student'], $rows[1]['student']);
        // Different student -> different token.
        self::assertNotSame($rows[0]['student'], $rows[2]['student']);
    }

    public function testShapedRowMapsAndCastsFields(): void
    {
        $this->auth->method('findActiveByResearcher')->willReturn(self::activeGrants());
        $this->analytics->method('exportRows')->willReturn(self::rowsGenerator([
            self::rawRow(9, ['is_archived' => true, 'latency' => null, 'session_access_code' => null]),
        ]));

        $row = iterator_to_array($this->service->export(13, [1])['rows'])[0];

        self::assertSame('Campus A', $row['campus']);
        self::assertSame('Informatique', $row['department']);
        self::assertSame(100, $row['session_id']);
        self::assertNull($row['session_code']);
        self::assertTrue($row['is_archived']);
        self::assertSame(12, $row['input_tokens']);
        self::assertNull($row['latency_ms']);
        self::assertSame(1, $row['user_feedback']);
    }
}
