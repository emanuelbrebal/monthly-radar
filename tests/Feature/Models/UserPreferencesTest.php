<?php

use App\Models\User;
use App\ValueObjects\UserPreferences;

it('casts preferences to and from the database', function () {
    $user = User::factory()->create();

    $user->preferences = new UserPreferences(states: ['RN', 'CE', 'NACIONAL']);
    $user->save();

    $fresh = User::query()->find($user->id);

    expect($fresh->preferences)->toBeInstanceOf(UserPreferences::class)
        ->and($fresh->preferences->states)->toBe(['RN', 'CE', 'NACIONAL']);
});

it('defaults preferences to an empty state list', function () {
    $user = User::factory()->create();

    expect($user->preferences)->toBeInstanceOf(UserPreferences::class)
        ->and($user->preferences->states)->toBe([]);
});
