<?php

declare(strict_types=1);

namespace App\Application\Exceptions;

use RuntimeException;

/**
 * Thrown when a chat request targets a model that does not exist, or
 * whose adapter has no wired provider. The HTTP layer maps it to 404.
 */
final class UnsupportedModelException extends RuntimeException
{
    public static function modelNotFound(string $name): self
    {
        return new self("Le modèle demandé n'est pas supporté.");
    }

    public static function adapterNotSupported(string $adapter): self
    {
        return new self(
            sprintf("Aucun fournisseur n'est configuré pour l'adaptateur « %s ».", $adapter)
        );
    }
}
