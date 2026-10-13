<?php

namespace App\Jobs\Radar;

/**
 * Resultado de EnrichContestJob::handle() — pra EnrichContestsJob agregar
 * custo/contagens em search_runs.meta/counts numa escrita só.
 */
final readonly class EnrichResult
{
    public function __construct(
        public bool $success,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $webSearches = 0,
    ) {}
}
