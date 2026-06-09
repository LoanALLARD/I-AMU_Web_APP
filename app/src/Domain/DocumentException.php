<?php

declare(strict_types=1);

namespace Domain;

use RuntimeException;

/**
 * Single domain exception for the document feature. Named factories carry the
 * user-facing French message the controller flashes.
 */
final class DocumentException extends RuntimeException
{
    public static function noFile(): self
    {
        return new self('Aucun fichier reçu.');
    }

    public static function uploadFailed(): self
    {
        return new self('Le téléversement du fichier a échoué.');
    }

    public static function unsupportedType(): self
    {
        return new self('Type de fichier non supporté (PDF, Markdown ou TXT uniquement).');
    }

    public static function tooLarge(int $maxMb): self
    {
        return new self("Le fichier dépasse la taille maximale de {$maxMb} Mo.");
    }

    public static function quotaReached(int $max): self
    {
        return new self("Cette session a atteint la limite de {$max} documents.");
    }

    public static function quotaReachedConversation(int $max): self
    {
        return new self("Cette conversation a atteint la limite de {$max} documents.");
    }

    public static function examImportDisabled(): self
    {
        return new self("L'import de documents est désactivé pendant un examen.");
    }

    public static function notFound(): self
    {
        return new self('Document introuvable.');
    }

    public static function forbidden(): self
    {
        return new self("Vous n'avez pas accès à ce document.");
    }

    public static function extractionFailed(): self
    {
        return new self('Le texte du document n\'a pas pu être extrait.');
    }
}
