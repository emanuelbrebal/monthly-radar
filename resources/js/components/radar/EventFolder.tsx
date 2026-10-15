import { TierMark } from '@/components/radar/TierMark';
import type { ContestDto, ContestEventDto } from '@/types/radar';
import { EVENT_TYPE_BADGE } from '@/types/radar';

const TIER_BORDER = {
    elite: 'border-2 border-tier-elite-line',
    alto: 'border-2 border-tier-alto-line',
} as const;

type EventFolderProps = {
    contest: ContestDto;
    event: ContestEventDto;
    onSelect?: (contest: ContestDto) => void;
};

/** Card curto do calendário: "SIGLA · TÍTULO DO EVENTO". Clique abre o dossiê — docs/03-ui-inventario.md → "Calendário". */
export function EventFolder({ contest, event, onSelect }: EventFolderProps) {
    const kind = EVENT_TYPE_BADGE[event.type];
    const acronym = contest.organ_acronym ?? contest.organ;
    const title = event.label ?? event.type_label;
    const highTier = contest.tier === 'elite' || contest.tier === 'alto';

    return (
        <button
            type="button"
            onClick={() => onSelect?.(contest)}
            className={`rm-badge ${kind} h-6 w-full min-w-0 gap-1 rounded-sm px-1.5 py-0 text-left uppercase ${highTier ? TIER_BORDER[contest.tier as 'elite' | 'alto'] : ''}`}
            title={`${acronym} · ${title}`}
        >
            {highTier && <TierMark tier={contest.tier} variant="dot" />}
            {contest.pinned_slot !== null && (
                <span
                    className="h-2 w-2 shrink-0 rounded-full bg-red-text"
                    aria-hidden="true"
                />
            )}
            {contest.pinned_slot !== null && (
                <span className="sr-only">na maleta: </span>
            )}
            <span className="min-w-0 truncate">
                {acronym} · {title}
            </span>
        </button>
    );
}
