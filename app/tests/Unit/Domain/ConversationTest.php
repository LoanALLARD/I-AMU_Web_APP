<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Domain\Conversation;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Conversation value object: it is immutable and exposes
 * exactly the values passed to its constructor. Pure logic, no DB.
 */
final class ConversationTest extends TestCase
{
    public function testExposesConstructorValues(): void
    {
        $conversation = new Conversation(7, 3, 'SESSION - ABC-123 #1');

        self::assertSame(7, $conversation->userId());
        self::assertSame(3, $conversation->sessionId());
        self::assertSame('SESSION - ABC-123 #1', $conversation->name());
    }
}
