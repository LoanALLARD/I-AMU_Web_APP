<?php

declare(strict_types=1);

namespace App\Application\DTOs;

use App\Application\Ports\ClockInterface;
use App\Domain\Entities\Session;

/**
 * Rich view-model for `Views/pages/session/dashboard.php`.
 *
 * Carries everything the teacher needs to drive a single session.
 */
final readonly class SessionDashboardView
{
    /**
     * @param list<array{model_id:int, name:string, version:?string}> $authorizedModels
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $typeLabel,
        public string $typeClass,
        public string $statusLabel,
        public string $statusClass,
        public string $accessCode,           // formatted XXX-XXX
        public string $accessCodeRaw,        // 6 chars, for copy-to-clipboard
        public ?string $startsAtFormatted,
        public ?string $endsAtFormatted,
        public ?string $closedAtFormatted,
        public ?string $prePromptOverride,
        public ?string $postPromptOverride,
        public ?string $instructions,
        public ?int $maxInputSize,
        public array $authorizedModels,
        public bool $canEdit,
        public bool $canStart,
        public bool $canEnd,
        public bool $canCancel,
    ) {
    }

    /**
     * @param list<array{model_id:int, name:string, version:?string}> $authorizedModels
     */
    public static function fromEntity(
        Session $session,
        array $authorizedModels,
        ClockInterface $clock
    ): self {
        $now      = $clock->now();
        $computed = $session->computedStatus($now);
        $actions  = $session->availableActions($now);

        return new self(
            id:                   $session->id() ?? 0,
            name:                 $session->name(),
            typeLabel:            $session->type()->label(),
            typeClass:            $session->type()->badgeClass(),
            statusLabel:          $computed->label(),
            statusClass:          $computed->badgeClass(),
            accessCode:           $session->accessCode()->formatted(),
            accessCodeRaw:        $session->accessCode()->value,
            startsAtFormatted:    $session->startsAt()?->format('d/m/Y H:i'),
            endsAtFormatted:      $session->endsAt()?->format('d/m/Y H:i'),
            closedAtFormatted:    $session->closedAt()?->format('d/m/Y H:i'),
            prePromptOverride:    $session->prePromptOverride(),
            postPromptOverride:   $session->postPromptOverride(),
            instructions:         $session->instructions(),
            maxInputSize:         $session->maxInputSize(),
            authorizedModels:     $authorizedModels,
            canEdit:              $actions['can_edit'],
            canStart:             $actions['can_start'],
            canEnd:               $actions['can_end'],
            canCancel:            $actions['can_cancel'],
        );
    }
}
