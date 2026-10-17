<?php

use App\Models\Contest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['radar.llm.api_key' => null]);
});

it('redirects guests to login', function () {
    $this->get('/archive')->assertRedirect(route('login'));
});

it('lists only contests that left the desk, with the reason', function () {
    $this->actingAs(User::factory()->create());

    Contest::factory()->create(); // na mesa
    Contest::factory()->closedAt(now()->subDays(10))->enrolled()->create(); // inscrito: fica na mesa
    $expired = Contest::factory()->closedAt(now()->subDays(3))->create();
    $dismissed = Contest::factory()->dismissed()->create();

    $this->get('/archive')->assertInertia(fn (Assert $page) => $page
        ->component('radar/archive')
        ->has('contests', 2)
        ->where('counts', ['all' => 2, 'expired' => 1, 'dismissed' => 1])
        ->where('reason', null)
        ->where('contests', fn ($contests) => collect($contests)->pluck('archive_reason', 'id')->sortKeys()->all() === collect([
            $expired->id => 'expired',
            $dismissed->id => 'dismissed',
        ])->sortKeys()->all())
    );
});

it('filters by reason', function () {
    $this->actingAs(User::factory()->create());

    $expired = Contest::factory()->closedAt(now()->subDays(3))->create();
    Contest::factory()->dismissed()->create();

    $this->get('/archive?reason=expired')->assertInertia(fn (Assert $page) => $page
        ->has('contests', 1)
        ->where('contests.0.id', $expired->id)
        ->where('reason', 'expired')
        ->where('counts.all', 2)
    );

    $this->get('/archive?reason=bogus')->assertInertia(fn (Assert $page) => $page
        ->has('contests', 2)
        ->where('reason', null)
    );
});

it('brings an expired contest back to the desk when marked as enrolled', function () {
    $this->actingAs(User::factory()->create());

    $contest = Contest::factory()->closedAt(now()->subDays(3))->create();

    expect(Contest::query()->archived()->whereKey($contest->id)->exists())->toBeTrue();

    $this->patchJson("/contests/{$contest->id}/enrollment", ['status' => 'exam_taken'])->assertOk();

    expect(Contest::query()->visible()->whereKey($contest->id)->exists())->toBeTrue();
    expect(Contest::query()->archived()->whereKey($contest->id)->exists())->toBeFalse();
});
