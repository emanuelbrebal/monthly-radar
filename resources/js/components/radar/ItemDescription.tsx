import { TierMark } from '@/components/radar/TierMark';
import { csrfFetch } from '@/lib/csrf-fetch';
import { notifyError } from '@/lib/notify';
import type { ContestDto } from '@/types/radar';

type ItemDescriptionProps = {
    contest: ContestDto | null;
    onOpen: (contest: ContestDto) => void;
    onContestChange: (contest: ContestDto) => void;
};

function nextEventLabel(contest: ContestDto): string | null {
    const upcoming = contest.events.find((e) => e.date >= new Date().toISOString().slice(0, 10)) ?? contest.events.at(-1);

    if (!upcoming) {
        return null;
    }

    const [, month, day] = upcoming.date.split('-');

    return `${upcoming.type_label} · ${day}/${month}`;
}

/** Painel fixo embaixo da grade da maleta — docs/03-ui-inventario.md → "descrição do item". */
export function ItemDescription({ contest, onOpen, onContestChange }: ItemDescriptionProps) {
    if (contest === null) {
        return (
            <div className="rm-rule-double border-t-[3px] border-double border-night-text-mid/30 pt-3">
                <p className="rm-serif text-sm text-night-text-mid italic">Escolhe um item.</p>
            </div>
        );
    }

    async function unpin() {
        if (contest === null) {
            return;
        }

        const response = await csrfFetch(`/contests/${contest.id}/pin`, { method: 'DELETE' });

        if (!response.ok) {
            const error: { message?: string } = await response.json().catch(() => ({}));
            notifyError(error.message ?? 'Não deu pra desafixar. Tenta de novo.');

            return;
        }

        const data: { contest: ContestDto } = await response.json();
        onContestChange(data.contest);
    }

    return (
        <div className="rm-rule-double border-t-[3px] border-double border-night-text-mid/30 pt-3">
            <div className="flex items-baseline justify-between gap-2">
                <p className="rm-display text-2xl text-night-text">{contest.organ_acronym ?? contest.organ}</p>
                <TierMark tier={contest.tier} variant="label" className="text-night-text-mid" />
            </div>
            <p className="rm-serif mt-1 text-xs text-night-text-mid italic">
                {nextEventLabel(contest) ?? 'sem data'} · {contest.enrollment_status === 'not_enrolled' ? 'não inscrito' : contest.enrollment_status}
            </p>
            {contest.tier_note && <p className="rm-serif mt-1.5 text-[11px] leading-snug text-night-text-mid">{contest.tier_note}</p>}
            <div className="mt-3 flex gap-2">
                <button
                    type="button"
                    onClick={() => onOpen(contest)}
                    className="rm-mono rounded-sm border border-night-text-mid/40 px-2 py-1 text-[11px] uppercase text-night-text"
                >
                    abrir dossiê ↵
                </button>
                <button type="button" onClick={unpin} className="rm-mono rounded-sm border border-night-text-mid/40 px-2 py-1 text-[11px] uppercase text-night-text">
                    desafixar P
                </button>
            </div>
        </div>
    );
}
