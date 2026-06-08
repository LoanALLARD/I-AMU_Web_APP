<?php

declare(strict_types=1);

namespace Domain;

/**
 * Text from plain-text documents (TXT, Markdown) - a direct read.
 */
final class PlainTextExtractor implements TextExtractorInterface
{
    public function supports(string $mimeType): bool
    {
        return in_array($mimeType, ['text/plain', 'text/markdown'], true);
    }

    public function extract(string $absolutePath): string
    {
        $content = file_get_contents($absolutePath);
        if ($content === false) {
            throw DocumentException::extractionFailed();
        }
        return $content;
    }
}
