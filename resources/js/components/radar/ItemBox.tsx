import { useMemo, useState } from 'react';

import { ItemRow } from '@/components/radar/ItemRow';
import { csrfFetch } from '@/lib/csrf-fetch';
import { notifyError } from '@/lib/notify';
import type { ContestDto, EditalStatusValue } from '@/types/radar';

type ItemBoxProps = {
    contests: ContestDto[];
    onSelect?: (contest: ContestDto) => void;
    onPinChange: (contest: ContestDto) => void;
    onRestored: () => void;
};

const STATUS_OPTIONS: Array<{ value: EditalStatusValue; label: string }> = [
    { value: 'expected', label: 'previsto' },
    { value: 'authorized', label: 'autorizado' },
    { value: 'in_preparation', label: 'edital em produção' },
    { value: 'published', label: 'edital publicado' },
    { value: 'registration_open', label: 'inscrições abertas' },
    { value: 'registration_closed', label: 'inscrições encerradas' },
    { value: 'exam_done', label: 'prova aplicada' },
    { value: 'finished', label: 'encerrado' },
    { value: 'suspended', label: 'suspenso' },
];

function nextEventLabel(contest: ContestDto): string | null {
    const upcoming = contest.events.find((e) => e.date >= new Date().toISOString().slice(0, 10)) ?? contest.events.at(-1);

    if (!upcoming) {
        return null;
    }

    const [, month, day] = upcoming.date.split('-');

    return `${upcoming.type_label} · ${day}/${month}`;
}

