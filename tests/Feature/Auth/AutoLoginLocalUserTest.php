<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['email' => 'dono@example.com']);
    config(['radar.seed_user.email' => 'dono@example.com']);
});

function asEnvironment(string $env): void
{
    app()->detectEnvironment(fn () => $env);
}

it('logs the seed user in on loopback when enabled in local', function () {
    config(['radar.auto_login' => true]);
    asEnvironment('local');

    $this->get(route('home'))->assertOk();

    $this->assertAuthenticatedAs($this->user);
});

it('sends the login page straight to the app', function () {
    config(['radar.auto_login' => true]);
    asEnvironment('local');

    $this->get(route('login'))->assertRedirect();

    $this->assertAuthenticatedAs($this->user);
});

it('does nothing when the flag is off', function () {
    config(['radar.auto_login' => false]);
    asEnvironment('local');

    $this->get(route('home'))->assertRedirect(route('login'));

    $this->assertGuest();
});

it('does nothing outside the local environment', function () {
    config(['radar.auto_login' => true]);
    asEnvironment('production');

    $this->get(route('home'))->assertRedirect(route('login'));

    $this->assertGuest();
});

it('does nothing for requests that do not come from loopback', function () {
    config(['radar.auto_login' => true]);
    asEnvironment('local');

    $this->withServerVariables(['REMOTE_ADDR' => '192.168.0.10'])
        ->get(route('home'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
