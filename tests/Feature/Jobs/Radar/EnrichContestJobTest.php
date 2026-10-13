<?php

use App\Enums\EnrichmentStatus;
use App\Jobs\Radar\EnrichContestJob;
use App\Models\Contest;
use App\Services\Radar\ContestMerger;
use App\Services\Radar\DossierValidator;
use App\Services\Radar\Llm\LlmResponse;
use App\Services\Radar\LlmProvider;

class QueuedLlmProvider implements LlmProvider
{
    private int $calls = 0;

    /**
     * @param  array<int, LlmResponse>  $responses
     */
    public function __construct(private readonly array $responses) {}

    public function available(): bool
    {
        return true;
    }

    public function complete(string $system, string $input, int $maxWebSearches): LlmResponse
    {
        $response = $this->responses[$this->calls] ?? end($this->responses);
        $this->calls++;

        return $response;
    }

    public function callCount(): int
    {
        return $this->calls;
    }
}

function llmResponse(string $text, int $in = 100, int $out = 50, int $searches = 1): LlmResponse
{
    return new LlmResponse($text, $in, $out, $searches);
}

function validDossier(): string
{
    return json_encode([
        'campos' => [
            'board' => ['valor' => 'FGV', 'fonte' => ['url' => 'https://banca.gov.br/edital', 'confianca' => 'high']],
            'edital_url' => ['valor' => 'https://banca.gov.br/edital.pdf', 'fonte' => ['url' => 'https://banca.gov.br/edital', 'confianca' => 'high']],
            'it_vacancies' => ['valor' => 20, 'fonte' => ['url' => 'https://agregador.com/x', 'confianca' => 'medium']],
        ],
        'eventos_extra' => [
            ['tipo' => 'result', 'data' => '2026-06-01'],
        ],
    ], JSON_UNESCAPED_UNICODE);
}

it('merges a valid dossier on the first attempt', function () {
    $contest = Contest::factory()->create(['board' => null, 'edital_url' => null, 'it_vacancies' => null]);

    $provider = new QueuedLlmProvider([llmResponse(validDossier(), 100, 50, 2)]);

    $result = (new EnrichContestJob($contest->id))->handle($provider, new ContestMerger, new DossierValidator);

    $contest->refresh();

    expect($result->success)->toBeTrue()
        ->and($result->inputTokens)->toBe(100)
        ->and($result->outputTokens)->toBe(50)
        ->and($result->webSearches)->toBe(2)
        ->and($contest->board)->toBe('FGV')
        ->and($contest->edital_url)->toBe('https://banca.gov.br/edital.pdf')
        ->and($contest->it_vacancies)->toBe(20)
        ->and($contest->field_sources['board']['origin'])->toBe('llm')
        ->and($contest->field_sources['board']['confidence'])->toBe('high')
        ->and($contest->enrichment_status)->toBe(EnrichmentStatus::Done)
        ->and($contest->last_enriched_at)->not->toBeNull()
        ->and($contest->dossier['campos'])->toHaveCount(3)
        ->and($provider->callCount())->toBe(1);

    expect($contest->events()->where('type', 'result')->exists())->toBeTrue();
});

it('retries once with the validation error attached and succeeds on the second try', function () {
    $contest = Contest::factory()->create();

    $provider = new QueuedLlmProvider([
        llmResponse('não é json', 80, 20, 1),
        llmResponse(validDossier(), 100, 50, 2),
    ]);

    $result = (new EnrichContestJob($contest->id))->handle($provider, new ContestMerger, new DossierValidator);

    expect($result->success)->toBeTrue()
        ->and($result->inputTokens)->toBe(180)
        ->and($result->outputTokens)->toBe(70)
        ->and($result->webSearches)->toBe(3)
        ->and($provider->callCount())->toBe(2)
        ->and($contest->refresh()->enrichment_status)->toBe(EnrichmentStatus::Done);
});

it('marks the contest as failed after two invalid responses', function () {
    $contest = Contest::factory()->create();

    $provider = new QueuedLlmProvider([
        llmResponse('não é json'),
        llmResponse('ainda não é json'),
    ]);

    $result = (new EnrichContestJob($contest->id))->handle($provider, new ContestMerger, new DossierValidator);

    expect($result->success)->toBeFalse()
        ->and($provider->callCount())->toBe(2)
        ->and($contest->refresh()->enrichment_status)->toBe(EnrichmentStatus::Failed);
});