/** docs/03-ui-inventario.md → "Caixa de itens". */
export function ItemBox({ contests, onSelect, onPinChange, onRestored }: ItemBoxProps) {
    const [statusFilter, setStatusFilter] = useState<Set<EditalStatusValue>>(new Set());
    const [onlyEnrolled, setOnlyEnrolled] = useState(false);
    const [search, setSearch] = useState('');
    const [showDismissed, setShowDismissed] = useState(false);
    const [dismissed, setDismissed] = useState<ContestDto[]>([]);
    const [loadingDismissed, setLoadingDismissed] = useState(false);
    const [dismissedError, setDismissedError] = useState(false);

    function loadDismissed() {
        setLoadingDismissed(true);
        setDismissedError(false);
        csrfFetch('/contests/dismissed', { method: 'GET' })
            .then((r) => {
                if (!r.ok) {
                    throw new Error('failed');
                }

                return r.json();
            })
            .then((data: { contests: ContestDto[] }) => setDismissed(data.contests))
            .catch(() => setDismissedError(true))
            .finally(() => setLoadingDismissed(false));
    }

    function toggleDismissed() {
        const next = !showDismissed;
        setShowDismissed(next);

        if (next) {
            loadDismissed();
        }
    }

    async function restore(contest: ContestDto) {
        const response = await csrfFetch(`/contests/${contest.id}/restore`, { method: 'POST' });

        if (!response.ok) {
            notifyError('Não deu pra trazer de volta. Tenta de novo.');

            return;
        }

        setDismissed((prev) => prev.filter((c) => c.id !== contest.id));
        onRestored();
    }

    function toggleStatus(value: EditalStatusValue) {
        setStatusFilter((prev) => {
            const next = new Set(prev);

            if (next.has(value)) {
                next.delete(value);
            } else {
                next.add(value);
            }

            return next;
        });
    }

    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();

        return contests
            .filter((c) => statusFilter.size === 0 || statusFilter.has(c.effective_status))
            .filter((c) => !onlyEnrolled || c.enrollment_status !== 'not_enrolled')
            .filter((c) => term === '' || c.organ.toLowerCase().includes(term) || (c.organ_acronym ?? '').toLowerCase().includes(term))
            .sort((a, b) => {
                const dateA = a.events[0]?.date ?? '9999-99-99';
                const dateB = b.events[0]?.date ?? '9999-99-99';

                return dateA.localeCompare(dateB);
            });
    }, [contests, statusFilter, onlyEnrolled, search]);

    return (
        <div className="flex h-full min-h-0 flex-col rounded-lg border border-ink bg-card">
            <div className="shrink-0 rounded-t-lg border-b border-ink px-3 py-1.5">
                <span className="rm-eyebrow">¶ caixa de itens · {showDismissed ? dismissed.length : filtered.length}</span>
            </div>

            <div className="flex shrink-0 flex-wrap items-center gap-2 border-b border-ink/20 px-3 py-2">
                {STATUS_OPTIONS.map((opt) => (
                    <button
                        key={opt.value}
                        type="button"
                        onClick={() => toggleStatus(opt.value)}
                        disabled={showDismissed}
                        className={`rm-mono rounded-sm border px-1.5 py-0.5 text-[10px] uppercase disabled:opacity-30 ${
                            statusFilter.has(opt.value) ? 'border-ink bg-card' : 'border-ink/20 text-ink-mid'
                        }`}
                    >
                        {opt.label}
                    </button>
                ))}

                <label className="rm-mono ml-2 flex items-center gap-1 text-[10px] uppercase">
                    <input type="checkbox" checked={onlyEnrolled} onChange={(e) => setOnlyEnrolled(e.target.checked)} disabled={showDismissed} />
                    só inscritos
                </label>

                <input
                    type="text"
                    placeholder="busca /"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    disabled={showDismissed}
                    className="rm-mono w-32 rounded-sm border border-ink/30 px-1.5 py-0.5 text-[11px] disabled:opacity-30"
                />

                <button
                    type="button"
                    onClick={toggleDismissed}
                    className={`rm-mono ml-auto rounded-sm border px-1.5 py-0.5 text-[10px] uppercase ${
                        showDismissed ? 'border-ink bg-card' : 'border-ink/20 text-ink-mid'
                    }`}
                >
                    descartados ({dismissed.length})
                </button>
            </div>

            <div className="min-h-0 flex-1 overflow-y-auto px-3">
                {showDismissed ? (
                    loadingDismissed ? (
                        <p className="rm-serif py-6 text-center text-ink-mid italic">Carregando…</p>
                    ) : dismissedError ? (
                        <div className="py-6 text-center">
                            <p className="rm-serif text-ink-mid italic">Sinal perdido. Verifica as fontes.</p>
                            <button type="button" onClick={loadDismissed} className="rm-mono mt-2 text-[11px] underline">
                                tentar de novo
                            </button>
                        </div>
                    ) : dismissed.length === 0 ? (
                        <p className="rm-serif py-6 text-center text-ink-mid italic">Nada descartado.</p>
                    ) : (
                        dismissed.map((contest) => (
                            <div key={contest.id} className="flex items-center gap-3 border-b border-ink/10 py-1.5 text-sm opacity-50">
                                <button
                                    type="button"
                                    className="rm-display flex-1 truncate text-left text-base"
                                    onClick={() => onSelect?.(contest)}
                                >
                                    {contest.organ_acronym ?? contest.organ}
                                </button>
                                <span className="rm-mono truncate text-[11px] italic">{contest.dismissed_reason ?? '—'}</span>
                                <button
                                    type="button"
                                    onClick={() => restore(contest)}
                                    className="rm-mono shrink-0 rounded-sm border border-ink/40 px-1.5 py-0.5 text-[10px] uppercase opacity-100"
                                >
                                    trazer de volta
                                </button>
                            </div>
                        ))
                    )
                ) : filtered.length === 0 ? (
                    <p className="rm-serif py-6 text-center text-ink-mid italic">Nada no radar. Escolhe uma área e varre.</p>
                ) : (
                    filtered.map((contest) => (
                        <ItemRow
                            key={contest.id}
                            contest={contest}
                            nextEventLabel={nextEventLabel(contest)}
                            onPinChange={onPinChange}
                            onSelect={onSelect}
                        />
                    ))
                )}
            </div>
        </div>
    );
}
