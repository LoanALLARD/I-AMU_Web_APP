<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Exceptions\UnsupportedModelException;
use App\Application\Ports\LlmModelRepositoryInterface;
use App\Application\Ports\LlmProviderResolverInterface;

/**
 * Use-case: turn a chat prompt into an LLM reply.
 *
 * Loads the model configuration by name, resolves the matching provider
 * and delegates generation to it. Knows nothing about HTTP or PDO.
 */
final class GenerateReplyService
{
    public function __construct(
        private readonly LlmModelRepositoryInterface $models,
        private readonly LlmProviderResolverInterface $providers,
    ) {
    }

    /**
     * @param array<int|string, mixed> $context
     *
     * @throws UnsupportedModelException when the model name is unknown
     *         or its adapter has no provider.
     */
    public function execute(string $modelName, string $message, array $context): string
    {
        $config = $this->models->findByName($modelName);
        if ($config === null) {
            throw UnsupportedModelException::modelNotFound($modelName);
        }

        return $this->providers->resolve($config)->generate($message, $context);
    }
}
