<?php

use App\Models\Contest;
use Carbon\CarbonImmutable;

it('is not expired 23h59 after closing', function () {
    $contest = Contest::factory()->closedAt(now()->subHours(23)->subMinutes(59))->create();

    expect($contest->isExpired())->toBeFalse();
});

it('is expired 24h01 after closing', function () {
    $contest = Contest::factory()->closedAt(now()->subDay()->subMinute())->create();

    expect($contest->isExpired())->toBeTrue();
});

it('never expires once enrolled', function () {
    $contest = Contest::factory()
        ->closedAt(now()->subDays(10))
        ->enrolled()
        ->create();

    expect($contest->isExpired())->toBeFalse();
});

it('stops being expired when the contest reopens', function () {
    $contest = Contest::factory()->closedAt(now()->subDays(10))->create();

    expect($contest->isExpired())->toBeTrue();

    $contest->update(['status' => 'registration_open', 'closed_at' => null]);

    expect($contest->refresh()->isExpired())->toBeFalse();
});

it('uses registration_end + 1 day as the effective closing moment when closed_at is null', function () {
    $contest = Contest::factory()->create([
        'status' => 'registration_closed',
        'registration_end' => CarbonImmutable::now()->subDays(2)->toDateString(),
        'closed_at' => null,
    ]);

    expect($contest->isExpired())->toBeTrue();
});

it('is dismissed only when enrollment_status is dismissed', function () {
    $contest = Contest::factory()->dismissed()->create();

    expect($contest->isDismissed())->toBeTrue();
});
