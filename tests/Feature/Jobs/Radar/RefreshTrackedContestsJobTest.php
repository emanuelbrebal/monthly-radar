<?php

use App\Enums\EditalStatus;
use App\Enums\RunStatus;
use App\Jobs\Radar\RefreshTrackedContestsJob;
use App\Models\Contest;
use App\Models\SearchRun;
use App\Services\Radar\Llm\LlmResponse;
use App\Services\Radar\LlmProvider;

class NothingChangedProvider implements LlmProvider
{
    /** @var array<int, string> */
    public array $inputs = [];

    public function available(): bool
    {
        return true;
    }

    public function complete(string $system, string $input, int $maxWebSearches): LlmResponse
    {
        $this->inputs[] = $input;

        return new LlmResponse('{"campos": {}, "novidades": []}', 500, 40, 3);
    }
}

it('reviews only tracked contests and logs the cost in the run', function () {
    $pinned = Contest::factory()->pinned(0)->create();
    $interested = Contest::factory()->create(['enrollment_status' => 'interested']);
    $enrolled = Contest::factory()->enrolled()->create();
    Contest::factory()->create();
    Contest::factory()->dismissed()->create();
    Contest::factory()->create(['enrollment_status' => 'interested', 'status' => EditalStatus::Finished->value]);

    expect(RefreshTrackedContestsJob::candidates()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$pinned->id, $interested->id, $enrolled->id])->sort()->values()->all());

    $run = SearchRun::factory()->create([
        'status' => RunStatus::Queued->value,
        'counts' => [],
        'meta' => ['kind' => 'refresh', 'input_tokens' => 0, 'output_tokens' => 0, 'web_searches' => 0, 'errors' => []],
    ]);
    $provider = new NothingChangedProvider;
    app()->instance(LlmProvider::class, $provider);

    app()->call([new RefreshTrackedContestsJob($run->id), 'handle']);

    $run->refresh();

    expect($provider->inputs)->toHaveCount(3)
        ->and($run->counts['enriched'])->toBe(3)
        ->and($run->counts['updated'])->toBe(0)
        ->and($run->meta['input_tokens'])->toBe(1500)
        ->and($run->meta['output_tokens'])->toBe(120)
        ->and($run->meta['web_searches'])->toBe(9)
        ->and($run->meta['kind'])->toBe('refresh');
});

it('starts with the contests checked longest ago', function () {
    Contest::factory()->pinned(0)->create(['last_enriched_at' => now()->subDay()]);
    $never = Contest::factory()->pinned(1)->create(['last_enriched_at' => null]);

    expect(RefreshTrackedContestsJob::candidates()->first()->id)->toBe($never->id);
});
