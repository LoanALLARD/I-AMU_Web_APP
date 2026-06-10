<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use Domain\DocumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for the DocumentException named factories. Each factory must
 * produce a RuntimeException carrying the exact French message the controller
 * flashes, and the parametrised ones must interpolate their number.
 */
final class DocumentExceptionTest extends TestCase
{
    public function testIsARuntimeException(): void
    {
        self::assertInstanceOf(RuntimeException::class, DocumentException::noFile());
    }

    public function testNoFileMessage(): void
    {
        self::assertSame('Aucun fichier reçu.', DocumentException::noFile()->getMessage());
    }

    public function testUploadFailedMessage(): void
    {
        self::assertSame('Le téléversement du fichier a échoué.', DocumentException::uploadFailed()->getMessage());
    }

    public function testUnsupportedTypeMessage(): void
    {
        self::assertSame(
            'Type de fichier non supporté (PDF, Markdown ou TXT uniquement).',
            DocumentException::unsupportedType()->getMessage()
        );
    }

    public function testTooLargeInterpolatesTheLimit(): void
    {
        self::assertSame(
            'Le fichier dépasse la taille maximale de 5 Mo.',
            DocumentException::tooLarge(5)->getMessage()
        );
    }

    public function testQuotaReachedInterpolatesTheLimit(): void
    {
        self::assertSame(
            'Cette session a atteint la limite de 10 documents.',
            DocumentException::quotaReached(10)->getMessage()
        );
    }

    public function testQuotaReachedConversationInterpolatesTheLimit(): void
    {
        self::assertSame(
            'Cette conversation a atteint la limite de 3 documents.',
            DocumentException::quotaReachedConversation(3)->getMessage()
        );
    }

    public function testExamImportDisabledMessage(): void
    {
        self::assertSame(
            "L'import de documents est désactivé pendant un examen.",
            DocumentException::examImportDisabled()->getMessage()
        );
    }

    public function testDocumentsDisabledMessage(): void
    {
        self::assertSame(
            "L'import de documents est désactivé pour cette session.",
            DocumentException::documentsDisabled()->getMessage()
        );
    }

    public function testTypeNotAllowedMessage(): void
    {
        self::assertSame(
            "Ce type de fichier n'est pas autorisé pour cette session.",
            DocumentException::typeNotAllowed()->getMessage()
        );
    }

    public function testNotFoundMessage(): void
    {
        self::assertSame('Document introuvable.', DocumentException::notFound()->getMessage());
    }

    public function testForbiddenMessage(): void
    {
        self::assertSame("Vous n'avez pas accès à ce document.", DocumentException::forbidden()->getMessage());
    }

    public function testExtractionFailedMessage(): void
    {
        self::assertSame(
            "Le texte du document n'a pas pu être extrait.",
            DocumentException::extractionFailed()->getMessage()
        );
    }
}
