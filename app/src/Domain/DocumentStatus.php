<?php

declare(strict_types=1);

namespace Domain;

/**
 * Text-extraction status of an uploaded document. String-backed so it maps
 * straight to the `documents.status` column. The binary is always stored and
 * downloadable; this only tracks whether the text could be extracted (used by
 * the later RAG phases).
 */
enum DocumentStatus: string
{
    case Pending = 'PENDING';
    case Ready   = 'READY';
    case Failed  = 'FAILED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En traitement',
            self::Ready   => 'Indexé',
            self::Failed  => 'Texte non extrait',
        };
    }

    public function badgeClass(): string
    {
        return 'badge-' . strtolower($this->value);
    }
}
