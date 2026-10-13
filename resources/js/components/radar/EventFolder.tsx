import type { ContestDto, ContestEventDto } from '@/types/radar';
import { EVENT_TYPE_BADGE } from '@/types/radar';

const BADGE_BG: Record<string, string> = {
    pos: '#d9e6ca',
    neu: '#eee2be',
    neg: '#edd5cb',
    info: '#d7e0ec',
};

const BADGE_BORDER: Record<string, string> = {
    pos: '#4e7943',
    neu: '#ab8a4a',
    neg: '#bb5a45',
    info: '#2e4a68',
};

type EventFolderProps = {
    contest: ContestDto;
    event: ContestEventDto;
    onSelect?: (contest: ContestDto) => void;
};

/** Chip "pasta de evento" do calendário — docs/03-ui-inventario.md → "Calendário". */
export function EventFolder({ contest, event, onSelect }: EventFolderProps) {
    const kind = EVENT_TYPE_BADGE[event.type];
    const acronym = contest.organ_acronym ?? contest.organ;

    return (
        <button
            type="button"
            onClick={() => onSelect?.(contest)}
            className="relative flex h-5 w-full items-center truncate rounded-sm px-1.5 text-left text-[9px] font-medium tracking-wide uppercase"
            style={{
                background: BADGE_BG[kind],
                border: `1px solid ${BADGE_BORDER[kind]}`,
                fontFamily: 'var(--font-mono)',
            }}
            title={`${acronym} · ${event.type_label}`}
        >
            {contest.pinned_slot !== null && <span className="mr-1 h-1.5 w-1.5 shrink-0 rounded-full bg-red" />}
            <span className="truncate">
                {acronym} · {event.type_label}
            </span>
        </button>
    );
}
