import assert from 'node:assert/strict';
import { test } from 'node:test';

import { compareByNextEvent, nextEvent, nextEventLabel } from './radar.ts';

const TODAY = '2026-10-15';

function contest(...dates: string[]) {
    return {
        events: dates.map((date, id) => ({
            id,
            type: 'other' as const,
            type_label: 'outro',
            date,
            label: null,
        })),
    };
}

function order(contests: Record<string, ReturnType<typeof contest>>) {
    return Object.entries(contests)
        .sort(([, a], [, b]) => compareByNextEvent(a, b, TODAY))
        .map(([name]) => name);
}

test('nextEvent picks the first event from today on, else the last one', () => {
    assert.equal(
        nextEvent(contest('2026-10-01', '2026-10-15', '2026-11-01'), TODAY)
            ?.date,
        '2026-10-15',
    );
    assert.equal(
        nextEvent(contest('2026-09-01', '2026-10-01'), TODAY)?.date,
        '2026-10-01',
    );
    assert.equal(nextEvent(contest(), TODAY), null);
});

test('nextEventLabel formats label and day/month', () => {
    assert.match(
        nextEventLabel(contest('2099-03-09')) ?? '',
        /outro · 09\/03$/,
    );
    assert.equal(nextEventLabel(contest()), null);
});

test('a past first event does not beat a nearer upcoming event', () => {
    // `soon` começou em agosto mas o próximo evento é dia 20; `later` só tem evento em novembro.
    const result = order({
        later: contest('2026-11-05'),
        soon: contest('2026-08-01', '2026-10-20'),
    });

    assert.deepEqual(result, ['soon', 'later']);
});

test('upcoming first, then past-only (most recent first), then no events', () => {
    const result = order({
        none: contest(),
        oldest: contest('2026-06-01'),
        recent: contest('2026-10-01'),
        upcoming: contest('2026-12-01'),
        today: contest('2026-10-15'),
    });

    assert.deepEqual(result, ['today', 'upcoming', 'recent', 'oldest', 'none']);
});
