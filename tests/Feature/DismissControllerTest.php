<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Models\Contest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DismissControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_dismiss(): void
    {
        $contest = Contest::factory()->create();

        $this->post("/contests/{$contest->id}/dismiss")->assertRedirect(route('login'));
    }

    public function test_it_dismisses_a_contest_with_a_reason(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $contest = Contest::factory()->pinned(2)->create();

        $this->postJson("/contests/{$contest->id}/dismiss", ['reason' => 'cargo errado'])
            ->assertOk()
            ->assertJsonPath('contest.enrollment_status', 'dismissed')
            ->assertJsonPath('contest.dismissed_reason', 'cargo errado')
            ->assertJsonPath('contest.pinned_slot', null);

        $contest->refresh();
        $this->assertSame(EnrollmentStatus::Dismissed, $contest->enrollment_status);
        $this->assertNull($contest->pinned_slot);
        $this->assertNotNull($contest->dismissed_at);
    }

    public function test_it_dismisses_without_a_reason(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $contest = Contest::factory()->create();

        $this->postJson("/contests/{$contest->id}/dismiss")
            ->assertOk()
            ->assertJsonPath('contest.dismissed_reason', null);
    }

    public function test_it_restores_a_dismissed_contest(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $contest = Contest::factory()->dismissed()->create();

        $this->postJson("/contests/{$contest->id}/restore")
            ->assertOk()
            ->assertJsonPath('contest.enrollment_status', 'not_enrolled')
            ->assertJsonPath('contest.dismissed_reason', null);

        $contest->refresh();
        $this->assertSame(EnrollmentStatus::NotEnrolled, $contest->enrollment_status);
        $this->assertNull($contest->dismissed_at);
    }
}
