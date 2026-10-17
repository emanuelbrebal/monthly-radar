import type { ContestDto, EventTypeValue } from '@/types/radar';

/** Hoje em `YYYY-MM-DD` no fuso local (UTC viraria o dia às 21h no Brasil), igual às datas dos eventos. */
export function todayIso(): string {
    const now = new Date();
    const pad = (n: number) => n.toString().padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

type WithEvents = Pick<ContestDto, 'events'>;

/** Próximo evento (≥ hoje) ou, se todos já passaram, o último; `null` sem eventos. */
export function nextEvent(
    contest: WithEvents,
    today: string,
): ContestDto['events'][number] | null {
    return (
        contest.events.find((e) => e.date >= today) ??
        contest.events.at(-1) ??
        null
    );
}

/** Próximo evento no formato "prova · 13/10". */
export function nextEventLabel(contest: WithEvents): string | null {
    const event = nextEvent(contest, todayIso());

    if (!event) {
        return null;
    }

    const [, month, day] = event.date.split('-');

    return `${event.label ?? event.type_label} · ${day}/${month}`;
}

/**
 * Ordem da caixa de itens, coerente com `nextEventLabel`: quem tem evento
 * futuro vem primeiro (mais cedo antes), depois quem só tem eventos passados
 * (o mais recente antes), por fim quem não tem evento.
 */
export function compareByNextEvent(
    a: WithEvents,
    b: WithEvents,
    today: string,
): number {
    const rank = (c: WithEvents): [number, string] => {
        const date = nextEvent(c, today)?.date;

        if (date === undefined) {
            return [2, ''];
        }

        return date >= today ? [0, date] : [1, date];
    };

    const [rankA, dateA] = rank(a);
    const [rankB, dateB] = rank(b);

    if (rankA !== rankB) {
        return rankA - rankB;
    }

    // Futuros: mais cedo primeiro. Só passados: mais recente primeiro.
    return rankA === 1
        ? dateB.localeCompare(dateA)
        : dateA.localeCompare(dateB);
}

/** Rótulo curto por tipo — cabe no cartão da maleta. */
const SHORT_EVENT_LABEL: Record<EventTypeValue, string> = {
    registration_start: 'insc. abre',
    registration_end: 'insc. até',
    fee_deadline: 'taxa até',
    exam: 'prova',
    result: 'resultado',
    other: 'prazo',
};

/** Prazos a até 7 dias ganham destaque no cartão. */
export const URGENT_DAYS = 7;

export type Deadline = {
    label: string;
    /** `dd/mm` */
    date: string;
    days: number;
    /** `hoje`, `amanhã` ou `Nd` */
    countdown: string;
    urgent: boolean;
};

function daysBetween(from: string, to: string): number {
    const toUtc = (iso: string) => {
        const [y, m, d] = iso.split('-').map(Number);

        return Date.UTC(y, m - 1, d);
    };

    return Math.round((toUtc(to) - toUtc(from)) / 86_400_000);
}

/** Situações em que os prazos de inscrição já não são o meu próximo passo. */
const ENROLLED: ReadonlyArray<ContestDto['enrollment_status']> = [
    'enrolled',
    'fee_paid',
    'venue_released',
    'exam_taken',
];
const FEE_DONE: ReadonlyArray<ContestDto['enrollment_status']> = [
    'fee_paid',
    'venue_released',
    'exam_taken',
];

/**
 * Próximo prazo de hoje em diante (eventos já vêm ordenados por data do
 * presenter); `null` se não há nenhum futuro. Já inscrito, pula os prazos
 * de inscrição; com a taxa paga, pula também o da taxa. `result`/`other`
 * usam o rótulo do evento quando existe ("gabarito preliminar").
 */
export function upcomingDeadline(
    contest: WithEvents & Partial<Pick<ContestDto, 'enrollment_status'>>,
    today: string,
): Deadline | null {
    const status = contest.enrollment_status ?? 'not_enrolled';
    const skip = (type: EventTypeValue) =>
        (ENROLLED.includes(status) &&
            (type === 'registration_start' || type === 'registration_end')) ||
        (FEE_DONE.includes(status) && type === 'fee_deadline');
    const event = contest.events.find((e) => e.date >= today && !skip(e.type));

    if (!event) {
        return null;
    }

    const days = daysBetween(today, event.date);
    const [, month, day] = event.date.split('-');
    const useOwnLabel =
        (event.type === 'other' || event.type === 'result') &&
        event.label !== null;

    return {
        label: useOwnLabel
            ? (event.label as string)
            : SHORT_EVENT_LABEL[event.type],
        date: `${day}/${month}`,
        days,
        countdown: days === 0 ? 'hoje' : days === 1 ? 'amanhã' : `${days}d`,
        urgent: days <= URGENT_DAYS,
    };
}

export function money(cents: number | null): string | null {
    if (cents === null) {
        return null;
    }

    return (cents / 100).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });
}

export function dateLabel(date: string): string {
    const [year, month, day] = date.split('-');

    return `${day}/${month}/${year}`;
}
