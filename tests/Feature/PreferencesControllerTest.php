<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreferencesControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_update_preferences(): void
    {
        $this->patch('/preferences/states', ['states' => ['RN']])->assertRedirect(route('login'));
    }

    public function test_it_saves_the_state_selection(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->patch('/preferences/states', ['states' => ['RN', 'CE', 'NACIONAL']])
            ->assertRedirect();

        $this->assertSame(['RN', 'CE', 'NACIONAL'], $user->refresh()->preferences->states);
    }

    public function test_it_rejects_an_invalid_state(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->patchJson('/preferences/states', ['states' => ['XX']])->assertStatus(422);
    }

    public function test_it_accepts_an_empty_selection(): void
    {
        $user = User::factory()->create(['preferences' => ['states' => ['RN']]]);
        $this->actingAs($user);

        $this->patch('/preferences/states', ['states' => []])->assertRedirect();

        $this->assertSame([], $user->refresh()->preferences->states);
    }
}
