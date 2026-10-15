import { useMemo, useState } from 'react';

import { ItemRow } from '@/components/radar/ItemRow';
import { StatusFilterPopover } from '@/components/radar/StatusFilterPopover';
import { Checkbox } from '@/components/ui/checkbox';
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
        <div className="surface-card flex h-full min-h-0 flex-col rounded-lg border border-ink bg-card">
            <div className="shrink-0 rounded-t-lg border-b border-line-strong px-3 py-2">
                <h2 className="t-label text-ink">caixa de itens · {showDismissed ? dismissed.length : filtered.length}</h2>
            </div>

            <div className="flex shrink-0 flex-wrap items-center gap-x-3 gap-y-2 border-b border-line px-3 py-2">
                <StatusFilterPopover options={STATUS_OPTIONS} selected={statusFilter} onChange={setStatusFilter} disabled={showDismissed} />

                <label className="t-body flex min-h-6 items-center gap-2 text-ink">
                    <Checkbox checked={onlyEnrolled} onCheckedChange={(c) => setOnlyEnrolled(c === true)} disabled={showDismissed} />
                    só inscritos
                </label>

                <label className="t-body flex items-center gap-2 text-ink">
                    busca
                    <input
                        type="text"
                        placeholder="órgão ou sigla"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        disabled={showDismissed}
                        className="t-body min-h-6 w-40 rounded-sm border border-line-strong bg-card px-2 py-1 text-ink placeholder:text-muted-fg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:text-muted-fg"
                    />
                </label>

                <button
                    type="button"
                    onClick={toggleDismissed}
                    aria-pressed={showDismissed}
                    className={`t-body ml-auto min-h-6 rounded-sm border px-2 py-1 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus ${
                        showDismissed ? 'border-ink bg-paper' : 'border-line-strong'
                    }`}
                >
                    descartados ({dismissed.length})
                </button>
            </div>

            <div className="min-h-0 flex-1 overflow-y-auto px-3">
                {showDismissed ? (
                    loadingDismissed ? (
                        <p className="t-body py-6 text-center text-muted-fg">Carregando…</p>
                    ) : dismissedError ? (
                        <div className="py-6 text-center">
                            <p className="t-body text-muted-fg">Sinal perdido. Verifica as fontes.</p>
                            <button type="button" onClick={loadDismissed} className="t-body mt-2 min-h-6 text-ink underline">
                                tentar de novo
                            </button>
                        </div>
                    ) : dismissed.length === 0 ? (
                        <p className="t-body py-6 text-center text-muted-fg">Nada descartado.</p>
                    ) : (
                        dismissed.map((contest) => (
                            <div key={contest.id} className="flex items-center gap-3 border-b border-line py-1.5">
                                <button
                                    type="button"
                                    className="t-title min-h-6 flex-1 truncate text-left text-muted-fg line-through"
                                    onClick={() => onSelect?.(contest)}
                                >
                                    {contest.organ_acronym ?? contest.organ}
                                </button>
                                <span className="t-body truncate text-muted-fg">{contest.dismissed_reason ?? '—'}</span>
                                <button
                                    type="button"
                                    onClick={() => restore(contest)}
                                    className="t-body min-h-6 shrink-0 rounded-sm border border-line-strong px-2 py-1 text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                                >
                                    trazer de volta
                                </button>
                            </div>
                        ))
                    )
                ) : filtered.length === 0 ? (
                    <p className="t-body py-6 text-center text-muted-fg">Nada no radar. Escolhe uma área e varre.</p>
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
