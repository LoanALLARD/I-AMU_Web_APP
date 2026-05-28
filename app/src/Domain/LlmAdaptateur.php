<?php
namespace App\Domain;

interface LlmAdapterInterface {
    /**
     * Reçoit un message et un contexte, s'occupe de formater la requête 
     * spécifique à l'API cible, l'exécute, et renvoie une chaîne standard.
     */
    public function generate(string $message, array $context): string;
}