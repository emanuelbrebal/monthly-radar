import type { ContestDto } from '@/types/radar';

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
