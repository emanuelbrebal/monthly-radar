import { Pin } from 'lucide-react';
import { useState } from 'react';

import { Stamp } from '@/components/radar/Stamp';
import { Tag } from '@/components/radar/Tag';
import { TierMark } from '@/components/radar/TierMark';
import { csrfFetch } from '@/lib/csrf-fetch';
import { notifyError } from '@/lib/notify';
import { EDITAL_STATUS_BADGE } from '@/types/radar';
import type { ContestDto } from '@/types/radar';

type ItemRowProps = {
    contest: ContestDto;
    nextEventLabel: string | null;
    onPinChange: (contest: ContestDto) => void;
    onSelect?: (contest: ContestDto) => void;
};

/** Linha compacta da caixa de itens — docs/03-ui-inventario.md → "Caixa de itens". */
export function ItemRow({ contest, nextEventLabel, onPinChange, onSelect }: ItemRowProps) {
    const [pinning, setPinning] = useState(false);
    const pinned = contest.pinned_slot !== null;

    async function togglePin() {
        setPinning(true);

        const response = await csrfFetch(`/contests/${contest.id}/pin`, { method: pinned ? 'DELETE' : 'POST' });
        setPinning(false);

        if (!response.ok) {
            const error: { message?: string } = await response.json().catch(() => ({}));
            notifyError(error.message ?? 'Não deu pra salvar. Tenta de novo.');

            return;
        }

        const data: { contest: ContestDto } = await response.json();
        onPinChange(data.contest);
    }

    return (
        <div className="flex items-center gap-3 border-b border-ink/10 py-1.5 text-sm">
            <span className="flex w-2 shrink-0 justify-center">
                <TierMark tier={contest.tier} />
            </span>
            <button type="button" className="rm-display flex-1 truncate text-left text-base" onClick={() => onSelect?.(contest)}>
                {contest.organ_acronym ?? contest.organ}
            </button>

            <span className="rm-mono w-28 shrink-0 truncate text-[11px] text-ink-mid">{nextEventLabel ?? '—'}</span>

            <Tag label={contest.effective_status_label} kind={EDITAL_STATUS_BADGE[contest.effective_status]} />

            {contest.hides_in_hours !== null && contest.hides_in_hours <= 24 && <Stamp rotate={-6}>some {contest.hides_in_hours}h</Stamp>}

            <button
                type="button"
                onClick={togglePin}
                disabled={pinning}
                aria-pressed={pinned}
                className="shrink-0 disabled:opacity-50"
                title={pinned ? 'desafixar' : 'fixar na maleta'}
            >
                <Pin size={16} strokeWidth={1.5} className={pinned ? 'fill-red text-red' : 'text-ink-mid'} />
            </button>
        </div>
    );
}
