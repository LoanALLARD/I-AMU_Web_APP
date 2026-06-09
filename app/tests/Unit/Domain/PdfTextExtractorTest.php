<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Domain\PdfTextExtractor;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PdfTextExtractor mime routing. extract() shells out to the
 * `pdftotext` binary and is therefore out of scope for a pure unit test.
 */
final class PdfTextExtractorTest extends TestCase
{
    public function testSupportsPdfOnly(): void
    {
        $extractor = new PdfTextExtractor();

        self::assertTrue($extractor->supports('application/pdf'));
        self::assertFalse($extractor->supports('text/plain'));
        self::assertFalse($extractor->supports('text/markdown'));
        self::assertFalse($extractor->supports('image/png'));
    }
}
