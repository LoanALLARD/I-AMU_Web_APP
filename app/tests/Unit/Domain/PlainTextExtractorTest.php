<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Domain\DocumentException;
use Domain\PlainTextExtractor;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PlainTextExtractor: mime support and the direct file read.
 */
final class PlainTextExtractorTest extends TestCase
{
    public function testSupportsPlainAndMarkdownOnly(): void
    {
        $extractor = new PlainTextExtractor();

        self::assertTrue($extractor->supports('text/plain'));
        self::assertTrue($extractor->supports('text/markdown'));
        self::assertFalse($extractor->supports('application/pdf'));
        self::assertFalse($extractor->supports('text/html'));
    }

    public function testExtractReturnsFileContents(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'iamu_txt_');
        file_put_contents($path, "# Titre\nLigne 1\n");

        try {
            self::assertSame("# Titre\nLigne 1\n", (new PlainTextExtractor())->extract($path));
        } finally {
            @unlink($path);
        }
    }

    public function testExtractThrowsWhenFileUnreadable(): void
    {
        $this->expectException(DocumentException::class);

        // file_get_contents emits an E_WARNING on a missing path; swallow it so
        // the suite stays clean while we assert the thrown DocumentException.
        set_error_handler(static fn (): bool => true);
        try {
            (new PlainTextExtractor())->extract('/no/such/iamu/file.txt');
        } finally {
            restore_error_handler();
        }
    }
}
