<?php

use App\Enums\ExamOutcome;
use App\Models\ExamResult;

it('computes percent, margin and cutoff percent', function () {
    $result = new ExamResult(['score' => 62, 'max_score' => 70, 'cutoff_score' => 57.5]);

    expect($result->percent())->toBe(88.6);
    expect($result->margin())->toBe(4.5);
    expect($result->cutoffPercent())->toBe(82.1);
});

it('returns null margin without a cutoff', function () {
    $result = new ExamResult(['score' => 50, 'max_score' => 100]);

    expect($result->margin())->toBeNull();
    expect($result->cutoffPercent())->toBeNull();
});

it('counts passed and reserve as approved', function () {
    expect(ExamOutcome::Passed->isApproved())->toBeTrue();
    expect(ExamOutcome::Reserve->isApproved())->toBeTrue();
    expect(ExamOutcome::Pending->isApproved())->toBeFalse();
    expect(ExamOutcome::Failed->isApproved())->toBeFalse();
});
