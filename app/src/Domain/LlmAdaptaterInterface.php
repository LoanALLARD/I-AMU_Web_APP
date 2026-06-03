<?php

namespace Domain;

interface LlmAdaptaterInterface {
    /**
     * Reçoit un message et un contexte, s'occupe de formater la requête 
     * spécifique à l'API cible, l'exécute, et renvoie une chaîne standard.
     */
    public function generate(string $message, array $context): string;

    public function formatMetadata(object $response);

    public function readContextFromMetadata(array $metaDataRaw) : array;
}