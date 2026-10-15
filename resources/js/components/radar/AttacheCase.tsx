import { useMemo, useState } from 'react';

import { CaseSlot } from '@/components/radar/CaseSlot';
import { ItemDescription } from '@/components/radar/ItemDescription';
import { TierMark } from '@/components/radar/TierMark';
import { csrfFetch } from '@/lib/csrf-fetch';
import { notifyError } from '@/lib/notify';
import { CONTEST_TIERS } from '@/types/radar';
import type { ContestDto, ContestTierValue } from '@/types/radar';

const SLOTS = 12;
const VIEW_STORAGE_KEY = 'radar.case.view';

type CaseView = 'grid' | 'tiers';

/** Preferência só deste navegador — falha silenciosa (aba privada etc.). */
function readView(): CaseView {
    try {
        return window.localStorage.getItem(VIEW_STORAGE_KEY) === 'tiers'
            ? 'tiers'
            : 'grid';
    } catch {
        return 'grid';
    }
}

function writeView(view: CaseView) {
    try {
        window.localStorage.setItem(VIEW_STORAGE_KEY, view);
    } catch {
        // sem storage: a escolha vale só até recarregar
    }
}

type AttacheCaseProps = {
    contests: ContestDto[];
    onOpen: (contest: ContestDto) => void;
    onContestChange: (contest: ContestDto) => void;
};

/**
 * Maleta (coluna esquerda) — docs/03-ui-inventario.md → "Maleta". Grade 4×3,
 * navegação por teclado (setas/Enter/P) e arrastar-e-soltar (HTML5 nativo)
 * pra reordenar via `PATCH /pins`, otimista com rollback em erro.
 */
