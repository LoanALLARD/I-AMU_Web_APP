<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Core\HttpException;
use Core\Router;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the minimal Router: literal matching, {placeholder} capture
 * and ordering, end anchoring, method handling, query-string stripping and the
 * 404 fallback. Pure logic, no HTTP.
 */
final class RouterTest extends TestCase
{
    public function testLiteralRouteInvokesItsCallback(): void
    {
        $hit = false;
        $router = new Router();
        $router->add('GET', '/health', function () use (&$hit): void {
            $hit = true;
        });

        $router->dispatch('/health', 'GET');

        self::assertTrue($hit);
    }

    public function testSinglePlaceholderIsPassedToCallback(): void
    {
        $captured = null;
        $router = new Router();
        $router->add('GET', '/sessions/{id}', function ($id) use (&$captured): void {
            $captured = $id;
        });

        $router->dispatch('/sessions/42', 'GET');

        self::assertSame('42', $captured);
    }

    public function testMultiplePlaceholdersArePassedInDeclarationOrder(): void
    {
        $args = [];
        $router = new Router();
        $router->add('GET', '/places/{placeId}/departments/{deptId}', function ($a, $b) use (&$args): void {
            $args = [$a, $b];
        });

        $router->dispatch('/places/7/departments/3', 'GET');

        self::assertSame(['7', '3'], $args);
    }

    public function testQueryStringIsStrippedBeforeMatching(): void
    {
        $captured = null;
        $router = new Router();
        $router->add('GET', '/sessions/{id}', function ($id) use (&$captured): void {
            $captured = $id;
        });

        $router->dispatch('/sessions/5?format=json&x=1', 'GET');

        self::assertSame('5', $captured);
    }

    public function testEndAnchoringPreventsPrefixMatch(): void
    {
        $hit = false;
        $router = new Router();
        $router->add('GET', '/sessions', function () use (&$hit): void {
            $hit = true;
        });

        // "/sessions" must not match "/sessions/12".
        try {
            $router->dispatch('/sessions/12', 'GET');
            self::fail('Expected HttpException for an unmatched longer path.');
        } catch (HttpException $e) {
            self::assertSame(404, $e->statusCode());
        }
        self::assertFalse($hit);
    }

    public function testPlaceholderDoesNotSpanSlashes(): void
    {
        $hit = false;
        $router = new Router();
        $router->add('GET', '/sessions/{id}', function () use (&$hit): void {
            $hit = true;
        });

        // {id} is [^/]+, so it cannot swallow the extra "/edit" segment.
        try {
            $router->dispatch('/sessions/42/edit', 'GET');
            self::fail('Expected HttpException: {id} must not match across a slash.');
        } catch (HttpException $e) {
            self::assertSame(404, $e->statusCode());
        }
        self::assertFalse($hit);
    }

    public function testMethodMismatchDoesNotMatch(): void
    {
        $hit = false;
        $router = new Router();
        $router->add('POST', '/sessions', function () use (&$hit): void {
            $hit = true;
        });

        try {
            $router->dispatch('/sessions', 'GET');
            self::fail('Expected HttpException for a method mismatch.');
        } catch (HttpException $e) {
            self::assertSame(404, $e->statusCode());
        }
        self::assertFalse($hit);
    }

    public function testMethodMatchingIsCaseInsensitive(): void
    {
        $hit = false;
        $router = new Router();
        $router->add('get', '/ping', function () use (&$hit): void {
            $hit = true;
        });

        $router->dispatch('/ping', 'get');

        self::assertTrue($hit);
    }

    public function testFirstMatchingRouteWinsAndStops(): void
    {
        $calls = [];
        $router = new Router();
        $router->add('GET', '/x', function () use (&$calls): void {
            $calls[] = 'first';
        });
        $router->add('GET', '/x', function () use (&$calls): void {
            $calls[] = 'second';
        });

        $router->dispatch('/x', 'GET');

        self::assertSame(['first'], $calls);
    }

    public function testUnmatchedPathThrows404(): void
    {
        $router = new Router();
        $router->add('GET', '/known', static fn () => null);

        try {
            $router->dispatch('/unknown', 'GET');
            self::fail('Expected HttpException for an unknown path.');
        } catch (HttpException $e) {
            self::assertSame(404, $e->statusCode());
            self::assertNotSame('', $e->getMessage());
        }
    }
}
