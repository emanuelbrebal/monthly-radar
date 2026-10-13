import { useMemo, useState } from 'react';

import { CaseSlot } from '@/components/radar/CaseSlot';
import { ItemDescription } from '@/components/radar/ItemDescription';
import { csrfFetch } from '@/lib/csrf-fetch';
import { notifyError } from '@/lib/notify';
import type { ContestDto } from '@/types/radar';

const SLOTS = 12;

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
export function AttacheCase({ contests, onOpen, onContestChange }: AttacheCaseProps) {
    const [selected, setSelected] = useState<number | null>(null);
    // Overrida a ordem derivada da prop enquanto um drag está em voo (mesmo
    // motivo do bug de useState(propList) da T4: nunca copiar a prop pra
    // state, só sobrepor otimisticamente por cima dela).
    const [pendingOrder, setPendingOrder] = useState<Array<number | null> | null>(null);
    const baseOrder = useMemo(() => buildOrder(contests), [contests]);
    const order = pendingOrder ?? baseOrder;

    const byId = new Map(contests.map((c) => [c.id, c]));
    const filled = order.filter((id) => id !== null).length;
    const selectedContest = selected !== null && order[selected] !== null ? (byId.get(order[selected] as number) ?? null) : null;

    async function unpinSelected() {
        if (selectedContest === null) {
            return;
        }

        const response = await csrfFetch(`/contests/${selectedContest.id}/pin`, { method: 'DELETE' });

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

        const response = await csrfFetch('/pins', { method: 'PATCH', body: JSON.stringify({ order: next }) });

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

        const arrows: Record<string, number> = { ArrowRight: 1, ArrowLeft: -1, ArrowDown: 4, ArrowUp: -4 };

        if (e.key in arrows) {
            e.preventDefault();
            const next = Math.min(SLOTS - 1, Math.max(0, selected + arrows[e.key]));
            setSelected(next);
        } else if (e.key === 'Enter' && selectedContest) {
            onOpen(selectedContest);
        } else if ((e.key === 'p' || e.key === 'P') && selectedContest) {
            unpinSelected();
        }
    }

    return (
        <section
            className="flex h-full min-h-0 flex-col gap-3 rounded-lg border border-ink bg-case p-4 text-night-text outline-none"
            tabIndex={0}
            onKeyDown={handleKeyDown}
        >
            <p className="rm-eyebrow text-night-text-mid">
                ¶ maleta · {filled}/{SLOTS}
            </p>

            <div className="grid grid-cols-4 gap-1.5">
                {order.map((contestId, slot) => (
                    <CaseSlot
                        key={slot}
                        slot={slot}
                        contest={contestId !== null ? (byId.get(contestId) ?? null) : null}
                        selected={selected === slot}
                        onSelect={setSelected}
                        onOpen={onOpen}
                        onDropContest={moveTo}
                    />
                ))}
            </div>

            <ItemDescription contest={selectedContest} onOpen={onOpen} onContestChange={onContestChange} />
        </section>
    );
}

function buildOrder(contests: ContestDto[]): Array<number | null> {
    const order: Array<number | null> = Array.from({ length: SLOTS }, () => null);

    for (const contest of contests) {
        if (contest.pinned_slot !== null && contest.pinned_slot >= 0 && contest.pinned_slot < SLOTS) {
            order[contest.pinned_slot] = contest.id;
        }
    }

    return order;
}