export function AttacheCase({
    contests,
    onOpen,
    onContestChange,
}: AttacheCaseProps) {
    const [selected, setSelected] = useState<number | null>(null);
    const [view, setView] = useState<CaseView>(readView);
    // Overrida a ordem derivada da prop enquanto um drag está em voo (mesmo
    // motivo do bug de useState(propList) da T4: nunca copiar a prop pra
    // state, só sobrepor otimisticamente por cima dela).
    const [pendingOrder, setPendingOrder] = useState<Array<
        number | null
    > | null>(null);
    const baseOrder = useMemo(() => buildOrder(contests), [contests]);
    const order = pendingOrder ?? baseOrder;

    const byId = new Map(contests.map((c) => [c.id, c]));
    const filled = order.filter((id) => id !== null).length;
    const selectedContest =
        selected !== null && order[selected] !== null
            ? (byId.get(order[selected] as number) ?? null)
            : null;

    const groups = buildTierGroups(order, byId);
    const counts = new Map(groups.map((g) => [g.tier, g.slots.length]));
    const top = (counts.get('elite') ?? 0) + (counts.get('alto') ?? 0);
    const base =
        (counts.get('intermediario') ?? 0) + (counts.get('entrada') ?? 0);
    // Ordem de navegação por teclado: na grade são os 12 slots; agrupado, só
    // os ocupados, na ordem em que aparecem na tela.
    const navOrder =
        view === 'grid'
            ? order.map((_, slot) => slot)
            : groups.flatMap((g) => g.slots);

    function toggleView() {
        const next: CaseView = view === 'grid' ? 'tiers' : 'grid';
        setView(next);
        writeView(next);
    }

    async function unpinSelected() {
        if (selectedContest === null) {
            return;
        }

        const response = await csrfFetch(
            `/contests/${selectedContest.id}/pin`,
            { method: 'DELETE' },
        );

        if (!response.ok) {
            notifyError('Não deu pra desafixar. Tenta de novo.');

            return;
        }

        const data: { contest: ContestDto } = await response.json();
        onContestChange(data.contest);
    }

    async function moveTo(contestId: number, slot: number) {
        const next = [...order];
        const from = next.indexOf(contestId);

        if (from !== -1) {
            next[from] = null;
        }

        next[slot] = contestId;
        setPendingOrder(next);

        const response = await csrfFetch('/pins', {
            method: 'PATCH',
            body: JSON.stringify({ order: next }),
        });

        if (!response.ok) {
            setPendingOrder(null);
            notifyError('Não deu pra reordenar a maleta. Tenta de novo.');

            return;
        }

        const data: { contests: ContestDto[] } = await response.json();
        data.contests.forEach(onContestChange);
        setPendingOrder(null);
    }

    function handleKeyDown(e: React.KeyboardEvent) {
        if (selected === null) {
            return;
        }

        const arrows: Record<string, number> = {
            ArrowRight: 1,
            ArrowLeft: -1,
            ArrowDown: 4,
            ArrowUp: -4,
        };

        if (e.key in arrows) {
            e.preventDefault();
            const position = Math.max(0, navOrder.indexOf(selected));
            const next = Math.min(
                navOrder.length - 1,
                Math.max(0, position + arrows[e.key]),
            );
            setSelected(navOrder[next] ?? selected);
        } else if (e.key === 'Enter' && selectedContest) {
            onOpen(selectedContest);
        } else if ((e.key === 'p' || e.key === 'P') && selectedContest) {
            unpinSelected();
        }
    }

    return (
        <section
            className="surface-night flex h-full min-h-0 flex-col gap-3 overflow-hidden rounded-lg border border-ink p-4 outline-none"
            tabIndex={0}
            onKeyDown={handleKeyDown}
        >
            <div className="flex shrink-0 items-center justify-between gap-2">
                <p className="t-label text-night-text">
                    maleta · {filled}/{SLOTS}
                </p>
                <button
                    type="button"
                    onClick={toggleView}
                    aria-pressed={view === 'tiers'}
                    className="t-label rounded-sm border-2 border-slot-border px-2 py-0.5 text-night-text hover:bg-slot-hover"
                    title={
                        view === 'grid'
                            ? 'agrupar por nível'
                            : 'voltar para a grade'
                    }
                >
                    {view === 'grid' ? 'por nível' : 'grade'}
                </button>
            </div>

            {filled > 0 && (
                <div className="flex shrink-0 flex-col gap-1">
                    <div
                        className="flex flex-wrap gap-x-3 gap-y-1"
                        aria-label="equilíbrio da maleta por nível"
                    >
                        {CONTEST_TIERS.map((tier) => (
                            <span
                                key={tier.value}
                                className="t-label inline-flex items-center gap-1 text-night-text"
                            >
                                <TierMark
                                    tier={tier.value}
                                    variant="dot"
                                    on="night"
                                />
                                {tier.short} {counts.get(tier.value) ?? 0}
                            </span>
                        ))}
                    </div>
                    {top > base && (
                        <p className="rm-serif text-xs text-night-text-mid italic">
                            maleta pesada no topo — {top} de elite/alto contra{' '}
                            {base} de base.
                        </p>
                    )}
                </div>
            )}

            {view === 'grid' ? (
                <div className="grid min-h-0 flex-1 grid-cols-4 content-start gap-1.5 overflow-y-auto">
                    {order.map((contestId, slot) => (
                        <CaseSlot
                            key={slot}
                            slot={slot}
                            contest={
                                contestId !== null
                                    ? (byId.get(contestId) ?? null)
                                    : null
                            }
                            selected={selected === slot}
                            onSelect={setSelected}
                            onOpen={onOpen}
                            onDropContest={moveTo}
                        />
                    ))}
                </div>
            ) : (
                <div className="flex min-h-0 flex-col gap-3 overflow-y-auto">
                    {groups.length === 0 && (
                        <p className="rm-serif text-sm text-night-text-mid italic">
                            Maleta vazia.
                        </p>
                    )}
                    {groups.map((group) => (
                        <div
                            key={group.tier ?? 'none'}
                            className="flex flex-col gap-1.5"
                        >
                            <p className="t-label inline-flex items-center gap-1.5 text-night-text">
                                <TierMark
                                    tier={group.tier}
                                    variant="dot"
                                    on="night"
                                />
                                {group.label} · {group.slots.length}
                            </p>
                            <div className="grid grid-cols-4 gap-1.5">
                                {group.slots.map((slot) => (
                                    <CaseSlot
                                        key={slot}
                                        slot={slot}
                                        contest={
                                            byId.get(order[slot] as number) ??
                                            null
                                        }
                                        selected={selected === slot}
                                        onSelect={setSelected}
                                        onOpen={onOpen}
                                        onDropContest={moveTo}
                                        reorderable={false}
                                    />
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <ItemDescription
                contest={selectedContest}
                onOpen={onOpen}
                onContestChange={onContestChange}
            />
        </section>
    );
}

function buildOrder(contests: ContestDto[]): Array<number | null> {
    const order: Array<number | null> = Array.from(
        { length: SLOTS },
        () => null,
    );

    for (const contest of contests) {
        if (
            contest.pinned_slot !== null &&
            contest.pinned_slot >= 0 &&
            contest.pinned_slot < SLOTS
        ) {
            order[contest.pinned_slot] = contest.id;
        }
    }

    return order;
}

type TierGroup = {
    tier: ContestTierValue | null;
    label: string;
    slots: number[];
};

/**
 * Agrupa os slots ocupados por nível, do topo da escada para a base; quem não
 * tem nível vai num grupo "sem nível" no fim. Dentro do grupo, a ordem é a do
 * slot — a ordem manual da grade continua valendo.
 */
function buildTierGroups(
    order: Array<number | null>,
    byId: Map<number, ContestDto>,
): TierGroup[] {
    const groups: TierGroup[] = [
        ...CONTEST_TIERS.map((t) => ({
            tier: t.value,
            label: t.label,
            slots: [] as number[],
        })),
        {
            tier: null,
            label: 'sem nível',
            slots: [],
        },
    ];

    order.forEach((contestId, slot) => {
        if (contestId === null) {
            return;
        }

        const tier = byId.get(contestId)?.tier ?? null;
        groups.find((g) => g.tier === tier)?.slots.push(slot);
    });

    return groups.filter((g) => g.slots.length > 0);
}
