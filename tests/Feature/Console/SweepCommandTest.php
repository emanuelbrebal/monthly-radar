<?php

use App\Enums\EnrichmentStatus;
use App\Enums\RunStatus;
use App\Models\Contest;
use App\Models\SearchRun;
use App\Services\Radar\Llm\LlmResponse;
use App\Services\Radar\LlmProvider;
use Illuminate\Support\Facades\Http;

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

// A varredura passa por todas as fontes habilitadas; sem isolar a rede o teste
// depende de internet, do site no ar e do CA bundle do PHP local.
beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake([
        'pciconcursos.com.br/*' => Http::response(file_get_contents(base_path('tests/Fixtures/pci_concursos_sample.html'))),
        '*.gov.br/*' => Http::response(file_get_contents(base_path('tests/Fixtures/official_page_sample.html'))),
    ]);
});

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
