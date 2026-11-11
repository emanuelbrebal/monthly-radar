<?php

use App\Enums\EditalStatus;
use App\Enums\EnrichmentStatus;
use App\Jobs\Radar\EnrichResult;
use App\Jobs\Radar\RefreshContestJob;
use App\Models\Contest;
use App\Services\Radar\ContestMerger;
use App\Services\Radar\Llm\LlmResponse;
use App\Services\Radar\LlmProvider;
use App\Services\Radar\RefreshValidator;
use Illuminate\Support\Carbon;

class ScriptedRefreshProvider implements LlmProvider
{
    public int $calls = 0;

    public ?string $lastInput = null;

    /**
     * @param  array<int, string>  $texts
     */
    public function __construct(private readonly array $texts) {}

    public function available(): bool
    {
        return true;
    }

    public function complete(string $system, string $input, int $maxWebSearches): LlmResponse
    {
        $this->lastInput = $input;
        $text = $this->texts[$this->calls] ?? end($this->texts);
        $this->calls++;

        return new LlmResponse($text, 1000, 200, 2);
    }
}

/**
 * @param  array<int, string>  $texts
 * @return array{0: EnrichResult, 1: ScriptedRefreshProvider, 2: Contest}
 */
function runRefresh(Contest $contest, array $texts): array
{
    $provider = new ScriptedRefreshProvider($texts);
    $result = (new RefreshContestJob($contest->id))->handle($provider, app(ContestMerger::class), app(RefreshValidator::class));

    return [$result, $provider, $contest->refresh()];
}

function reviewWith(array $campos, array $extra = []): string
{
    return json_encode(['campos' => $campos, ...$extra], JSON_UNESCAPED_UNICODE);
}

/** Concurso semeado: salário de agregador (seed), situação e maleta do dono. */
function trackedContest(): Contest
{
    return Contest::factory()->create([
        'board' => null,
        'salary_min_cents' => 2092480,
        'enrollment_status' => 'interested',
        'pinned_slot' => 3,
        'personal_notes' => 'minha nota',
        'field_sources' => [
            'salary_min_cents' => ['url' => 'https://agregador.com/a', 'fetched_at' => null, 'origin' => 'seed', 'confidence' => 'medium'],
        ],
    ]);
}

beforeEach(function () {
    Carbon::setTestNow('2026-10-17 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('applies official changes, keeps the owner fields and records the check', function () {
    [$result, $provider, $contest] = runRefresh(trackedContest(), [reviewWith([
        'board' => ['valor' => 'Cebraspe', 'fonte' => ['url' => 'https://www.bcb.gov.br/concurso', 'confianca' => 'high', 'data' => '2026-10-16']],
        'status' => ['valor' => 'in_preparation', 'fonte' => ['url' => 'https://www.bcb.gov.br/concurso', 'confianca' => 'high', 'data' => '2026-10-16']],
        'salary_min_cents' => ['valor' => 1803352, 'fonte' => ['url' => 'https://www.in.gov.br/portaria', 'confianca' => 'high', 'data' => '2026-07-03']],
    ], [
        'eventos_extra' => [['tipo' => 'other', 'data' => '2027-01-03', 'rotulo' => 'prazo do edital']],
        'novidades' => ['Cebraspe contratado em 16/10/2026.'],
    ])]);

    expect($result->success)->toBeTrue()
        ->and($contest->board)->toBe('Cebraspe')
        ->and($contest->status)->toBe(EditalStatus::InPreparation)
        ->and($contest->salary_min_cents)->toBe(1803352)
        ->and($contest->field_sources['board']['source_date'])->toBe('2026-10-16')
        ->and($contest->field_sources['salary_min_cents']['url'])->toBe('https://www.in.gov.br/portaria')
        ->and($contest->enrollment_status->value)->toBe('interested')
        ->and($contest->pinned_slot)->toBe(3)
        ->and($contest->personal_notes)->toBe('minha nota')
        ->and($contest->source_checked_at?->toDateString())->toBe('2026-10-17')
        ->and($contest->enrichment_status)->toBe(EnrichmentStatus::Done)
        ->and($contest->dossier['last_refresh']['novidades'])->toBe(['Cebraspe contratado em 16/10/2026.'])
        ->and($contest->events->pluck('label')->all())->toContain('prazo do edital')
        ->and($contest->changes()->where('field', 'board')->exists())->toBeTrue()
        ->and($provider->lastInput)->toContain('Hoje é 2026-10-17');
});

it('turns a change the source priority refuses into a divergence', function () {
    $refused = reviewWith([
        'salary_min_cents' => ['valor' => 1803352, 'fonte' => ['url' => 'https://blog.example.com/bacen', 'confianca' => 'medium', 'data' => '2026-08-27']],
    ]);

    [, , $contest] = runRefresh(trackedContest(), [$refused]);

    expect($contest->salary_min_cents)->toBe(2092480)
        ->and($contest->dossier['divergences'])->toHaveCount(1)
        ->and($contest->dossier['divergences'][0]['field'])->toBe('salary_min_cents')
        ->and($contest->dossier['divergences'][0]['side_b'])->toContain('blog.example.com');

    // A mesma recusa numa segunda revisão não empilha outra divergência.
    [, , $contest] = runRefresh($contest, [$refused]);

    expect($contest->dossier['divergences'])->toHaveCount(1);
});

it('retries once and marks the contest failed after two invalid answers', function () {
    [$result, $provider, $contest] = runRefresh(trackedContest(), [
        reviewWith(['pinned_slot' => ['valor' => 0, 'fonte' => ['url' => 'https://x.gov.br', 'confianca' => 'high', 'data' => '2026-10-01']]]),
        'não é json',
    ]);

    expect($result->success)->toBeFalse()
        ->and($provider->calls)->toBe(2)
        ->and($contest->enrichment_status)->toBe(EnrichmentStatus::Failed)
        ->and($contest->pinned_slot)->toBe(3)
        ->and($contest->changes()->where('kind', 'failed')->exists())->toBeTrue();
});
