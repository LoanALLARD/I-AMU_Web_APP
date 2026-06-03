<?php

namespace Domain;

class Conversation {
    // a faire :
    // Doit recup tous les context des messages qui appartienne
    // a la conv.

    public function __construct(
        private readonly int $userId,
        private readonly int $sessionId,
        private readonly string $name,
    ) {
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function sessionId(): int
    {
        return $this->sessionId;
    }

    public function name(): string
    {
        return $this->name;
    }
}
