import { NewTag, PhaseTag } from '@/components/radar/PhaseTag';
import { TierMark } from '@/components/radar/TierMark';
import { csrfFetch } from '@/lib/csrf-fetch';
import { notifyError } from '@/lib/notify';
import { dateLabel, money, nextEventLabel } from '@/lib/radar';
import type { ContestDto } from '@/types/radar';

type ItemDescriptionProps = {
    contest: ContestDto | null;
    onOpen: (contest: ContestDto) => void;
    onContestChange: (contest: ContestDto) => void;
};

function Fact({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="min-w-0">
            <dt className="t-label text-night-text-mid">{label}</dt>
            <dd className="t-body truncate font-semibold text-night-text">
                {value ?? '—'}
            </dd>
        </div>
    );
}

const LINK =
    't-label text-night-text underline underline-offset-2 hover:text-night-text-mid';
const BUTTON =
    't-label rounded-sm border-2 border-slot-border px-2 py-1 text-night-text hover:bg-slot-hover';

/** Painel fixo embaixo da grade da maleta — docs/03-ui-inventario.md → "descrição do item". */
export function ItemDescription({
    contest,
    onOpen,
    onContestChange,
}: ItemDescriptionProps) {
    if (contest === null) {
        return (
            <div className="rm-rule-double shrink-0 border-t-[3px] border-double border-slot-border pt-3">
                <p className="rm-serif text-sm text-night-text-mid italic">
                    Escolhe um item.
                </p>
            </div>
        );
    }

    async function unpin() {
        if (contest === null) {
            return;
        }

        const response = await csrfFetch(`/contests/${contest.id}/pin`, {
            method: 'DELETE',
        });

        if (!response.ok) {
            const error: { message?: string } = await response
                .json()
                .catch(() => ({}));
            notifyError(
                error.message ?? 'Não deu pra desafixar. Tenta de novo.',
            );

            return;
        }

        const data: { contest: ContestDto } = await response.json();
        onContestChange(data.contest);
    }

    const salary = money(contest.salary_max_cents ?? contest.salary_min_cents);

    return (
        <div className="rm-rule-double shrink-0 border-t-[3px] border-double border-slot-border pt-3">
            <div className="flex flex-wrap items-center justify-between gap-x-2 gap-y-1">
                <p
                    className="t-title min-w-0 truncate font-bold text-night-text"
                    title={contest.title}
                >
                    {contest.organ_acronym ?? contest.organ}
                </p>
                <div className="flex items-center gap-1.5">
                    {contest.is_new && <NewTag />}
                    <TierMark tier={contest.tier} variant="chip" on="night" />
                </div>
            </div>

            <div className="mt-1.5">
                <PhaseTag contest={contest} className="t-body px-2 py-1" />
            </div>

            <dl className="mt-2 grid grid-cols-3 gap-x-3 gap-y-1.5">
                <Fact
                    label="vagas TI"
                    value={
                        contest.it_vacancies !== null
                            ? String(contest.it_vacancies)
                            : null
                    }
                />
                <Fact label="salário" value={salary} />
                <Fact label="taxa" value={money(contest.fee_cents)} />
                <Fact label="banca" value={contest.board} />
                <Fact
                    label="inscrição até"
                    value={
                        contest.registration_end
                            ? dateLabel(contest.registration_end)
                            : null
                    }
                />
                <Fact
                    label="prova"
                    value={
                        contest.exam_date ? dateLabel(contest.exam_date) : null
                    }
                />
            </dl>

            <p className="rm-serif mt-1.5 text-sm text-night-text-mid italic">
                próximo: {nextEventLabel(contest) ?? 'sem data'}
            </p>

            <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5">
                {contest.edital_url && (
                    <a
                        href={contest.edital_url}
                        target="_blank"
                        rel="noreferrer"
                        className={LINK}
                    >
                        edital ↗
                    </a>
                )}
                {contest.registration_url && (
                    <a
                        href={contest.registration_url}
                        target="_blank"
                        rel="noreferrer"
                        className={LINK}
                    >
                        inscrição ↗
                    </a>
                )}
            </div>

            <div className="mt-2 flex flex-wrap gap-2">
                <button
                    type="button"
                    onClick={() => onOpen(contest)}
                    className={BUTTON}
                >
                    abrir dossiê ↵
                </button>
                <button type="button" onClick={unpin} className={BUTTON}>
                    desafixar P
                </button>
            </div>
        </div>
    );
}
