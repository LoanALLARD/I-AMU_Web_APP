<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use DateTimeImmutable;
use Domain\Document;
use Domain\DocumentStatus;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Document entity: row hydration, optional fields and the
 * display helpers (human size, kind label). Pure logic, no DB.
 */
final class DocumentTest extends TestCase
{
    /**
     * @param array<string, mixed> $overrides
     */
    private function doc(array $overrides = []): Document
    {
        return Document::fromRow(array_merge([
            'id'              => 5,
            'session_id'      => 3,
            'conversation_id' => null,
            'uploaded_by_id'  => 7,
            'original_name'   => 'consignes.pdf',
            'stored_path'     => 'session_3/abcdef.pdf',
            'mime_type'       => 'application/pdf',
            'size_bytes'      => 2_500_000,
            'extracted_text'  => 'hello',
            'status'          => 'READY',
            'created_at'      => '2026-06-07T10:00:00+02:00',
        ], $overrides));
    }

    public function testFromRowMapsScalarsAndEnum(): void
    {
        $d = $this->doc();

        self::assertSame(5, $d->id());
        self::assertSame(3, $d->sessionId());
        self::assertNull($d->conversationId());
        self::assertSame(7, $d->uploadedById());
        self::assertSame('consignes.pdf', $d->originalName());
        self::assertSame('session_3/abcdef.pdf', $d->storedPath());
        self::assertSame('application/pdf', $d->mimeType());
        self::assertSame(2_500_000, $d->sizeBytes());
        self::assertSame('hello', $d->extractedText());
        self::assertSame(DocumentStatus::Ready, $d->status());
        self::assertInstanceOf(DateTimeImmutable::class, $d->createdAt());
    }

    public function testFromRowTreatsEmptyOptionalsAsNull(): void
    {
        $d = $this->doc([
            'session_id'      => null,
            'conversation_id' => 9,
            'extracted_text'  => null,
            'created_at'      => null,
        ]);

        self::assertNull($d->sessionId());
        self::assertSame(9, $d->conversationId());
        self::assertNull($d->extractedText());
        self::assertNull($d->createdAt());
    }

    public function testHumanSize(): void
    {
        self::assertSame('512 o', $this->doc(['size_bytes' => 512])->humanSize());
        self::assertSame('2 Ko', $this->doc(['size_bytes' => 2048])->humanSize());
        self::assertSame('2,4 Mo', $this->doc(['size_bytes' => 2_500_000])->humanSize());
    }

    public function testKindLabel(): void
    {
        self::assertSame('PDF', $this->doc(['mime_type' => 'application/pdf'])->kindLabel());
        self::assertSame('Markdown', $this->doc(['mime_type' => 'text/markdown'])->kindLabel());
        self::assertSame('Texte', $this->doc(['mime_type' => 'text/plain'])->kindLabel());
    }
}
