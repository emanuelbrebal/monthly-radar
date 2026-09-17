<?php

use App\Enums\EnrichmentStatus;
use App\Jobs\Radar\EnrichContestsJob;
use App\Models\Contest;
use App\Models\SearchRun;
use App\Models\User;
use App\Services\Radar\Llm\LlmResponse;
use App\Services\Radar\LlmProvider;
use App\ValueObjects\UserPreferences;

class UnavailableProvider implements LlmProvider
{
    public function available(): bool
    {
        return false;
    }

    public function complete(string $system, string $input, int $maxWebSearches): LlmResponse
    {
        throw new LogicException('não deveria ser chamado');
    }
}

class AlwaysSucceedsProvider implements LlmProvider
{
    public int $calls = 0;

    public function available(): bool
    {
        return true;
    }

    public function complete(string $system, string $input, int $maxWebSearches): LlmResponse
    {
        $this->calls++;

        $json = json_encode([
            'campos' => [
                'board' => ['valor' => 'Cesgranrio', 'fonte' => ['url' => 'https://x.gov.br/edital', 'confianca' => 'high']],
            ],
        ]);

        return new LlmResponse($json, 10, 5, 1);
    }
}

it('marks every candidate as skipped without calling the provider when unavailable', function () {
    $run = SearchRun::factory()->create();
    $contest = Contest::factory()->create(['board' => null]);

    (new EnrichContestsJob($run->id))->handle(new UnavailableProvider);

    expect($contest->refresh()->enrichment_status)->toBe(EnrichmentStatus::Skipped);
});

it('enriches candidates with gaps and aggregates cost into the run', function () {
    $run = SearchRun::factory()->create();
    $withGap = Contest::factory()->create(['board' => null]);
    $complete = Contest::factory()->create([
        'board' => 'FCC',
        'registration_start' => now()->toDateString(),
        'registration_end' => now()->addDays(10)->toDateString(),
        'exam_date' => now()->addDays(40)->toDateString(),
        'salary_min_cents' => 500000,
        'it_vacancies' => 5,
        'edital_url' => 'https://x.gov.br/edital.pdf',
        'regime' => 'statutory',
        'last_enriched_at' => now(),
    ]);

    $provider = new AlwaysSucceedsProvider;

    (new EnrichContestsJob($run->id))->handle($provider);

    expect($provider->calls)->toBe(1)
        ->and($withGap->refresh()->board)->toBe('Cesgranrio')
        ->and($withGap->enrichment_status)->toBe(EnrichmentStatus::Done)
        ->and($complete->refresh()->board)->toBe('FCC');

    $run->refresh();
    expect($run->counts['enriched'])->toBe(1)
        ->and($run->meta['input_tokens'])->toBe(10)
        ->and($run->meta['web_searches'])->toBe(1);
});

it('never selects a dismissed contest', function () {
    $run = SearchRun::factory()->create();
    $dismissed = Contest::factory()->dismissed()->create(['board' => null]);

    $provider = new AlwaysSucceedsProvider;
    (new EnrichContestsJob($run->id))->handle($provider);

    expect($provider->calls)->toBe(0)
        ->and($dismissed->refresh()->enrichment_status)->toBe(EnrichmentStatus::Pending);
});

it('skips a contest outside the state filter unless pinned or enrolled', function () {
    $user = User::factory()->create();
    $user->preferences = new UserPreferences(states: ['SP']);
    $user->save();

    $run = SearchRun::factory()->create();

    $outOfFilter = Contest::factory()->inStates(['RN'])->create(['board' => null]);
    $pinnedOutOfFilter = Contest::factory()->inStates(['RN'])->pinned(0)->create(['board' => null]);

    $provider = new AlwaysSucceedsProvider;
    (new EnrichContestsJob($run->id))->handle($provider);

    expect($outOfFilter->refresh()->enrichment_status)->toBe(EnrichmentStatus::Pending)
        ->and($pinnedOutOfFilter->refresh()->enrichment_status)->toBe(EnrichmentStatus::Done);
});
