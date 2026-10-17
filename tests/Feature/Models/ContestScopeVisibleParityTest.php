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

it('scopeArchived is the exact complement of scopeVisible', function () {
    Contest::factory()->count(2)->create();
    Contest::factory()->create(['registration_end' => null, 'closed_at' => null]);
    Contest::factory()->closedAt(now()->subHours(23))->create();
    Contest::factory()->closedAt(now()->subDays(3))->create(); // expirado
    Contest::factory()->closedAt(now()->subDays(10))->enrolled()->create();
    Contest::factory()->dismissed()->create();
    Contest::factory()->closedAt(now()->subDays(5))->dismissed()->create();
    Contest::factory()->create(['closed_at' => null, 'registration_end' => now()->subDays(4)->toDateString()]); // expirado via registration_end

    $all = Contest::query()->pluck('id')->sort()->values()->all();
    $visible = Contest::query()->visible()->pluck('id')->all();
    $archived = Contest::query()->archived()->pluck('id')->all();

    expect(array_intersect($visible, $archived))->toBe([]);
    expect(collect([...$visible, ...$archived])->sort()->values()->all())->toBe($all);
    expect($archived)->toHaveCount(4);

    Contest::query()->archived()->get()->each(
        fn (Contest $c) => expect($c->archiveReason())->toBe($c->isDismissed() ? 'dismissed' : 'expired'),
    );
    Contest::query()->visible()->get()->each(fn (Contest $c) => expect($c->archiveReason())->toBeNull());
});
