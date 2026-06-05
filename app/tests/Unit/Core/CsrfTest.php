<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Core\Csrf;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the anti-CSRF token helper. Drives the $_SESSION / $_POST
 * superglobals directly (no real session is started in CLI). Pure logic.
 */
final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_POST    = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST    = [];
    }

    public function testGenerateTokenCreatesHexTokenStoredInSession(): void
    {
        $token = Csrf::generateToken();

        // 32 random bytes -> 64 hex chars.
        self::assertSame(64, strlen($token));
        self::assertSame(1, preg_match('/^[0-9a-f]{64}$/', $token));
        self::assertSame($token, $_SESSION['csrf_token']);
    }

    public function testGenerateTokenIsStableWithinSession(): void
    {
        $first  = Csrf::generateToken();
        $second = Csrf::generateToken();

        self::assertSame($first, $second);
    }

    public function testRotateReplacesToken(): void
    {
        $original = Csrf::generateToken();
        $rotated  = Csrf::rotate();

        self::assertNotSame($original, $rotated);
        self::assertSame($rotated, $_SESSION['csrf_token']);
    }

    public function testFieldEmbedsCurrentTokenAsHiddenInput(): void
    {
        $token = Csrf::generateToken();
        $field = Csrf::field();

        self::assertStringContainsString('type="hidden"', $field);
        self::assertStringContainsString('name="_csrf_token"', $field);
        self::assertStringContainsString('value="' . $token . '"', $field);
    }

    public function testVerifyAcceptsMatchingToken(): void
    {
        $token = Csrf::generateToken();

        self::assertTrue(Csrf::verify($token));
    }

    public function testVerifyRejectsWrongToken(): void
    {
        Csrf::generateToken();

        self::assertFalse(Csrf::verify('not-the-token'));
    }

    public function testVerifyFallsBackToPostField(): void
    {
        $token                  = Csrf::generateToken();
        $_POST['_csrf_token']   = $token;

        self::assertTrue(Csrf::verify());
    }

    public function testVerifyFailsWithoutSessionToken(): void
    {
        self::assertFalse(Csrf::verify('anything'));
    }

    public function testVerifyFailsWithEmptySubmittedToken(): void
    {
        Csrf::generateToken();

        self::assertFalse(Csrf::verify(''));
    }
}
