<?php

namespace Tests\Feature;

use App\Models\Contest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DismissedContestsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_list_dismissed_contests(): void
    {
        $this->get('/contests/dismissed')->assertRedirect(route('login'));
    }

    public function test_it_lists_only_dismissed_contests(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $dismissed = Contest::factory()->dismissed()->create();
        Contest::factory()->create();

        $response = $this->getJson('/contests/dismissed')->assertOk();

        $ids = collect($response->json('contests'))->pluck('id');
        $this->assertEquals([$dismissed->id], $ids->all());
    }
}
