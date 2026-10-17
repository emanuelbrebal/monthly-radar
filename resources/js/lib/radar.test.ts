import assert from 'node:assert/strict';
import { test } from 'node:test';

import {
    compareByNextEvent,
    nextEvent,
    nextEventLabel,
    upcomingDeadline,
} from './radar.ts';

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

function typed(
    ...events: Array<{
        type: 'registration_end' | 'exam' | 'result' | 'other';
        date: string;
        label?: string;
    }>
) {
    return {
        events: events.map((e, id) => ({
            id,
            type: e.type,
            type_label: e.type,
            date: e.date,
            label: e.label ?? null,
        })),
    };
}

test('upcomingDeadline skips past events and counts days', () => {
    const d = upcomingDeadline(
        typed(
            { type: 'registration_end', date: '2026-10-01' },
            { type: 'registration_end', date: '2026-10-21' },
            { type: 'exam', date: '2026-12-20' },
        ),
        TODAY,
    );

    assert.deepEqual(d, {
        label: 'insc. até',
        date: '21/10',
        days: 6,
        countdown: '6d',
        urgent: true,
    });
});

test('upcomingDeadline says hoje/amanhã and is not urgent after 7 days', () => {
    assert.equal(
        upcomingDeadline(typed({ type: 'exam', date: TODAY }), TODAY)
            ?.countdown,
        'hoje',
    );
    assert.equal(
        upcomingDeadline(typed({ type: 'exam', date: '2026-10-16' }), TODAY)
            ?.countdown,
        'amanhã',
    );

    const far = upcomingDeadline(
        typed({ type: 'exam', date: '2026-12-13' }),
        TODAY,
    );

    assert.equal(far?.urgent, false);
    assert.equal(far?.days, 59);
});

test('upcomingDeadline uses the event label for result/other and null when nothing is ahead', () => {
    assert.equal(
        upcomingDeadline(
            typed({ type: 'result', date: '2026-10-19', label: 'gabarito' }),
            TODAY,
        )?.label,
        'gabarito',
    );
    assert.equal(
        upcomingDeadline(typed({ type: 'exam', date: '2026-09-01' }), TODAY),
        null,
    );
});

test('upcomingDeadline skips registration and fee deadlines once they are done', () => {
    const events = typed(
        { type: 'registration_end', date: '2026-11-04' },
        { type: 'exam', date: '2026-12-13' },
    ).events;
    const fee = {
        id: 9,
        type: 'fee_deadline' as const,
        type_label: 'taxa',
        date: '2026-11-06',
        label: null,
    };
    const all = [events[0], fee, events[1]];

    assert.equal(
        upcomingDeadline(
            { events: all, enrollment_status: 'interested' },
            TODAY,
        )?.label,
        'insc. até',
    );
    assert.equal(
        upcomingDeadline({ events: all, enrollment_status: 'enrolled' }, TODAY)
            ?.label,
        'taxa até',
    );
    assert.equal(
        upcomingDeadline({ events: all, enrollment_status: 'fee_paid' }, TODAY)
            ?.label,
        'prova',
    );
});
