<?php

namespace Tests\Feature;

use App\Models\Contest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RadarPageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_it_renders_the_radar_page_with_visible_contests(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $visible = Contest::factory()->create();
        Contest::factory()->dismissed()->create();

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('radar/index')
            ->has('visibleContests', 1)
            ->where('visibleContests.0.id', $visible->id)
            ->has('areas')
            ->has('regions')
            ->where('preferences.states', [])
        );
    }

    public function test_it_respects_the_saved_state_filter(): void
    {
        $user = User::factory()->create(['preferences' => ['states' => ['RN']]]);
        $this->actingAs($user);

        Contest::factory()->inStates(['SP'])->create();
        $rn = Contest::factory()->inStates(['RN'])->create();

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('visibleContests', 1)
            ->where('visibleContests.0.id', $rn->id)
        );
    }

    public function test_it_exposes_the_contest_tier(): void
    {
        $this->actingAs(User::factory()->create());

        Contest::factory()->create(['tier' => 'elite', 'tier_note' => 'auditor federal']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('visibleContests.0.tier', 'elite')
            ->where('visibleContests.0.tier_label', 'elite')
            ->where('visibleContests.0.tier_note', 'auditor federal')
        );
    }
}
