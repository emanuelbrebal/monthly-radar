<?php

use App\Models\Contest;
use App\Services\Radar\StateFilter;

it('scopeMatchingStates matches StateFilter over a mixed dataset', function () {
    Contest::factory()->inStates(['RN', 'CE'])->create();
    Contest::factory()->inStates(['SP'])->create();
    Contest::factory()->nationwide()->create();
    Contest::factory()->create(); // states = [], nationwide = false: local a definir

    $selections = [
        [],
        ['NACIONAL'],
        ['RN'],
        ['SP', 'NACIONAL'],
        ['PB'],
    ];

    $filter = app(StateFilter::class);
    $all = Contest::query()->get();

    foreach ($selections as $selection) {
        $viaScope = Contest::query()->matchingStates($selection)->pluck('id')->sort()->values();

        $viaFilter = $all
            ->filter(fn (Contest $c) => $filter->matches($selection, $c->states, $c->nationwide))
            ->pluck('id')
            ->sort()
            ->values();

        expect($viaScope->all())->toBe($viaFilter->all());
    }
});
