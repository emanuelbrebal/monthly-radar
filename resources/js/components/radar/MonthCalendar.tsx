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

    const daysInMonth = new Date(year, m, 0).getDate();
    const weeks = Math.ceil(
        (mondayIndex(firstOfMonth.getDay()) + daysInMonth) / 7,
    );

    return Array.from({ length: weeks * 7 }, (_, i) => {
        const day = new Date(start);
        day.setDate(start.getDate() + i);

        return day;
    });
}

function isoDate(d: Date): string {
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

const NAV_BUTTON =
    't-body inline-flex min-h-6 min-w-6 items-center justify-center rounded-md border border-ink bg-card px-2 py-0.5';

type MonthCalendarProps = {
    initialMonth: string;
    initialContests: ContestDto[];
    onSelectContest?: (contest: ContestDto) => void;
};

/** docs/03-ui-inventario.md → "Calendário". */
export function MonthCalendar({
    initialMonth,
    initialContests,
    onSelectContest,
}: MonthCalendarProps) {
    const [month, setMonth] = useState(initialMonth);
    const [fetchedContests, setFetchedContests] = useState<ContestDto[] | null>(
        null,
    );
    const [loadingMonth, setLoadingMonth] = useState(false);
    const [expandedDate, setExpandedDate] = useState<string | null>(null);
    const requestedMonth = useRef(initialMonth);
    const contests = useMemo(
        () =>
            month === initialMonth ? initialContests : (fetchedContests ?? []),
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
            .catch(() =>
                notifyError('Não deu pra carregar esse mês. Tenta de novo.'),
            )
            .finally(() => {
                if (requestedMonth.current === target) {
                    setLoadingMonth(false);
                }
            });
    }

    const eventsByDate = useMemo(() => {
        const map = new Map<
            string,
            Array<{ contest: ContestDto; eventId: number }>
        >();

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
            <div className="mb-2 flex items-center justify-between gap-2">
                <h2 className="rm-display text-display">
                    {MONTH_NAMES[m - 1]} · {year}
                    {loadingMonth && (
                        <span className="t-body ml-2 align-middle text-muted-fg">
                            carregando…
                        </span>
                    )}
                </h2>
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        aria-label="mês anterior"
                        className={NAV_BUTTON}
                        onClick={() => goToMonth(shiftMonth(month, -1))}
                    >
                        <span aria-hidden="true">←</span>
                    </button>
                    <button
                        type="button"
                        aria-label="ir para hoje"
                        className={NAV_BUTTON}
                        onClick={() => goToMonth(initialMonth)}
                    >
                        hoje
                    </button>
                    <button
                        type="button"
                        aria-label="próximo mês"
                        className={NAV_BUTTON}
                        onClick={() => goToMonth(shiftMonth(month, 1))}
                    >
                        <span aria-hidden="true">→</span>
                    </button>
                </div>
            </div>

            <div className="grid grid-cols-7 overflow-hidden rounded-lg border-t border-l border-line-strong">
                {weekLabels.map((label) => (
                    <div
                        key={label}
                        className="t-label border-r border-b border-line-strong bg-paper-soft px-1 py-1"
                    >
                        {label}
                    </div>
                ))}

                {days.map((day) => {
                    const iso = isoDate(day);
                    const inMonth = day.getMonth() === m - 1;
                    const isToday = iso === todayIso;
                    const entries = eventsByDate.get(iso) ?? [];
                    const visible =
                        expandedDate === iso ? entries : entries.slice(0, 3);
                    const overflow = entries.length - visible.length;

                    const cellTone = isToday
                        ? 'bg-highlight outline-2 -outline-offset-2 outline-ink text-ink z-10'
                        : inMonth
                          ? 'bg-card text-ink'
                          : 'bg-paper-soft text-muted-fg';

                    return (
                        <div
                            key={iso}
                            className={`relative flex min-h-[4.5rem] flex-col gap-0.5 border-r border-b border-line-strong p-1 ${cellTone}`}
                        >
                            <span
                                className={`t-body self-end px-1 ${isToday ? 'font-semibold' : inMonth ? '' : 'font-normal'}`}
                            >
                                {day.getDate()}
                                {isToday && (
                                    <span className="sr-only"> hoje</span>
                                )}
                            </span>

                            {visible.map(({ contest, eventId }) => {
                                const event = contest.events.find(
                                    (e) => e.id === eventId,
                                )!;

                                return (
                                    <EventFolder
                                        key={`${contest.id}-${eventId}`}
                                        contest={contest}
                                        event={event}
                                        onSelect={onSelectContest}
                                    />
                                );
                            })}

                            {overflow > 0 && (
                                <button
                                    type="button"
                                    className="t-label inline-flex min-h-6 min-w-6 items-center self-start px-1 text-ink underline"
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
