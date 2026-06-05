<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use DateTimeImmutable;
use Domain\Session;
use Domain\SessionException;
use Domain\SessionStatus;
use Domain\SessionType;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Session aggregate: lifecycle transitions, derived
 * status, available actions and access-code helpers. Pure logic, no DB.
 */
final class SessionTest extends TestCase
{
    private const NOW = '2026-06-03T11:00:00+02:00';

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    /**
     * Builds a Session from a default row, overridable per test. Going
     * through fromRow() also exercises the hydration path.
     *
     * @param array<string, mixed> $overrides
     */
    private function session(array $overrides = []): Session
    {
        return Session::fromRow(array_merge([
            'id'                   => 1,
            'resource_id'          => 7,
            'teacher_id'           => 3,
            'name'                 => 'Algo TD',
            'type'                 => 'COURSE',
            'status'               => 'SCHEDULED',
            'access_code'          => 'ABC123',
            'starts_at'            => '2026-06-03T10:00:00+02:00',
            'ends_at'              => '2026-06-03T12:00:00+02:00',
            'closed_at'            => null,
            'pre_prompt_override'  => null,
            'post_prompt_override' => null,
            'instructions'         => null,
            'max_input_size'       => null,
        ], $overrides));
    }

    // ----------------------------------------------------------------
    // Hydration / serialisation
    // ----------------------------------------------------------------

    public function testFromRowMapsScalarsAndEnums(): void
    {
        $session = $this->session([
            'type'           => 'EXAM',
            'status'         => 'DRAFT',
            'max_input_size' => 2000,
        ]);

        self::assertSame(1, $session->id());
        self::assertSame(7, $session->resourceId());
        self::assertSame(3, $session->teacherId());
        self::assertSame('Algo TD', $session->name());
        self::assertSame(SessionType::Exam, $session->type());
        self::assertSame(SessionStatus::Draft, $session->status());
        self::assertSame(2000, $session->maxInputSize());
    }

    public function testFromRowTreatsEmptyOptionalsAsNull(): void
    {
        $session = $this->session([
            'id'          => null,
            'teacher_id'  => null,
            'access_code' => '',
            'starts_at'   => null,
            'ends_at'     => '',
            'closed_at'   => null,
        ]);

        self::assertNull($session->id());
        self::assertNull($session->teacherId());
        self::assertNull($session->accessCode());
        self::assertNull($session->startsAt());
        self::assertNull($session->endsAt());
    }

    public function testToRowRoundTripsEnumValues(): void
    {
        $row = $this->session(['type' => 'COURSE', 'status' => 'ACTIVE'])->toRow();

        self::assertSame('COURSE', $row['type']);
        self::assertSame('ACTIVE', $row['status']);
        self::assertSame('ABC123', $row['access_code']);
        self::assertSame(7, $row['resource_id']);
    }

    // ----------------------------------------------------------------
    // assignId / assignAccessCode
    // ----------------------------------------------------------------

    public function testAssignIdSetsIdWhenUnset(): void
    {
        $session = $this->session(['id' => null]);
        $session->assignId(42);

        self::assertSame(42, $session->id());
    }

    public function testAssignIdRejectsConflictingId(): void
    {
        $session = $this->session(['id' => 1]);

        $this->expectException(LogicException::class);
        $session->assignId(2);
    }

    public function testAssignAccessCodeOverwritesCode(): void
    {
        $session = $this->session(['access_code' => null]);
        self::assertNull($session->accessCode());

        $session->assignAccessCode('XYZ789');
        self::assertSame('XYZ789', $session->accessCode());
    }

    // ----------------------------------------------------------------
    // Lifecycle: start()
    // ----------------------------------------------------------------

    public function testStartActivatesAndAnchorsFutureStart(): void
    {
        $session = $this->session([
            'status'    => 'SCHEDULED',
            'starts_at' => '2026-06-03T15:00:00+02:00',
        ]);

        $session->start($this->now());

        self::assertSame(SessionStatus::Active, $session->status());
        // A future start is pulled back to now so closed_at >= starts_at holds.
        self::assertEquals($this->now(), $session->startsAt());
    }

    public function testStartKeepsPastStart(): void
    {
        $past    = '2026-06-03T09:00:00+02:00';
        $session = $this->session(['status' => 'SCHEDULED', 'starts_at' => $past]);

        $session->start($this->now());

        self::assertSame(SessionStatus::Active, $session->status());
        self::assertEquals(new DateTimeImmutable($past), $session->startsAt());
    }

    public function testStartOnActiveThrows(): void
    {
        $session = $this->session(['status' => 'ACTIVE']);

        $this->expectException(SessionException::class);
        $this->expectExceptionMessage('déjà démarrée');
        $session->start($this->now());
    }

    public function testStartOnEndedThrows(): void
    {
        $session = $this->session(['status' => 'ENDED']);

        $this->expectException(SessionException::class);
        $this->expectExceptionMessage('déjà terminée');
        $session->start($this->now());
    }

