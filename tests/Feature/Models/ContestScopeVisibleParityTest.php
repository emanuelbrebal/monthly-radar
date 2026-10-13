<?php

use App\Models\Contest;

it('scopeVisible matches isExpired()/isDismissed() over a mixed dataset', function () {
    Contest::factory()->count(3)->create(); // published, nunca fecha
    Contest::factory()->closedAt(now()->subHours(23)->subMinutes(59))->create(); // borda: visível
    Contest::factory()->closedAt(now()->subDay()->subMinute())->create(); // borda: expirado
    Contest::factory()->closedAt(now()->subDays(10))->enrolled()->create(); // inscrito: nunca expira
    Contest::factory()->dismissed()->create(); // descartado
    Contest::factory()->closedAt(now()->subDays(5))->dismissed()->create(); // descartado e expirado

    $all = Contest::query()->get();
    $visibleIds = Contest::query()->visible()->pluck('id')->sort()->values();

    $expectedIds = $all
        ->filter(fn (Contest $c) => ! $c->isExpired() && ! $c->isDismissed())
        ->pluck('id')
        ->sort()
        ->values();

    expect($visibleIds->all())->toBe($expectedIds->all());
});
