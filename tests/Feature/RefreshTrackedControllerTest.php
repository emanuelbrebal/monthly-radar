<?php

use App\Enums\RunStatus;
use App\Jobs\Radar\FinalizeRunJob;
use App\Jobs\Radar\RefreshTrackedContestsJob;
use App\Models\Contest;
use App\Models\SearchRun;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('refuses without an LLM key so no screen depends on it', function () {
    config(['radar.llm.api_key' => null]);
    Contest::factory()->pinned(0)->create();

    $this->postJson('/contests/refresh-tracked')
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $m) => str_contains($m, 'ANTHROPIC_API_KEY'));

    $this->assertDatabaseCount('search_runs', 0);
});

it('creates a refresh run and chains the review and the finalizer', function () {
    Bus::fake();
    config(['radar.llm.api_key' => 'test-key']);
    Contest::factory()->pinned(0)->create();

    $this->postJson('/contests/refresh-tracked')
        ->assertStatus(201)
        ->assertJsonPath('run.kind', 'refresh')
        ->assertJsonPath('run.status', RunStatus::Queued->value);

    Bus::assertChained([RefreshTrackedContestsJob::class, FinalizeRunJob::class]);
});

it('shares the one-active-run rule with the sweep', function () {
    Bus::fake();
    config(['radar.llm.api_key' => 'test-key']);
    Contest::factory()->pinned(0)->create();
    SearchRun::factory()->create(['status' => RunStatus::Scraping->value]);

    $this->postJson('/contests/refresh-tracked')->assertStatus(409);
});

it('says so when nothing is tracked', function () {
    Bus::fake();
    config(['radar.llm.api_key' => 'test-key']);
    Contest::factory()->create();

    $this->postJson('/contests/refresh-tracked')->assertStatus(422);
    Bus::assertNothingDispatched();
});

it('tells the page whether the button can run and how many contests it covers', function () {
    config(['radar.llm.api_key' => null]);
    Contest::factory()->pinned(0)->create();
    Contest::factory()->create(['enrollment_status' => 'interested']);

    $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('refresh.available', false)
        ->where('refresh.tracked', 2));
});
