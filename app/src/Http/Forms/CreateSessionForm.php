<?php

declare(strict_types=1);

namespace App\Http\Forms;

use App\Application\DTOs\CreateSessionRequest;
use App\Application\DTOs\UpdateSessionRequest;
use App\Domain\ValueObjects\SessionType;
use DateTimeImmutable;

/**
 * Converts raw `$_POST` from the create/edit forms into validated
 * Application DTOs.
 *
 * Format validation only (required, types, ranges) — business rules
 * (>=1 model authorised, transition gating, resource ownership) belong
 * to the controller / services.
 */
final class CreateSessionForm
{
    /**
     * @param array<string, mixed> $post
     * @return array{request: CreateSessionRequest, errors: list<string>}|array{errors: list<string>}
     */
    public static function fromPost(array $post): array
    {
        [$validated, $errors] = self::validate($post, isCreate: true);
        if ($errors !== [] || $validated === null) {
            return ['errors' => $errors];
        }

        $request = new CreateSessionRequest(
            name:            $validated['name'],
            type:            $validated['type'],
            resourceId:      $validated['resource_id'],
            startsAt:        $validated['starts_at'],
            durationMinutes: $validated['duration'],
            modelIds:        $validated['model_ids'],
            prePrompt:       $validated['pre_prompt'],
            postPrompt:      $validated['post_prompt'],
            instructions:    $validated['instructions'],
            maxInputSize:    $validated['max_input_size'],
            accessCode:      $validated['access_code'],
        );

        return ['request' => $request, 'errors' => []];
    }

    /**
     * @param array<string, mixed> $post
     * @return array{request: UpdateSessionRequest, errors: list<string>}|array{errors: list<string>}
     */
    public static function fromPostForUpdate(array $post): array
    {
        [$validated, $errors] = self::validate($post, isCreate: false);
        if ($errors !== [] || $validated === null) {
            return ['errors' => $errors];
        }

        $request = new UpdateSessionRequest(
            name:            $validated['name'],
            startsAt:        $validated['starts_at'],
            durationMinutes: $validated['duration'],
            modelIds:        $validated['model_ids'],
            prePrompt:       $validated['pre_prompt'],
            postPrompt:      $validated['post_prompt'],
            instructions:    $validated['instructions'],
            maxInputSize:    $validated['max_input_size'],
        );

        return ['request' => $request, 'errors' => []];
    }

    /**
     * @param array<string, mixed> $post
     * @return array{0: array<string, mixed>|null, 1: list<string>}
     */
    private static function validate(array $post, bool $isCreate): array
    {
        $errors = [];

        $name = trim((string) ($post['name'] ?? ''));
        if ($name === '') {
            $errors[] = "Le libellé est obligatoire.";
        }
        if (strlen($name) > 255) {
            $errors[] = "Le libellé doit faire au plus 255 caractères.";
        }

        // Type — only required on create; immutable post-insert.
        $type = null;
        if ($isCreate) {
            $rawType = (string) ($post['type'] ?? '');
            $type = SessionType::tryFrom($rawType);
            if ($type === null) {
                $errors[] = "Type de session invalide.";
            }
        }

        // Resource — only required on create. The controller enforces
        // ownership (resources.owner_id = current teacher) separately.
        $resourceId = null;
        if ($isCreate) {
            $rawRes = $post['resource_id'] ?? null;
            $resourceId = ($rawRes !== null && (string) $rawRes !== '') ? (int) $rawRes : 0;
            if ($resourceId <= 0) {
                $errors[] = "Une ressource est obligatoire.";
            }
        }

        $startsAt = null;
        $startsRaw = trim((string) ($post['starts_at'] ?? ''));
        if ($startsRaw !== '') {
            try {
                $startsAt = new DateTimeImmutable($startsRaw);
            } catch (\Throwable) {
                $errors[] = "Date de démarrage invalide.";
            }
        }

        $duration = (int) ($post['duration_min'] ?? 90);
        if ($duration <= 0 || $duration > 480) {
            $errors[] = "La durée doit être entre 1 et 480 minutes.";
        }

        // Models: form sends models[] as a list of model id strings.
        $rawModels = $post['models'] ?? [];
        if (!is_array($rawModels)) {
            $rawModels = [];
        }
        $modelIds = array_values(array_filter(
            array_map(static fn ($v) => (int) $v, $rawModels),
            static fn (int $v): bool => $v > 0
        ));
        if ($modelIds === []) {
            $errors[] = "Sélectionnez au moins un modèle.";
        }

        // Single token cap on the live schema; null = no cap.
        $maxInputSize = null;
        $maxRaw = $post['max_input_size'] ?? null;
        if ($maxRaw !== null && (string) $maxRaw !== '') {
            $maxInputSize = (int) $maxRaw;
            if ($maxInputSize <= 0 || $maxInputSize > 200000) {
                $errors[] = "Plafond d'entrée invalide (1 à 200 000).";
                $maxInputSize = null;
            }
        }

        $accessCode = $isCreate
            ? (trim((string) ($post['access_code'] ?? '')) ?: null)
            : null;

        if ($errors !== []) {
            return [null, $errors];
        }

        return [[
            'name'            => $name,
            'type'            => $type,
            'resource_id'     => $resourceId,
            'starts_at'       => $startsAt,
            'duration'        => $duration,
            'model_ids'       => $modelIds,
            'pre_prompt'      => self::nullableText($post['pre_prompt']   ?? null),
            'post_prompt'     => self::nullableText($post['post_prompt']  ?? null),
            'instructions'    => self::nullableText($post['instructions'] ?? null),
            'max_input_size'  => $maxInputSize,
            'access_code'     => $accessCode,
        ], []];
    }

    private static function nullableText(mixed $raw): ?string
    {
        $s = trim((string) ($raw ?? ''));
        return $s === '' ? null : $s;
    }
}
