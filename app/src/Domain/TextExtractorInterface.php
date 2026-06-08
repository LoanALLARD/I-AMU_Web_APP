<?php

declare(strict_types=1);

namespace Domain;

/**
 * Extracts plain text from an uploaded document, so it can later feed the
 * prompt (phase 2) or be chunked + embedded (phase 3 RAG). One implementation
 * per family of MIME types.
 */
interface TextExtractorInterface
{
    public function supports(string $mimeType): bool;

    /**
     * Returns the document's plain text. Throws {@see DocumentException} when
     * extraction fails (missing binary, unreadable/corrupt file…).
     */
    public function extract(string $absolutePath): string;
}
