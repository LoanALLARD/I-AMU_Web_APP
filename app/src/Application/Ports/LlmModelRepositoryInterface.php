<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\DTOs\LlmModelConfig;

/**
 * Read port for resolving an LLM model by its public name (the tag the
 * client sends, e.g. "llama3.2:1b"). Implemented in Infrastructure.
 */
interface LlmModelRepositoryInterface
{
    public function findByName(string $name): ?LlmModelConfig;
}
