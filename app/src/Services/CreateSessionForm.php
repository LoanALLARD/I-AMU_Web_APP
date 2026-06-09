<?php

declare(strict_types=1);

namespace Services;

use DateTimeImmutable;
use Domain\SessionType;
use Exception;

/**
 * Validates the create / edit session form. Replaces the input DTOs: returns
 * a plain `['data' => [...], 'errors' => [...]]` array the controller hands
 * straight to SessionService when `errors` is empty.
 */
class CreateSessionForm
{
    /**
     * @param array<string, mixed> $post
     * @return array{data: array<string, mixed>, errors: list<string>}
     */
    public static function fromPost(array $post): array
    {
        return self::validate($post, true);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{data: array<string, mixed>, errors: list<string>}
     */
    public static function fromPostForUpdate(array $post): array
    {
        return self::validate($post, false);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{data: array<string, mixed>, errors: list<string>}
     */
    private static function validate(array $post, bool $isCreate): array
    {
        $errors = [];

        $name = trim((string) ($post['name'] ?? ''));
        if ($name === '') {
            $errors[] = 'Le libellé est obligatoire.';
        } elseif (mb_strlen($name) > 255) {
            $errors[] = 'Le libellé doit faire au plus 255 caractères.';
        }

        $type       = null;
        $resourceId = 0;
        if ($isCreate) {
            $type = SessionType::tryFrom((string) ($post['type'] ?? ''));
            if ($type === null) {
                $errors[] = 'Type de session invalide.';
            }
            $resourceId = (int) ($post['resource_id'] ?? 0);
            if ($resourceId <= 0) {
                $errors[] = 'Une ressource est obligatoire.';
            }
        }

        $startsAt = null;
        $rawStart = trim((string) ($post['starts_at'] ?? ''));
        if ($rawStart !== '') {
            try {
                $startsAt = new DateTimeImmutable($rawStart);
            } catch (Exception) {
                $errors[] = 'Date de démarrage invalide.';
            }
        }

        $duration = (int) ($post['duration_min'] ?? 90);
        if ($duration < 1 || $duration > 480) {
            $errors[] = 'La durée doit être entre 1 et 480 minutes.';
        }

        $modelIds = [];
        foreach ((array) ($post['models'] ?? []) as $mid) {
            $mid = (int) $mid;
            if ($mid > 0) {
                $modelIds[] = $mid;
            }
        }
        $modelIds = array_values(array_unique($modelIds));
        if ($modelIds === []) {
            $errors[] = 'Sélectionnez au moins un modèle.';
        }

        $maxInputSize = null;
        $maxTokens  = null;

        $rawMax       = trim((string) ($post['max_input_size'] ?? ''));
        if ($rawMax !== '') {
            $maxInputSize = (int) $rawMax;
            if ($maxInputSize < 1 || $maxInputSize > 200000) {
                $errors[] = "Plafond d'entrée invalide (1 à 200 000).";
            }
        }
        $rawReq      = trim((string) ($post['max_tokens'] ?? ''));
        if ($rawReq !== '') {
            $maxTokens  = (int) $rawReq;
            if ($maxTokens  < 1 || $maxTokens  > 500000) {
                $errors[] = 'Limite de token invalide (1 à 500 000).';
            }
        }

        // Document settings: the authorised file formats (an empty set means
        // student imports are disabled for the session) and the max file size.
        $documentsMaxBytes = null;
        $rawDocMb = trim((string) ($post['documents_max_mb'] ?? ''));
        if ($rawDocMb !== '') {
            $mb = (int) $rawDocMb;
            if ($mb < 1 || $mb > 10) {
                $errors[] = 'Taille max des documents invalide (1 à 10 Mo).';
            } else {
                $documentsMaxBytes = $mb * 1024 * 1024;
            }
        }

        $documentsEnabled = isset($post['documents_enabled']);

        $documentsFormats = [];
        foreach ((array) ($post['documents_types'] ?? []) as $t) {
            $t = strtolower(trim((string) $t));
            if (in_array($t, ['pdf', 'md', 'txt'], true)) {
                $documentsFormats[] = $t;
            }
        }
        $documentsFormats = array_values(array_unique($documentsFormats));

        // The toggle drives whether any format is recorded: unchecked means no
        // row in session_file_formats (imports disabled).
        if (!$documentsEnabled) {
            $documentsFormats = [];
        } elseif ($documentsFormats === []) {
            $errors[] = "Sélectionnez au moins un format autorisé (ou décochez l'import de documents).";
        }

        $optional = static function (mixed $value): ?string {
            $value = trim((string) $value);
            return $value === '' ? null : $value;
        };

        return [
            'data' => [
                'name'            => $name,
                'type'            => $type,
                'resourceId'      => $resourceId,
                'startsAt'        => $startsAt,
                'durationMinutes' => $duration,
                'modelIds'        => $modelIds,
                'prePrompt'       => $optional($post['pre_prompt'] ?? ''),
                'postPrompt'      => $optional($post['post_prompt'] ?? ''),
                'instructions'    => $optional($post['instructions'] ?? ''),
                'maxInputSize'    => $maxInputSize,
                'maxTokens'       => $maxTokens,
                'documentsMaxBytes'     => $documentsMaxBytes,
                'documentsFormats'      => $documentsFormats,
                'accessCode'      => $isCreate ? $optional($post['access_code'] ?? '') : null,
            ],
            'errors' => $errors,
        ];
    }
}