    public function testStartOnCancelledThrows(): void
    {
        $session = $this->session(['status' => 'CANCELLED']);

        $this->expectException(SessionException::class);
        $this->expectExceptionMessage('annulée');
        $session->start($this->now());
    }

    // ----------------------------------------------------------------
    // Lifecycle: end()
    // ----------------------------------------------------------------

    public function testEndClosesActiveSession(): void
    {
        $session = $this->session([
            'status'    => 'ACTIVE',
            'starts_at' => '2026-06-03T09:00:00+02:00',
            'ends_at'   => null,
        ]);

        $session->end($this->now());

        self::assertSame(SessionStatus::Ended, $session->status());
        self::assertEquals($this->now(), $session->closedAt());
        self::assertEquals($this->now(), $session->endsAt());
    }

    public function testEndOnNeverStartedAnchorsStartAndBumpsEnd(): void
    {
        $session = $this->session([
            'status'    => 'DRAFT',
            'starts_at' => null,
            'ends_at'   => null,
        ]);

        $session->end($this->now());

        self::assertSame(SessionStatus::Ended, $session->status());
        self::assertEquals($this->now(), $session->startsAt());
        self::assertEquals($this->now(), $session->closedAt());
        // ends_at must be strictly after starts_at (DB invariant).
        self::assertEquals($this->now()->modify('+1 second'), $session->endsAt());
    }

    public function testEndOnEndedThrows(): void
    {
        $session = $this->session(['status' => 'ENDED']);

        $this->expectException(SessionException::class);
        $this->expectExceptionMessage('déjà terminée');
        $session->end($this->now());
    }

    public function testEndOnCancelledThrows(): void
    {
        $session = $this->session(['status' => 'CANCELLED']);

        $this->expectException(SessionException::class);
        $session->end($this->now());
    }

    // ----------------------------------------------------------------
    // Lifecycle: cancel()
    // ----------------------------------------------------------------

    public function testCancelMovesToCancelled(): void
    {
        $session = $this->session(['status' => 'SCHEDULED']);

        $session->cancel($this->now());

        self::assertSame(SessionStatus::Cancelled, $session->status());
        self::assertEquals($this->now(), $session->closedAt());
    }

    public function testCancelOnEndedThrows(): void
    {
        $session = $this->session(['status' => 'ENDED']);

        $this->expectException(SessionException::class);
        $this->expectExceptionMessage('déjà terminée');
        $session->cancel($this->now());
    }

    public function testCancelOnCancelledThrows(): void
    {
        $session = $this->session(['status' => 'CANCELLED']);

        $this->expectException(SessionException::class);
        $session->cancel($this->now());
    }

    // ----------------------------------------------------------------
    // Derived state: computedStatus()
    // ----------------------------------------------------------------

    public function testComputedStatusScheduledBeforeStartStaysScheduled(): void
    {
        $session = $this->session([
            'status'    => 'SCHEDULED',
            'starts_at' => '2026-06-03T15:00:00+02:00',
            'ends_at'   => '2026-06-03T17:00:00+02:00',
        ]);

        self::assertSame(SessionStatus::Scheduled, $session->computedStatus($this->now()));
    }

    public function testComputedStatusScheduledSpanningNowIsActive(): void
    {
        $session = $this->session([
            'status'    => 'SCHEDULED',
            'starts_at' => '2026-06-03T10:00:00+02:00',
            'ends_at'   => '2026-06-03T12:00:00+02:00',
        ]);

        self::assertSame(SessionStatus::Active, $session->computedStatus($this->now()));
        self::assertTrue($session->isActive($this->now()));
    }

    public function testComputedStatusScheduledPastEndIsEnded(): void
    {
        $session = $this->session([
            'status'    => 'SCHEDULED',
            'starts_at' => '2026-06-03T08:00:00+02:00',
            'ends_at'   => '2026-06-03T09:00:00+02:00',
        ]);

        self::assertSame(SessionStatus::Ended, $session->computedStatus($this->now()));
        self::assertFalse($session->isActive($this->now()));
    }

    public function testComputedStatusNonScheduledReturnsStoredStatus(): void
    {
        // A DRAFT with elapsed dates must not auto-promote: only SCHEDULED
        // sessions are time-derived.
        $session = $this->session([
            'status'    => 'DRAFT',
            'starts_at' => '2026-06-03T08:00:00+02:00',
            'ends_at'   => '2026-06-03T09:00:00+02:00',
        ]);

        self::assertSame(SessionStatus::Draft, $session->computedStatus($this->now()));
    }

    // ----------------------------------------------------------------
    // Derived state: canBeModified() / availableActions()
    // ----------------------------------------------------------------

    public function testDraftWithoutStartIsModifiable(): void
    {
        $session = $this->session(['status' => 'DRAFT', 'starts_at' => null, 'ends_at' => null]);

        self::assertTrue($session->canBeModified($this->now()));
    }

