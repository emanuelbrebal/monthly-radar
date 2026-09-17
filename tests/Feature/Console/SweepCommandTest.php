<?php

use App\Enums\EnrichmentStatus;
use App\Enums\RunStatus;
use App\Models\Contest;
use App\Models\SearchRun;
use App\Services\Radar\Llm\LlmResponse;
use App\Services\Radar\LlmProvider;

class SweepStubProvider implements LlmProvider
{
    public bool $wasCalled = false;

    public function available(): bool
    {
        return true;
    }

    public function complete(string $system, string $input, int $maxWebSearches): LlmResponse
    {
        $this->wasCalled = true;

        return new LlmResponse(json_encode(['campos' => []]), 1, 1, 0);
    }
}

it('fails with an unknown area', function () {
    $this->artisan('radar:sweep', ['area' => 'nao_existe'])->assertFailed();
});

it('runs the pipeline synchronously and finishes the run', function () {
    $this->artisan('radar:sweep', ['area' => 'development', '--no-llm' => true])
        ->assertSuccessful();

    $run = SearchRun::query()->latest('id')->first();

    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(RunStatus::Done)
        ->and($run->finished_at)->not->toBeNull();
});

it('--no-llm skips enrichment even with a provider bound', function () {
    $stub = new SweepStubProvider;
    app()->instance(LlmProvider::class, $stub);

    $contest = Contest::factory()->create(['board' => null]);

    $this->artisan('radar:sweep', ['area' => 'development', '--no-llm' => true])->assertSuccessful();

    expect($stub->wasCalled)->toBeFalse()
        ->and($contest->refresh()->enrichment_status)->toBe(EnrichmentStatus::Pending);
});
