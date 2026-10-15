<?php

namespace Tests\Feature;

use App\Enums\EventType;
use App\Models\Contest;
use App\Models\ContestEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_read_the_calendar(): void
    {
        $this->get('/calendar?month=2026-09')->assertRedirect(route('login'));
    }

    public function test_it_rejects_a_malformed_month(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->getJson('/calendar?month=2026')->assertStatus(422);
    }

    public function test_it_only_returns_events_of_visible_contests_in_month(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $inMonth = Contest::factory()->create();
        ContestEvent::factory()->for($inMonth)->create(['type' => EventType::Exam->value, 'date' => '2026-09-15']);

        $outOfMonth = Contest::factory()->create();
        ContestEvent::factory()->for($outOfMonth)->create(['type' => EventType::Exam->value, 'date' => '2026-10-01']);

        $dismissed = Contest::factory()->dismissed()->create();
        ContestEvent::factory()->for($dismissed)->create(['type' => EventType::Exam->value, 'date' => '2026-09-20']);

        $response = $this->getJson('/calendar?month=2026-09')->assertOk();

        $ids = array_column($response->json('contests'), 'id');
        $this->assertSame([$inMonth->id], $ids);
    }

    public function test_it_returns_the_full_event_list_of_contests_in_month(): void
    {
        $this->actingAs(User::factory()->create());

        $contest = Contest::factory()->create();
        ContestEvent::factory()->for($contest)->create(['type' => EventType::Exam->value, 'date' => '2026-09-15']);
        ContestEvent::factory()->for($contest)->create(['type' => EventType::Result->value, 'date' => '2026-11-10']);

        $response = $this->getJson('/calendar?month=2026-09')->assertOk();

        $this->assertCount(2, $response->json('contests.0.events'));
    }
}
