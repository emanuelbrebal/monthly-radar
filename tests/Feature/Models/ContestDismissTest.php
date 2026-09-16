<?php

use App\Enums\EnrollmentStatus;
use App\Models\Contest;

it('leaves the visible scope immediately when dismissed', function () {
    $contest = Contest::factory()->pinned(0)->create();

    expect(Contest::query()->visible()->find($contest->id))->not->toBeNull();

    $contest->update(['enrollment_status' => EnrollmentStatus::Dismissed->value]);

    expect(Contest::query()->visible()->find($contest->id))->toBeNull();
});

it('releases the pinned slot when dismissed', function () {
    $contest = Contest::factory()->pinned(0)->create();

    $contest->update(['enrollment_status' => EnrollmentStatus::Dismissed->value]);

    expect($contest->refresh()->pinned_slot)->toBeNull();
});

it('comes back to the visible scope when un-dismissed', function () {
    $contest = Contest::factory()->dismissed()->create();

    $contest->update(['enrollment_status' => EnrollmentStatus::NotEnrolled->value]);

    expect(Contest::query()->visible()->find($contest->id))->not->toBeNull();
});
