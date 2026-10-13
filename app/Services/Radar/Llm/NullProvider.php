<?php

namespace App\Services\Radar\Llm;

use App\Services\Radar\LlmProvider;
use LogicException;

/**
 * Bindado quando não há ANTHROPIC_API_KEY (CLAUDE.md regra 3 — app funciona
 * sem LLM). available() = false; complete() nunca deveria ser chamado
 * (EnrichContestsJob checa available() antes), então falha alto se acontecer.
 */
class NullProvider implements LlmProvider
{
    public function available(): bool
    {
        return false;
    }

    public function complete(string $system, string $input, int $maxWebSearches): LlmResponse
    {
        throw new LogicException('NullProvider::complete() chamado — EnrichContestsJob deveria ter checado available() antes.');
    }
}
