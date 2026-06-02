<?php

declare(strict_types=1);

namespace App\Application\DTOs;

/**
 * Read-side flat view of a resource (course).
 *
 * Used by the create-session form to populate the resource selector
 * (a teacher can only attach sessions to resources they own) and by the
 * dashboard to surface the parent resource label.
 */
final readonly class ResourceMetaView
{
    public function __construct(
        public int $id,
        public int $ownerId,
        public string $code,
        public string $name,
        public string $state,
    ) {
    }

    /**
     * @param array{id:int, owner_id:int, code:string, name:string, state:string} $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id:      (int) $row['id'],
            ownerId: (int) $row['owner_id'],
            code:    (string) $row['code'],
            name:    (string) $row['name'],
            state:   (string) $row['state'],
        );
    }
}
