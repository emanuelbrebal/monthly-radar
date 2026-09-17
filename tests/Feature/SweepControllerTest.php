<?php

namespace Tests\Feature;

use App\Enums\RunStatus;
use App\Jobs\Radar\EnrichContestsJob;
use App\Jobs\Radar\FinalizeRunJob;
use App\Jobs\Radar\HarvestSourcesJob;
use App\Jobs\Radar\NormalizeListingsJob;
use App\Models\SearchRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SweepControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_start_a_sweep(): void
    {
        $this->postJson('/sweeps', ['area' => 'development'])->assertRedirect(route('login'));
    }

    public function test_it_creates_a_run_and_dispatches_the_chain(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/sweeps', ['area' => 'development']);

        $response->assertStatus(201)
            ->assertJsonPath('run.area', 'development')
            ->assertJsonPath('run.status', RunStatus::Scraping->value);

        $this->assertDatabaseCount('search_runs', 1);
        Bus::assertChained([
            HarvestSourcesJob::class,
            NormalizeListingsJob::class,
            EnrichContestsJob::class,
            FinalizeRunJob::class,
        ]);
    }

    public function test_it_rejects_a_second_sweep_while_one_is_active(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        SearchRun::factory()->create(['status' => RunStatus::Scraping->value]);

        $this->postJson('/sweeps', ['area' => 'development'])->assertStatus(409);
    }

    public function test_it_rejects_an_unknown_area(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/sweeps', ['area' => 'nao_existe'])->assertStatus(422);
    }

    public function test_show_returns_the_run_status_for_polling(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $run = SearchRun::factory()->create();

        $this->getJson("/sweeps/{$run->id}")
            ->assertOk()
            ->assertJsonPath('run.id', $run->id);
    }
}
