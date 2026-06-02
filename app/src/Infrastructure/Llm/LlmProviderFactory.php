<?php

declare(strict_types=1);

namespace App\Infrastructure\Llm;

use App\Application\DTOs\LlmModelConfig;
use App\Application\Exceptions\UnsupportedModelException;
use App\Application\Ports\LlmProviderInterface;
use App\Application\Ports\LlmProviderResolverInterface;

/**
 * Maps a model's `adapter` discriminator to a concrete provider.
 *
 * This is the single place allowed to instantiate Infrastructure LLM
 * adapters, so neither the Application nor the Http layer ever news
 * them up directly.
 */
final class LlmProviderFactory implements LlmProviderResolverInterface
{
    public function resolve(LlmModelConfig $config): LlmProviderInterface
    {
        return match ($config->adapter) {
            'ollama' => new OllamaProvider($config->apiUrl, $config->name),
            default  => throw UnsupportedModelException::adapterNotSupported($config->adapter),
        };
    }
}
