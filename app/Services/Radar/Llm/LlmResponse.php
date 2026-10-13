<?php

namespace App\Services\Radar\Llm;

/**
 * Resultado de uma chamada a LlmProvider::complete() — texto bruto da
 * resposta (esperado ser o JSON do dossiê, ver docs/agents/dossie.md) e o
 * custo da chamada, pra EnrichContestJob logar em search_runs.meta.
 */
final readonly class LlmResponse
{
    public function __construct(
        public string $text,
        public int $inputTokens,
        public int $outputTokens,
        public int $webSearches,
    ) {}
}
