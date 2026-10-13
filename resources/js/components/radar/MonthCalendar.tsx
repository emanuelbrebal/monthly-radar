import { useMemo, useRef, useState } from 'react';

import { EventFolder } from '@/components/radar/EventFolder';
import { csrfFetch } from '@/lib/csrf-fetch';
import { notifyError } from '@/lib/notify';
import type { ContestDto } from '@/types/radar';

const MONTH_NAMES = [
    'janeiro',
    'fevereiro',
    'março',
    'abril',
    'maio',
    'junho',
    'julho',
    'agosto',
    'setembro',
    'outubro',
    'novembro',
    'dezembro',
];

const ROMAN_TABLE: Array<[number, string]> = [
    [1000, 'M'],
    [900, 'CM'],
    [500, 'D'],
    [400, 'CD'],
    [100, 'C'],
    [90, 'XC'],
    [50, 'L'],
    [40, 'XL'],
    [10, 'X'],
    [9, 'IX'],
    [5, 'V'],
    [4, 'IV'],
    [1, 'I'],
];

function toRoman(year: number): string {
    let remainder = year;
    let result = '';

    for (const [value, symbol] of ROMAN_TABLE) {
        while (remainder >= value) {
            result += symbol;
            remainder -= value;
        }
    }

    return result;
}

function pad(n: number): string {
    return n.toString().padStart(2, '0');
}

function shiftMonth(month: string, delta: number): string {
    const [year, m] = month.split('-').map(Number);
    const date = new Date(year, m - 1 + delta, 1);

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;
}

/** Segunda = 0 … domingo = 6. */
function mondayIndex(jsDay: number): number {
    return (jsDay + 6) % 7;
}

function buildGrid(month: string): Date[] {
    const [year, m] = month.split('-').map(Number);
    const firstOfMonth = new Date(year, m - 1, 1);
    const start = new Date(firstOfMonth);
    start.setDate(start.getDate() - mondayIndex(firstOfMonth.getDay()));

    return Array.from({ length: 42 }, (_, i) => {
        const day = new Date(start);
        day.setDate(start.getDate() + i);

        return day;
    });
}

function isoDate(d: Date): string {
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

type MonthCalendarProps = {
    initialMonth: string;
    initialContests: ContestDto[];
    onSelectContest?: (contest: ContestDto) => void;
};

/** docs/03-ui-inventario.md → "Calendário". */
export function MonthCalendar({ initialMonth, initialContests, onSelectContest }: MonthCalendarProps) {
    const [month, setMonth] = useState(initialMonth);
    const [fetchedContests, setFetchedContests] = useState<ContestDto[] | null>(null);
    const [loadingMonth, setLoadingMonth] = useState(false);
    const [expandedDate, setExpandedDate] = useState<string | null>(null);
    const requestedMonth = useRef(initialMonth);
    const contests = useMemo(
        () => (month === initialMonth ? initialContests : (fetchedContests ?? [])),
        [month, initialMonth, initialContests, fetchedContests],
    );

    function goToMonth(target: string) {
        setMonth(target);
        requestedMonth.current = target;

        if (target === initialMonth) {
            return;
        }

        setLoadingMonth(true);

        csrfFetch(`/calendar?month=${target}`)
            .then((r) => {
                if (!r.ok) {
                    throw new Error('failed');
                }

                return r.json();
            })
            .then((data: { contests: ContestDto[] }) => {
                if (requestedMonth.current === target) {
                    setFetchedContests(data.contests);
                }
            })
            .catch(() => notifyError('Não deu pra carregar esse mês. Tenta de novo.'))
            .finally(() => {
                if (requestedMonth.current === target) {
                    setLoadingMonth(false);
                }
            });
    }

    const eventsByDate = useMemo(() => {
        const map = new Map<string, Array<{ contest: ContestDto; eventId: number }>>();

        for (const contest of contests) {
            for (const event of contest.events) {
                const list = map.get(event.date) ?? [];
                list.push({ contest, eventId: event.id });
                map.set(event.date, list);
            }
        }

        return map;
    }, [contests]);

    const days = useMemo(() => buildGrid(month), [month]);
    const [year, m] = month.split('-').map(Number);
    const todayIso = isoDate(new Date());
    const weekLabels = ['seg', 'ter', 'qua', 'qui', 'sex', 'sáb', 'dom'];

    return (
        <div>
            <div className="mb-2 flex items-center justify-between">
                <h2 className="rm-display text-3xl">
                    {MONTH_NAMES[m - 1]} · {toRoman(year)}
                    {loadingMonth && <span className="rm-mono ml-2 align-middle text-xs text-ink-mid">carregando…</span>}
                </h2>
                <div className="flex items-center gap-2">
                    <button type="button" className="rounded-md border border-ink px-2 py-0.5" onClick={() => goToMonth(shiftMonth(month, -1))}>
                        ←
                    </button>
                    <button
                        type="button"
                        className="rm-mono rounded-md border border-ink px-2 py-0.5 text-[10px] uppercase"
                        onClick={() => goToMonth(initialMonth)}
                    >
                        hoje
                    </button>
                    <button type="button" className="rounded-md border border-ink px-2 py-0.5" onClick={() => goToMonth(shiftMonth(month, 1))}>
                        →
                    </button>
                </div>
            </div>

            <div className="grid grid-cols-7 overflow-hidden rounded-lg border-t border-l border-ink/20 rm-dot-grid">
                {weekLabels.map((label) => (
                    <div key={label} className="rm-mono border-r border-b border-ink/20 bg-paper-soft px-1 py-0.5 text-[10px] uppercase">
                        {label}
                    </div>
                ))}

                {days.map((day) => {
                    const iso = isoDate(day);
                    const inMonth = day.getMonth() === m - 1;
                    const entries = eventsByDate.get(iso) ?? [];
                    const visible = expandedDate === iso ? entries : entries.slice(0, 3);
                    const overflow = entries.length - visible.length;

                    return (
                        <div
                            key={iso}
                            className={`relative flex min-h-16 flex-col gap-0.5 border-r border-b border-ink/20 p-1 ${inMonth ? '' : 'opacity-40'}`}
                        >
                            <span
                                className={`rm-mono self-end px-1 text-[10px] ${iso === todayIso ? 'bg-highlight' : ''}`}
                            >
                                {day.getDate()}
                            </span>

                            {visible.map(({ contest, eventId }) => {
                                const event = contest.events.find((e) => e.id === eventId)!;

                                return <EventFolder key={`${contest.id}-${eventId}`} contest={contest} event={event} onSelect={onSelectContest} />;
                            })}

                            {overflow > 0 && (
                                <button
                                    type="button"
                                    className="rm-mono self-start text-[9px] text-ink-mid underline"
                                    onClick={() => setExpandedDate(iso)}
                                >
                                    +{overflow}
                                </button>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
