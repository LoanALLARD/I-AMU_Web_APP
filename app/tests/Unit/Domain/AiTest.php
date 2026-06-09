<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Domain\Ai;
use Domain\LlmAdaptaterInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Ai entity: value accessors and the ask() delegation to
 * the injected LLM adapter. The adapter is a mock, so no network / no DB.
 */
final class AiTest extends TestCase
{
    private function ai(LlmAdaptaterInterface $adapter): Ai
    {
        return new Ai(
            id: 12,
            department_id: 3,
            resource_id: null,
            name: 'llama3.2:1b',
            size: '1B',
            provider: 'Meta',
            context_window: '4096',
            is_active: true,
            is_shareable: false,
            url: 'http://ollama:11434/api/generate',
            adaptater: $adapter,
        );
    }

    public function testAccessorsExposeConstructorValues(): void
    {
        $ai = $this->ai($this->createMock(LlmAdaptaterInterface::class));

        self::assertSame(12, $ai->getId());
        self::assertSame(3, $ai->getDepartmentId());
        self::assertNull($ai->getResourceId());
        self::assertSame('llama3.2:1b', $ai->getName());
        self::assertSame('1B', $ai->getSize());
        self::assertSame('Meta', $ai->getInfoCompagny());
        self::assertSame('4096', $ai->getInfoContextWindow());
        self::assertTrue($ai->isActive());
        self::assertFalse($ai->isShareable());
        self::assertSame('http://ollama:11434/api/generate', $ai->getUrl());
    }

    public function testAskDelegatesToAdapterAndReturnsItsAnswer(): void
    {
        $context = [128006, 9125];

        $adapter = $this->createMock(LlmAdaptaterInterface::class);
        // ask(message, context, postprompt, preprompt)
        //   -> generate(message, context, postprompt, preprompt, null)
        $adapter->expects(self::once())
            ->method('generate')
            ->with('Quelle heure est-il ?', $context, 'POST', 'PRE', null)
            ->willReturn('Il est midi.');

        $ai = $this->ai($adapter);

        self::assertSame(
            'Il est midi.',
            $ai->ask('Quelle heure est-il ?', $context, 'POST', 'PRE'),
        );
    }
}
