import { Stamp } from '@/components/radar/Stamp';
import { TierMark, tierMeta } from '@/components/radar/TierMark';
import type { ContestDto } from '@/types/radar';

const DOT_KIND_COLOR: Record<string, string> = {
    pos: 'bg-green',
    neu: 'bg-gold',
    neg: 'bg-red',
    info: 'bg-blue',
};

type CaseSlotProps = {
    slot: number;
    contest: ContestDto | null;
    selected: boolean;
    onSelect: (slot: number) => void;
    onOpen: (contest: ContestDto) => void;
    onDropContest: (contestId: number, slot: number) => void;
    /** `false` na vista agrupada por nível: a posição ali é derivada, não arrastável. */
    reorderable?: boolean;
};

function nextEventLabel(contest: ContestDto): string | null {
    const upcoming = contest.events.find((e) => e.date >= new Date().toISOString().slice(0, 10)) ?? contest.events.at(-1);

    if (!upcoming) {
        return null;
    }

    const [, month, day] = upcoming.date.split('-');

    return `${upcoming.type_label} · ${day}/${month}`;
}

/** Um slot da maleta — docs/03-ui-inventario.md → "Maleta (coluna esquerda)". */
export function CaseSlot({ slot, contest, selected, onSelect, onOpen, onDropContest, reorderable = true }: CaseSlotProps) {
    const tier = contest ? tierMeta(contest.tier) : null;
    const badgeKind = contest
        ? contest.effective_status === 'registration_open'
            ? 'pos'
            : ['published', 'in_preparation', 'authorized'].includes(contest.effective_status)
              ? 'neu'
              : contest.effective_status === 'expected'
                ? 'info'
                : 'neg'
        : null;

    return (
        <div
            role="button"
            tabIndex={0}
            aria-label={
                contest
                    ? `${contest.organ_acronym ?? contest.organ}, slot ${slot + 1}${tier ? `, nível ${tier.label}` : ''}`
                    : `slot ${slot + 1} vazio`
            }
            onClick={() => onSelect(slot)}
            onDoubleClick={() => contest && onOpen(contest)}
            onKeyDown={(e) => {
                if (e.key === 'Enter' && contest) {
                    onOpen(contest);
                }
            }}
            onDragOver={(e) => reorderable && e.preventDefault()}
            onDrop={(e) => {
                if (!reorderable) {
                    return;
                }

                e.preventDefault();
                const id = Number(e.dataTransfer.getData('text/contest-id'));

                if (id) {
                    onDropContest(id, slot);
                }
            }}
            draggable={reorderable && contest !== null}
            onDragStart={(e) => {
                if (contest) {
                    e.dataTransfer.setData('text/contest-id', String(contest.id));
                }
            }}
            className={`relative flex aspect-square cursor-pointer flex-col justify-between p-1 bg-case-lining ${
                selected ? 'ring-[3px] ring-highlight' : 'hover:bg-slot-hover'
            } ${contest === null ? 'border border-dashed border-slot-border' : 'border border-slot-border'}`}
        >
            {contest === null ? (
                <span className="text-label flex h-full items-start text-night-text-mid">{slot + 1}</span>
            ) : (
                <div className="surface-card relative flex h-full min-w-0 flex-col justify-between gap-0.5 overflow-hidden rounded-sm border border-ink p-1">
                    <div className="flex items-start justify-between gap-1">
                        <p className="rm-display min-w-0 truncate text-lg leading-none text-ink">{contest.organ_acronym ?? contest.organ}</p>
                        {badgeKind && (
                            <span className={`mt-0.5 h-2 w-2 shrink-0 rounded-full ${DOT_KIND_COLOR[badgeKind]}`} title={contest.effective_status_label}>
                                <span className="sr-only">{contest.effective_status_label}</span>
                            </span>
                        )}
                    </div>
                    <TierMark tier={contest.tier} variant="chip" className="self-start" />
                    <p className="text-label truncate font-normal text-muted-fg">{nextEventLabel(contest) ?? '—'}</p>
                    {contest.hides_in_hours !== null && contest.hides_in_hours <= 24 && (
                        <Stamp rotate={-8} className="absolute -bottom-1 -right-1 scale-[0.6]">
                            some {contest.hides_in_hours}h
                        </Stamp>
                    )}
                </div>
            )}
        </div>
    );
}
