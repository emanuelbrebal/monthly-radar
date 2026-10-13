<?php

namespace App\Services\Radar\Llm;

use App\Services\Radar\LlmProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HTTP direto no /v1/messages da Anthropic (CLAUDE.md regra 4 — sem SDK),
 * com a tool de busca web do servidor. Ver docs/02-pipeline.md → "3.
 * Enriquecimento" e docs/agents/dossie.md pro schema esperado na resposta.
 *
 * [inferido] Tipo de tool 'web_search_20250305' / nome 'web_search' e o
 * campo usage.server_tool_use.web_search_requests não foram validados
 * contra a API real nesta sessão (sem ANTHROPIC_API_KEY disponível) —
 * confirmar na primeira varredura real com chave configurada.
 */
class AnthropicProvider implements LlmProvider
{
    public function available(): bool
    {
        return filled(config('radar.llm.api_key'));
    }

    public function complete(string $system, string $input, int $maxWebSearches): LlmResponse
    {
        $response = Http::withHeaders([
            'x-api-key' => config('radar.llm.api_key'),
            'anthropic-version' => config('radar.llm.api_version'),
        ])
            ->timeout((int) config('radar.llm.timeout'))
            ->post(config('radar.llm.base_url'), [
                'model' => config('radar.llm.model'),
                'max_tokens' => (int) config('radar.llm.max_tokens'),
                'system' => $system,
                'messages' => [
                    ['role' => 'user', 'content' => $input],
                ],
                'tools' => [
                    [
                        'type' => 'web_search_20250305',
                        'name' => 'web_search',
                        'max_uses' => $maxWebSearches,
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException("Anthropic API respondeu {$response->status()}: {$response->body()}");
        }

        $data = $response->json();

        /** @var array<int, array<string, mixed>> $contentBlocks */
        $contentBlocks = $data['content'] ?? [];

        $text = collect($contentBlocks)
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        return new LlmResponse(
            text: $text,
            inputTokens: (int) ($data['usage']['input_tokens'] ?? 0),
            outputTokens: (int) ($data['usage']['output_tokens'] ?? 0),
            webSearches: (int) ($data['usage']['server_tool_use']['web_search_requests'] ?? 0),
        );
    }
}
