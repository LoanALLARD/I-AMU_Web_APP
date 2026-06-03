<?php

declare(strict_types=1);

namespace App\Application\Ports;

use App\Application\DTOs\LlmModelConfig;

/**
 * Resolves the concrete {@see LlmProviderInterface} for a given model
 * configuration (based on its `adapter` discriminator).
 *
 * Keeps the Application layer free of any direct Infrastructure
 * instantiation — the implementation (a factory) lives in
 * App\Infrastructure\Llm.
 */
interface LlmProviderResolverInterface
{
    public function resolve(LlmModelConfig $config): LlmProviderInterface;
}
