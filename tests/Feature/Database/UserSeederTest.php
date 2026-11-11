<?php

use App\Models\User;
use App\ValueObjects\UserPreferences;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    config(['radar.seed_user.email' => 'dono@example.com']);
});

it('creates the user with every state selected', function () {
    config(['radar.seed_user.password' => 'user123']);

    $this->seed(UserSeeder::class);

    $user = User::query()->where('email', 'dono@example.com')->sole();
    expect(Hash::check('user123', $user->password))->toBeTrue()
        ->and($user->preferences->states)->toContain('PB', 'NACIONAL');
});

it('changes the password on rerun without resetting the state filter', function () {
    config(['radar.seed_user.password' => 'user123']);
    $this->seed(UserSeeder::class);

    $user = User::query()->where('email', 'dono@example.com')->sole();
    $user->preferences = new UserPreferences(states: ['PB', 'PE']);
    $user->save();

    config(['radar.seed_user.password' => 'outra-senha-bem-longa']);
    $this->seed(UserSeeder::class);

    $user->refresh();
    expect(Hash::check('outra-senha-bem-longa', $user->password))->toBeTrue()
        ->and($user->preferences->states)->toBe(['PB', 'PE']);
});

it('refuses a weak password in production', function (string $password) {
    app()->detectEnvironment(fn () => 'production');
    config(['radar.seed_user.password' => $password]);

    $this->artisan('db:seed', ['--class' => UserSeeder::class, '--force' => true]);

    expect(User::query()->where('email', 'dono@example.com')->exists())->toBeFalse();
})->with(['user123', 'change-me-before-deploy']);

it('accepts a strong password in production', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['radar.seed_user.password' => 'uma-senha-forte-de-verdade']);

    $this->artisan('db:seed', ['--class' => UserSeeder::class, '--force' => true]);

    expect(User::query()->where('email', 'dono@example.com')->exists())->toBeTrue();
});
