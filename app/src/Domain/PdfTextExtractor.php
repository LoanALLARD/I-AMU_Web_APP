<?php

declare(strict_types=1);

namespace Domain;

/**
 * Text from a PDF via the `pdftotext` CLI (poppler-utils). When the binary is
 * absent or the conversion yields nothing, throws - the caller then stores the
 * document with status FAILED while keeping the file available for download.
 */
final class PdfTextExtractor implements TextExtractorInterface
{
    public function supports(string $mimeType): bool
    {
        return $mimeType === 'application/pdf';
    }

    public function extract(string $absolutePath): string
    {
        $output = shell_exec('pdftotext ' . escapeshellarg($absolutePath) . ' - 2>/dev/null');
        if ($output === null || $output === false || trim($output) === '') {
            throw DocumentException::extractionFailed();
        }
        return $output;
    }
}
