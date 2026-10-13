<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Models\Contest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_update_enrollment(): void
    {
        $contest = Contest::factory()->create();

        $this->patch("/contests/{$contest->id}/enrollment", ['status' => 'interested'])
            ->assertRedirect(route('login'));
    }

    public function test_it_updates_the_checklist_step(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $contest = Contest::factory()->create();

        $this->patchJson("/contests/{$contest->id}/enrollment", ['status' => 'fee_paid'])
            ->assertOk()
            ->assertJsonPath('contest.enrollment_status', 'fee_paid');

        $this->assertSame(EnrollmentStatus::FeePaid, $contest->refresh()->enrollment_status);
    }

    public function test_it_rejects_dismissed_as_a_checklist_step(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $contest = Contest::factory()->create();

        $this->patchJson("/contests/{$contest->id}/enrollment", ['status' => 'dismissed'])
            ->assertStatus(422);
    }

    public function test_it_rejects_updating_a_dismissed_contest(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $contest = Contest::factory()->dismissed()->create();

        $this->patchJson("/contests/{$contest->id}/enrollment", ['status' => 'interested'])
            ->assertStatus(422);
    }
}
