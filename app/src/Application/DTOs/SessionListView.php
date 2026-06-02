<?php

declare(strict_types=1);

namespace App\Application\DTOs;

use App\Application\Ports\ClockInterface;
use App\Domain\Entities\Session;

/**
 * Flat view-model used by `Views/pages/session/index.php`.
 *
 * Pre-computes labels, CSS classes and action flags so the template only
 * does `<?= $view->statusLabel ?>` instead of calling methods on the entity.
 */
final readonly class SessionListView
{
    public function __construct(
        public int $id,
        public string $name,
        public string $typeLabel,
        public string $typeClass,
        public string $statusLabel,
        public string $statusClass,
        public string $accessCode,           // formatted XXX-XXX
        public ?string $startsAtFormatted,
        public ?string $endsAtFormatted,
        public bool $canEdit,
        public bool $canStart,
        public bool $canEnd,
        public bool $canCancel,
    ) {
    }

    public static function fromEntity(Session $session, ClockInterface $clock): self
    {
        $now      = $clock->now();
        $computed = $session->computedStatus($now);
        $actions  = $session->availableActions($now);

        return new self(
            id:                $session->id() ?? 0,
            name:              $session->name(),
            typeLabel:         $session->type()->label(),
            typeClass:         $session->type()->badgeClass(),
            statusLabel:       $computed->label(),
            statusClass:       $computed->badgeClass(),
            accessCode:        $session->accessCode()->formatted(),
            startsAtFormatted: $session->startsAt()?->format('d/m/Y H:i'),
            endsAtFormatted:   $session->endsAt()?->format('d/m/Y H:i'),
            canEdit:           $actions['can_edit'],
            canStart:          $actions['can_start'],
            canEnd:            $actions['can_end'],
            canCancel:         $actions['can_cancel'],
        );
    }
}
