import { NewTag, PhaseTag } from '@/components/radar/PhaseTag';
import { Stamp } from '@/components/radar/Stamp';
import { TierMark, tierMeta } from '@/components/radar/TierMark';
import { todayIso, upcomingDeadline } from '@/lib/radar';
import type { ContestDto } from '@/types/radar';

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

/** Um slot da maleta — docs/03-ui-inventario.md → "Maleta (coluna esquerda)". */
export function CaseSlot({
    slot,
    contest,
    selected,
    onSelect,
    onOpen,
    onDropContest,
    reorderable = true,
}: CaseSlotProps) {
    const tier = contest ? tierMeta(contest.tier) : null;
    const deadline = contest ? upcomingDeadline(contest, todayIso()) : null;
    const deadlineText = deadline
        ? `${deadline.label} ${deadline.date}, ${deadline.countdown === 'hoje' || deadline.countdown === 'amanhã' ? deadline.countdown : `em ${deadline.days} dias`}`
        : 'sem prazo';

    return (
        <div
            role="button"
            tabIndex={0}
            aria-label={
                contest
                    ? `${contest.organ_acronym ?? contest.organ}, ${contest.phase_label}, ${deadlineText}${contest.is_new ? ', novo' : ''}, slot ${slot + 1}${tier ? `, nível ${tier.label}` : ''}`
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
                    e.dataTransfer.setData(
                        'text/contest-id',
                        String(contest.id),
                    );
                }
            }}
            className={`relative flex aspect-square cursor-pointer flex-col justify-between bg-case-lining p-1 ${
                selected ? 'ring-[3px] ring-highlight' : 'hover:bg-slot-hover'
            } ${contest === null ? 'border border-dashed border-slot-border' : 'border border-slot-border'}`}
        >
            {contest === null ? (
                <span className="flex h-full items-start text-label text-night-text-mid">
                    {slot + 1}
                </span>
            ) : (
                <div className="surface-card relative flex h-full min-w-0 flex-col justify-between gap-1 overflow-hidden rounded-sm border border-ink p-1.5">
                    {/* Uma informação por linha, sem quebra: sigla, fase,
                        prazo, nível. O rótulo do prazo fica no painel de
                        descrição — no cartão só data e contagem (T8). */}
                    <div className="flex min-w-0 items-center gap-1">
                        <TierMark tier={contest.tier} variant="dot" />
                        <p
                            className="t-body min-w-0 truncate leading-tight font-bold text-ink"
                            title={contest.title}
                        >
                            {contest.organ_acronym ?? contest.organ}
                        </p>
                    </div>
                    <PhaseTag
                        contest={contest}
                        className="max-w-full self-start px-1 py-0"
                    />
                    <p
                        className={`t-label flex min-w-0 justify-between gap-1 font-mono leading-tight ${
                            deadline === null
                                ? 'font-normal text-muted-fg'
                                : deadline.urgent
                                  ? 'text-red-text'
                                  : 'font-normal text-ink'
                        }`}
                        title={deadlineText}
                    >
                        {deadline === null ? (
                            <span>sem prazo</span>
                        ) : (
                            <>
                                <span>{deadline.date}</span>
                                <span>{deadline.countdown}</span>
                            </>
                        )}
                    </p>
                    {contest.is_new && (
                        <NewTag className="self-start px-1 py-0" />
                    )}
                    {contest.hides_in_hours !== null &&
                        contest.hides_in_hours <= 24 && (
                            <Stamp
                                rotate={-8}
                                className="absolute -right-1 -bottom-1 scale-[0.6]"
                            >
                                some {contest.hides_in_hours}h
                            </Stamp>
                        )}
                </div>
            )}
        </div>
    );
}
