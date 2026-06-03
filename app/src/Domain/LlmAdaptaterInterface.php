<?php

namespace Domain;

interface LlmAdaptaterInterface {
    /**
     * Reçoit un message et un contexte, s'occupe de formater la requête
     * spécifique à l'API cible, l'exécute, et renvoie une chaîne standard.
     *
     * @param array<int, int> $context conversation context (provider token ids)
     */
    public function generate(string $message, array $context): string;
}