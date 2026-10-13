<?php

namespace Tests\Feature;

use App\Models\Contest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PinControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_pin(): void
    {
        $contest = Contest::factory()->create();

        $this->post("/contests/{$contest->id}/pin")->assertRedirect(route('login'));
    }

    public function test_it_pins_a_contest_to_the_first_free_slot(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $contest = Contest::factory()->create();

        $this->postJson("/contests/{$contest->id}/pin")
            ->assertOk()
            ->assertJsonPath('contest.pinned_slot', 0);
    }

    public function test_it_rejects_pinning_a_dismissed_contest(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $contest = Contest::factory()->dismissed()->create();

        $this->postJson("/contests/{$contest->id}/pin")->assertStatus(422);
    }

    public function test_it_rejects_pinning_when_the_case_is_full(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        for ($i = 0; $i < Contest::PIN_SLOTS; $i++) {
            Contest::factory()->pinned($i)->create();
        }

        $contest = Contest::factory()->create();

        $this->postJson("/contests/{$contest->id}/pin")->assertStatus(422);
    }

    public function test_it_unpins_a_contest(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $contest = Contest::factory()->pinned(3)->create();

        $this->deleteJson("/contests/{$contest->id}/pin")
            ->assertOk()
            ->assertJsonPath('contest.pinned_slot', null);
    }

    public function test_it_reorders_the_case(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $a = Contest::factory()->pinned(0)->create();
        $b = Contest::factory()->pinned(1)->create();

        $this->patchJson('/pins', ['order' => [1 => $a->id, 0 => $b->id]])->assertOk();

        $this->assertSame(1, $a->refresh()->pinned_slot);
        $this->assertSame(0, $b->refresh()->pinned_slot);
    }

    public function test_it_rejects_reorder_with_too_many_slots(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $order = array_fill(0, Contest::PIN_SLOTS + 1, null);

        $this->patchJson('/pins', ['order' => $order])->assertStatus(422);
    }
}