    public function testScheduledAfterStartIsNotModifiable(): void
    {
        $session = $this->session([
            'status'    => 'SCHEDULED',
            'starts_at' => '2026-06-03T09:00:00+02:00',
        ]);

        self::assertFalse($session->canBeModified($this->now()));
    }

    public function testAvailableActionsForDraft(): void
    {
        $session = $this->session(['status' => 'DRAFT', 'starts_at' => null, 'ends_at' => null]);

        self::assertSame(
            ['can_edit' => true, 'can_start' => true, 'can_end' => false, 'can_cancel' => true],
            $session->availableActions($this->now()),
        );
    }

    public function testAvailableActionsForActive(): void
    {
        $session = $this->session([
            'status'    => 'SCHEDULED',
            'starts_at' => '2026-06-03T10:00:00+02:00',
            'ends_at'   => '2026-06-03T12:00:00+02:00',
        ]);

        self::assertSame(
            ['can_edit' => false, 'can_start' => false, 'can_end' => true, 'can_cancel' => true],
            $session->availableActions($this->now()),
        );
    }

    public function testAvailableActionsForEnded(): void
    {
        $session = $this->session(['status' => 'ENDED']);

        self::assertSame(
            ['can_edit' => false, 'can_start' => false, 'can_end' => false, 'can_cancel' => false],
            $session->availableActions($this->now()),
        );
    }

    // ----------------------------------------------------------------
    // Mutators + guardEditable()
    // ----------------------------------------------------------------

    public function testRenameUpdatesNameWhenEditable(): void
    {
        $session = $this->session(['status' => 'DRAFT', 'starts_at' => null]);

        $session->rename('Nouveau nom', $this->now());

        self::assertSame('Nouveau nom', $session->name());
    }

    public function testRenameOnActiveThrowsNotEditable(): void
    {
        $session = $this->session([
            'status'    => 'SCHEDULED',
            'starts_at' => '2026-06-03T09:00:00+02:00',
        ]);

        $this->expectException(SessionException::class);
        $this->expectExceptionMessage('ne peut plus être modifiée');
        $session->rename('X', $this->now());
    }

    public function testRescheduleWithStartBecomesScheduled(): void
    {
        $session = $this->session(['status' => 'DRAFT', 'starts_at' => null, 'ends_at' => null]);

        $start = new DateTimeImmutable('2026-06-04T10:00:00+02:00');
        $end   = new DateTimeImmutable('2026-06-04T12:00:00+02:00');
        $session->reschedule($start, $end, $this->now());

        self::assertSame(SessionStatus::Scheduled, $session->status());
        self::assertEquals($start, $session->startsAt());
        self::assertEquals($end, $session->endsAt());
    }

    public function testRescheduleWithoutStartBecomesDraft(): void
    {
        $session = $this->session(['status' => 'SCHEDULED', 'starts_at' => null, 'ends_at' => null]);

        $session->reschedule(null, null, $this->now());

        self::assertSame(SessionStatus::Draft, $session->status());
    }

    public function testRescheduleRejectsEndBeforeStart(): void
    {
        $session = $this->session(['status' => 'DRAFT', 'starts_at' => null, 'ends_at' => null]);

        $start = new DateTimeImmutable('2026-06-04T12:00:00+02:00');
        $end   = new DateTimeImmutable('2026-06-04T10:00:00+02:00');

        $this->expectException(InvalidArgumentException::class);
        $session->reschedule($start, $end, $this->now());
    }

    public function testReconfigureRejectsNonPositiveLimit(): void
    {
        $session = $this->session(['status' => 'DRAFT', 'starts_at' => null]);

        $this->expectException(InvalidArgumentException::class);
        $session->reconfigure(null, null, null, 0, $this->now());
    }

    public function testReconfigureStoresOverrides(): void
    {
        $session = $this->session(['status' => 'DRAFT', 'starts_at' => null]);

        $session->reconfigure('pre', 'post', 'do this', 1500, $this->now());

        self::assertSame('pre', $session->prePromptOverride());
        self::assertSame('post', $session->postPromptOverride());
        self::assertSame('do this', $session->instructions());
        self::assertSame(1500, $session->maxInputSize());
    }

    // ----------------------------------------------------------------
    // Access code helpers
    // ----------------------------------------------------------------

    public function testFormatAccessCodeInsertsSeparator(): void
    {
        self::assertSame('ABC-123', Session::formatAccessCode('ABC123'));
    }

    public function testFormatAccessCodeLeavesNonSixCharsUntouched(): void
    {
        self::assertSame('SHORT', Session::formatAccessCode('SHORT'));
    }

    public function testAccessCodeFormattedAccessor(): void
    {
        self::assertSame('ABC-123', $this->session(['access_code' => 'ABC123'])->accessCodeFormatted());
        self::assertNull($this->session(['access_code' => null])->accessCodeFormatted());
    }

    public function testNormalizeAccessCodeStripsAndUppercases(): void
    {
        self::assertSame('A7K29B', Session::normalizeAccessCode('a7k-29b'));
        self::assertSame('A7K29B', Session::normalizeAccessCode('  a7k 29b  '));
    }
}
