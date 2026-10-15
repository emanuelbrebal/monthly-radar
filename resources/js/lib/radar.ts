import type { ContestDto } from '@/types/radar';

/** Hoje em `YYYY-MM-DD` no fuso local (UTC viraria o dia às 21h no Brasil), igual às datas dos eventos. */
export function todayIso(): string {
    const now = new Date();
    const pad = (n: number) => n.toString().padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

/** Próximo evento (ou o último, se todos já passaram) no formato "prova · 13/10". */
export function nextEventLabel(contest: ContestDto): string | null {
    const today = todayIso();
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
