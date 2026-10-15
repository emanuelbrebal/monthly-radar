import type { BadgeKind, ContestDto } from '@/types/radar';

/** Próximo evento (ou o último, se todos já passaram) no formato "prova · 13/10". */
export function nextEventLabel(contest: ContestDto): string | null {
    const today = new Date().toISOString().slice(0, 10);
    const upcoming =
        contest.events.find((e) => e.date >= today) ?? contest.events.at(-1);

    if (!upcoming) {
        return null;
    }

    const [, month, day] = upcoming.date.split('-');

    return `${upcoming.label ?? upcoming.type_label} · ${day}/${month}`;
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

/** Forma junto da cor: o tom nunca é transmitido só por cor. */
export const TONE_ICON: Record<BadgeKind, string> = {
    pos: '✓',
    neu: '◐',
    neg: '■',
    info: '○',
};
