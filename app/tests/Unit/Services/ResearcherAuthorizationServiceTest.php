<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Models\PlaceRepository;
use Models\ResearcherAuthorizationRepository;
use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Services\ResearcherAuthorizationService;

/**
 * Unit tests for ResearcherAuthorizationService: the derived request status
 * (the timestamp pair -> pending/authorized/rejected/revoked rule) and the
 * validation branches of requestAccess() / cancelRequest(). Repositories are
 * mocked; no DB.
 */
final class ResearcherAuthorizationServiceTest extends TestCase
{
    /** @var ResearcherAuthorizationRepository&MockObject */
    private ResearcherAuthorizationRepository $repo;

    /** @var PlaceRepository&MockObject */
    private PlaceRepository $places;

    private ResearcherAuthorizationService $service;

    protected function setUp(): void
    {
        $this->repo    = $this->createMock(ResearcherAuthorizationRepository::class);
        $this->places  = $this->createMock(PlaceRepository::class);
        $this->service = new ResearcherAuthorizationService(
            $this->createStub(PDO::class),
            $this->repo,
            $this->places
        );
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function request(array $overrides = []): array
    {
        return array_merge([
            'department_id'   => 1,
            'request'         => 'Pour ma these.',
            'authorized_at'   => null,
            'rejected_at'     => null,
            'department_name' => 'Informatique',
            'place_name'      => 'Campus A',
        ], $overrides);
    }

    // ----------------------------------------------------------------
    // listForResearcher() -> status derivation
    // ----------------------------------------------------------------

    public function testDerivesEveryStatusFromTheTimestampPair(): void
    {
        $this->repo->method('findByResearcher')->willReturn([
            self::request(['authorized_at' => null,                 'rejected_at' => null]),                 // pending
            self::request(['authorized_at' => '2026-01-02 10:00',   'rejected_at' => null]),                 // authorized
            self::request(['authorized_at' => null,                 'rejected_at' => '2026-01-02 10:00']),   // rejected
            self::request(['authorized_at' => '2026-01-01 10:00',   'rejected_at' => '2026-01-03 10:00']),   // revoked
            self::request(['authorized_at' => '2026-01-05 10:00',   'rejected_at' => '2026-01-03 10:00']),   // re-authorized
        ]);

        $statuses = array_map(
            static fn (array $r): string => $r['status'],
            $this->service->listForResearcher(7)
        );

        self::assertSame(['pending', 'authorized', 'rejected', 'revoked', 'authorized'], $statuses);
    }

    // ----------------------------------------------------------------
    // requestAccess() -> validation branches
    // ----------------------------------------------------------------

    public function testRequestAccessRejectsMissingPlaceOrDepartment(): void
    {
        $this->repo->expects(self::never())->method('submitRequest');

        self::assertFalse($this->service->requestAccess(7, 0, 1, 'x')['success']);
        self::assertFalse($this->service->requestAccess(7, 1, 0, 'x')['success']);
    }

    public function testRequestAccessRejectsDepartmentNotInPlace(): void
    {
        $this->places->method('departmentBelongsToPlace')->willReturn(false);
        $this->repo->expects(self::never())->method('submitRequest');

        $result = $this->service->requestAccess(7, 1, 2, 'x');

        self::assertFalse($result['success']);
        self::assertStringContainsString('invalide', mb_strtolower($result['error']));
    }

    public function testRequestAccessRejectsWhenAlreadyAuthorised(): void
    {
        $this->places->method('departmentBelongsToPlace')->willReturn(true);
        $this->repo->method('isAuthorized')->willReturn(true);
        $this->repo->expects(self::never())->method('submitRequest');

        $result = $this->service->requestAccess(7, 1, 2, 'x');

        self::assertFalse($result['success']);
        self::assertStringContainsString('actif', mb_strtolower($result['error']));
    }

    public function testRequestAccessRejectsDuplicatePendingRequest(): void
    {
        $this->places->method('departmentBelongsToPlace')->willReturn(true);
        $this->repo->method('isAuthorized')->willReturn(false);
        $this->repo->method('findPendingDepartmentId')->willReturn(2);
        $this->repo->expects(self::never())->method('submitRequest');

        $result = $this->service->requestAccess(7, 1, 2, 'x');

        self::assertFalse($result['success']);
        self::assertStringContainsString('attente', mb_strtolower($result['error']));
    }

    public function testRequestAccessHappyPathTrimsAndSubmits(): void
    {
        $this->places->method('departmentBelongsToPlace')->willReturn(true);
        $this->repo->method('isAuthorized')->willReturn(false);
        $this->repo->method('findPendingDepartmentId')->willReturn(null);
        $this->repo->expects(self::once())
            ->method('submitRequest')
            ->with(7, 2, 'Bonjour');

        $result = $this->service->requestAccess(7, 1, 2, '  Bonjour  ');

        self::assertTrue($result['success']);
    }

    public function testRequestAccessStoresNullForAnEmptyMessage(): void
    {
        $this->places->method('departmentBelongsToPlace')->willReturn(true);
        $this->repo->method('isAuthorized')->willReturn(false);
        $this->repo->method('findPendingDepartmentId')->willReturn(null);
        $this->repo->expects(self::once())
            ->method('submitRequest')
            ->with(7, 2, null);

        $this->service->requestAccess(7, 1, 2, '   ');
    }

    // ----------------------------------------------------------------
    // cancelRequest()
    // ----------------------------------------------------------------

    public function testCancelRequestRejectsMissingDepartment(): void
    {
        $this->repo->expects(self::never())->method('cancelPending');

        self::assertFalse($this->service->cancelRequest(7, 0)['success']);
    }

    public function testCancelRequestFailsWhenNothingPending(): void
    {
        $this->repo->method('cancelPending')->willReturn(0);

        self::assertFalse($this->service->cancelRequest(7, 2)['success']);
    }

    public function testCancelRequestSucceedsWhenARowIsDeleted(): void
    {
        $this->repo->method('cancelPending')->willReturn(1);

        self::assertTrue($this->service->cancelRequest(7, 2)['success']);
    }
}
