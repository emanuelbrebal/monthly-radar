import { Pin } from 'lucide-react';
import { useState } from 'react';

import { NewTag, PhaseTag } from '@/components/radar/PhaseTag';
import { TierMark } from '@/components/radar/TierMark';
import { csrfFetch } from '@/lib/csrf-fetch';
import { notifyError } from '@/lib/notify';
import type { ContestDto } from '@/types/radar';

type ItemRowProps = {
    contest: ContestDto;
    nextEventLabel: string | null;
    onPinChange: (contest: ContestDto) => void;
    onSelect?: (contest: ContestDto) => void;
};

/** Linha compacta da caixa de itens — docs/03-ui-inventario.md → "Caixa de itens". */
export function ItemRow({
    contest,
    nextEventLabel,
    onPinChange,
    onSelect,
}: ItemRowProps) {
    const [pinning, setPinning] = useState(false);
    const pinned = contest.pinned_slot !== null;

    async function togglePin() {
        setPinning(true);

        const response = await csrfFetch(`/contests/${contest.id}/pin`, {
            method: pinned ? 'DELETE' : 'POST',
        });
        setPinning(false);

        if (!response.ok) {
            const error: { message?: string } = await response
                .json()
                .catch(() => ({}));
            notifyError(error.message ?? 'Não deu pra salvar. Tenta de novo.');

            return;
        }

        const data: { contest: ContestDto } = await response.json();
        onPinChange(data.contest);
    }

    return (
        <div className="flex items-center gap-3 border-b border-line py-1.5">
            <button
                type="button"
                className="t-title min-h-6 flex-1 truncate text-left text-ink"
                onClick={() => onSelect?.(contest)}
            >
                {contest.organ_acronym ?? contest.organ}
            </button>

            <TierMark tier={contest.tier} variant="chip" />

            <span className="t-body w-32 shrink-0 truncate text-muted-fg">
                {nextEventLabel ?? '—'}
            </span>

            {contest.is_new && <NewTag />}

            <PhaseTag contest={contest} />

            {contest.hides_in_hours !== null &&
                contest.hides_in_hours <= 24 && (
                    <span className="t-label shrink-0 rounded-sm border border-red-text px-1.5 py-0.5 whitespace-nowrap text-red-text">
                        some {contest.hides_in_hours}h
                    </span>
                )}

            <button
                type="button"
                onClick={togglePin}
                disabled={pinning}
                aria-pressed={pinned}
                aria-label={
                    pinned
                        ? `desafixar ${contest.organ_acronym ?? contest.organ} da maleta`
                        : `fixar ${contest.organ_acronym ?? contest.organ} na maleta`
                }
                className="inline-flex size-6 shrink-0 items-center justify-center rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:text-muted-fg"
                title={pinned ? 'desafixar' : 'fixar na maleta'}
            >
                <Pin
                    size={16}
                    strokeWidth={2}
                    aria-hidden="true"
                    className={
                        pinned ? 'fill-red-text text-red-text' : 'text-muted-fg'
                    }
                />
            </button>
        </div>
    );
}
