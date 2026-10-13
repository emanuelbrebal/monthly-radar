<?php

use App\Actions\Radar\UnpinExpiredContests;
use App\Enums\RunStatus;
use App\Jobs\Radar\FinalizeRunJob;
use App\Models\Contest;
use App\Models\SearchRun;
use App\Services\Radar\Sources\ContestSourceRegistry;
use App\Services\Radar\Sources\ProvasDeTiSource;

it('unpins expired contests and marks the run as done', function () {
    $expired = Contest::factory()->pinned(0)->closedAt(now()->subDays(2))->create();
    $run = SearchRun::factory()->create(['counts' => ['scraped' => 3, 'matched' => 2, 'created' => 1, 'updated' => 1, 'enriched' => 0, 'failed' => 0]]);

    (new FinalizeRunJob($run->id))->handle(new UnpinExpiredContests, app(ContestSourceRegistry::class));

    expect($expired->refresh()->pinned_slot)->toBeNull();

    $run->refresh();
    expect($run->status)->toBe(RunStatus::Done)
        ->and($run->finished_at)->not->toBeNull();
});

it('marks the run as failed when every enabled source errored and nothing was scraped', function () {
    $run = SearchRun::factory()->create([
        'counts' => ['scraped' => 0, 'matched' => 0, 'created' => 0, 'updated' => 0, 'enriched' => 0, 'failed' => 0],
        'meta' => ['input_tokens' => 0, 'output_tokens' => 0, 'web_searches' => 0, 'errors' => [['source' => 'provas_de_ti', 'message' => 'fonte exige navegador']]],
    ]);

    $registry = Mockery::mock(ContestSourceRegistry::class);
    $registry->shouldReceive('enabled')->andReturn([app(ProvasDeTiSource::class)]);

    (new FinalizeRunJob($run->id))->handle(new UnpinExpiredContests, $registry);

    expect($run->refresh()->status)->toBe(RunStatus::Failed);
});

it('keeps the run as done when only some sources errored', function () {
    $run = SearchRun::factory()->create([
        'counts' => ['scraped' => 1, 'matched' => 1, 'created' => 1, 'updated' => 0, 'enriched' => 0, 'failed' => 0],
        'meta' => ['input_tokens' => 0, 'output_tokens' => 0, 'web_searches' => 0, 'errors' => [['source' => 'a', 'message' => 'x']]],
    ]);

    $registry = Mockery::mock(ContestSourceRegistry::class);
    $registry->shouldReceive('enabled')->andReturn([app(ProvasDeTiSource::class), app(ProvasDeTiSource::class)]);

    (new FinalizeRunJob($run->id))->handle(new UnpinExpiredContests, $registry);

    expect($run->refresh()->status)->toBe(RunStatus::Done);
});
