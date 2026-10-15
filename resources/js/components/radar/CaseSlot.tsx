import { Stamp } from '@/components/radar/Stamp';
import { tierMeta } from '@/components/radar/TierMark';
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
            className={`relative flex aspect-square cursor-pointer flex-col justify-between border p-1.5 outline-none ${
                selected ? 'border-2 border-slot-selected' : 'border-slot-border hover:bg-slot-hover'
            } bg-case-lining`}
        >
            {contest === null ? (
                <span className="flex h-full items-center justify-center text-night-text-mid/30">·</span>
            ) : (
                <div className="relative flex h-full flex-col justify-between rounded-sm border border-ink bg-card p-1.5 shadow-[3px_3px_0_rgba(0,0,0,0.6)]">
                    <span className="absolute top-0 left-1/2 h-2 w-2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-red" />
                    {tier && <span className={`absolute inset-y-0 left-0 w-1 ${tier.color}`} title={`nível: ${tier.label}`} />}
                    {badgeKind && <span className={`absolute top-1 right-1 h-1.5 w-1.5 rounded-full ${DOT_KIND_COLOR[badgeKind]}`} />}
                    <p className="rm-display truncate text-base leading-none">{contest.organ_acronym ?? contest.organ}</p>
                    <p className="rm-mono truncate text-[9px] text-ink-mid">{nextEventLabel(contest) ?? '—'}</p>
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
