<?php

namespace Tests\Feature;

use App\Models\ExamResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_it_renders_the_results_dashboard_without_results()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->component('radar/dashboard')
            ->where('summary.kpis.with_result', 0)
            ->has('contests', 0)
        );
    }

    public function test_it_exposes_contests_with_results_for_the_dossier()
    {
        $this->actingAs(User::factory()->create());

        $result = ExamResult::factory()->create(['score' => 62, 'max_score' => 70]);

        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('summary.kpis.with_result', 1)
            ->where('summary.timeline.0.percent', 88.6)
            ->has('contests', 1)
            ->where('contests.0.id', $result->contest_id)
            ->where('contests.0.result.percent', 88.6)
        );
    }
}
