<?php

use App\Exceptions\Radar\ContestDismissedException;
use App\Exceptions\Radar\PinSlotsFullException;
use App\Models\Contest;
use App\Services\Radar\PinService;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->service = new PinService;
});

it('pins into the first free slot', function () {
    Contest::factory()->pinned(0)->create();

    $contest = Contest::factory()->create();

    $pinned = $this->service->pin($contest);

    expect($pinned->pinned_slot)->toBe(1);
});

it('refuses to pin a dismissed contest', function () {
    $contest = Contest::factory()->dismissed()->create();

    $this->service->pin($contest);
})->throws(ContestDismissedException::class);

it('refuses to pin when the case is full', function () {
    Contest::factory()->count(Contest::PIN_SLOTS)->sequence(
        ...array_map(fn (int $slot) => ['pinned_slot' => $slot], range(0, Contest::PIN_SLOTS - 1)),
    )->create();

    $contest = Contest::factory()->create();

    $this->service->pin($contest);
})->throws(PinSlotsFullException::class);

it('unpins a contest', function () {
    $contest = Contest::factory()->pinned(3)->create();

    $unpinned = $this->service->unpin($contest);

    expect($unpinned->pinned_slot)->toBeNull();
});

it('enforces a unique pinned_slot at the database level', function () {
    Contest::factory()->pinned(0)->create();

    $other = Contest::factory()->create();

    expect(fn () => Contest::query()->where('id', $other->id)->update(['pinned_slot' => 0]))
        ->toThrow(QueryException::class);
});

it('reorders the whole case without unique constraint collisions', function () {
    $a = Contest::factory()->pinned(0)->create();
    $b = Contest::factory()->pinned(1)->create();

    $this->service->reorder([1 => $a->id, 0 => $b->id]);

    expect($a->refresh()->pinned_slot)->toBe(1)
        ->and($b->refresh()->pinned_slot)->toBe(0);
});
