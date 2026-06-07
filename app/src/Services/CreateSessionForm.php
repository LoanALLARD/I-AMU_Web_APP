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
            $max_Tokens  = (int) $rawReq;
            if ($max_Tokens  < 1 || $max_Tokens  > 500000) {
                $errors[] = 'Limite de token invalide (1 à 500 000).';
            }
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
                'maxTokens'       => $max_Tokens,
                'accessCode'      => $isCreate ? $optional($post['access_code'] ?? '') : null,
            ],
            'errors' => $errors,
        ];
    }
}
