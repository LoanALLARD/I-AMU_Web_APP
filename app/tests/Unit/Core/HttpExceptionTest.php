<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Core\HttpException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for HttpException: it carries an HTTP status code alongside the
 * usual exception message / previous chain. Pure logic, no DB.
 */
final class HttpExceptionTest extends TestCase
{
    public function testStatusCodeIsExposed(): void
    {
        self::assertSame(404, (new HttpException(404))->statusCode());
        self::assertSame(403, (new HttpException(403))->statusCode());
    }

    public function testMessageDefaultsToEmpty(): void
    {
        self::assertSame('', (new HttpException(500))->getMessage());
    }

    public function testCarriesMessage(): void
    {
        $e = new HttpException(403, 'Accès refusé.');

        self::assertSame('Accès refusé.', $e->getMessage());
        self::assertSame(403, $e->statusCode());
    }

    public function testWrapsPreviousThrowable(): void
    {
        $previous = new RuntimeException('root cause');
        $e        = new HttpException(500, 'boom', $previous);

        self::assertSame($previous, $e->getPrevious());
    }

    public function testIsRuntimeException(): void
    {
        self::assertInstanceOf(RuntimeException::class, new HttpException(404));
    }
}
