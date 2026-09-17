<?php

namespace App\Services\Radar;

use App\Services\Radar\Llm\LlmResponse;

/**
 * Nunca chamar SDK/HTTP de provider diretamente em Job ou Controller
 * (CLAUDE.md regra 4) — sempre por esta interface. Implementações em
 * App\Services\Radar\Llm.
 */
interface LlmProvider
{
    /**
     * Sem ANTHROPIC_API_KEY → false. EnrichContestsJob usa isso pra marcar
     * enrichment_status = skipped sem tentar nenhuma chamada.
     */
    public function available(): bool;

    public function complete(string $system, string $input, int $maxWebSearches): LlmResponse;
}
