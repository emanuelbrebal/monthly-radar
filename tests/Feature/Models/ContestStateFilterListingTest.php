<?php

use App\Models\Contest;

/**
 * Composição usada pela listagem (docs/06-filtro-estados.md):
 * Contest::visible()->where(fn ($q) => $q->matchingStates($s)
 *     ->orWhereNotNull('pinned_slot')
 *     ->orWhereIn('enrollment_status', [...a partir de enrolled]));
 */
function visibleForStates(array $selection)
{
    return Contest::query()->visible()->where(
        fn ($q) => $q->matchingStates($selection)
            ->orWhereNotNull('pinned_slot')
            ->orWhereIn('enrollment_status', ['enrolled', 'fee_paid', 'venue_released', 'exam_taken'])
    );
}

it('keeps a pinned contest visible even outside the state filter', function () {
    $contest = Contest::factory()->inStates(['SP'])->pinned(0)->create();

    expect(visibleForStates(['RN'])->find($contest->id))->not->toBeNull();
});

it('keeps an enrolled contest visible even outside the state filter', function () {
    $contest = Contest::factory()->inStates(['SP'])->enrolled()->create();

    expect(visibleForStates(['RN'])->find($contest->id))->not->toBeNull();
});

it('drops a not-enrolled, unpinned contest outside the state filter', function () {
    $contest = Contest::factory()->inStates(['SP'])->create();

    expect(visibleForStates(['RN'])->find($contest->id))->toBeNull();
});

it('keeps a contest inside the state filter', function () {
    $contest = Contest::factory()->inStates(['RN'])->create();

    expect(visibleForStates(['RN'])->find($contest->id))->not->toBeNull();
});
