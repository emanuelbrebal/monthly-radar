import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { DossierModal } from '@/components/radar/DossierModal';
import { ErrorToast } from '@/components/radar/ErrorToast';
import { RadarMark } from '@/components/radar/RadarMark';
import { Tag } from '@/components/radar/Tag';
import { TierMark } from '@/components/radar/TierMark';
import { dateLabel } from '@/lib/radar';
import type { ContestDto } from '@/types/radar';

type ArchiveReason = 'expired' | 'dismissed';

type ArchivedContestDto = ContestDto & {
    archive_reason: ArchiveReason;
    left_at: string | null;
};

type ArchivePageProps = {
    contests: ArchivedContestDto[];
    reason: ArchiveReason | null;
    counts: { all: number; expired: number; dismissed: number };
};

const FILTERS: {
    value: ArchiveReason | null;
    label: string;
    count: keyof ArchivePageProps['counts'];
}[] = [
    { value: null, label: 'todos', count: 'all' },
    { value: 'expired', label: 'expirados', count: 'expired' },
    { value: 'dismissed', label: 'descartados', count: 'dismissed' },
];

/**
 * Arquivo (T7) — docs/03-ui-inventario.md → "Arquivo". Tudo que saiu da
 * mesa: expirados pela regra das 24h e descartados. O dossiê abre sem a
 * opção de maleta; mudar a situação pra "inscrito" ou além (ou trazer de
 * volta um descartado) recarrega a lista — o item volta pra mesa se a regra
 * deixar.
 */
export default function RadarArchive() {
    const { contests, reason, counts } = usePage<ArchivePageProps>().props;
    const [selected, setSelected] = useState<ContestDto | null>(null);

    function handleChange(updated: ContestDto) {
        setSelected(updated);
        router.reload({ only: ['contests', 'counts'] });
    }

    return (
        <>
            <Head title="Arquivo · Radar Mensal" />
            <div className="flex min-h-screen flex-col bg-paper text-ink">
                <header className="shrink-0 border-b border-line-strong bg-paper px-6 py-3">
                    <div className="flex flex-wrap items-center gap-4">
                        <RadarMark size={28} />
                        <h1 className="text-display">arquivo</h1>
                        <Link
                            href="/dashboard"
                            className="t-body ml-auto inline-flex min-h-6 items-center underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                        >
                            painel
                        </Link>
                        <Link
                            href="/"
                            className="t-body inline-flex min-h-6 items-center underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                        >
                            ← voltar pra mesa
                        </Link>
                    </div>
                </header>

                <main
                    aria-label="Concursos fora da mesa"
                    className="mx-auto w-full max-w-5xl flex-1 p-6"
                >
                    <p className="t-body mb-4 text-muted-fg">
                        Concursos que saíram da mesa: encerrados sem inscrição
                        (regra das 24h) e descartados. Marcar
                        &ldquo;inscrito&rdquo; ou além traz de volta.
                    </p>

                    <nav
                        aria-label="Filtrar por motivo"
                        className="mb-4 flex flex-wrap gap-2"
                    >
                        {FILTERS.map((filter) => {
                            const active = reason === filter.value;

                            return (
                                <Link
                                    key={filter.label}
                                    href={
                                        filter.value
                                            ? `/archive?reason=${filter.value}`
                                            : '/archive'
                                    }
                                    aria-current={active ? 'page' : undefined}
                                    preserveScroll
                                    className={`t-body min-h-6 border px-2 py-1 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus ${
                                        active
                                            ? 'border-ink font-semibold'
                                            : 'border-line-strong'
                                    }`}
                                >
                                    {filter.label} ({counts[filter.count]})
                                </Link>
                            );
                        })}
                    </nav>

                    <section
                        aria-label="Lista do arquivo"
                        className="border border-line-strong px-3"
                    >
                        {contests.length === 0 ? (
                            <p className="t-body py-6 text-center text-muted-fg">
                                Nada no arquivo.
                            </p>
                        ) : (
                            <ul>
                                {contests.map((contest) => (
                                    <li
                                        key={contest.id}
                                        className="flex flex-wrap items-center gap-3 border-b border-line py-2 last:border-b-0"
                                    >
                                        <button
                                            type="button"
                                            onClick={() => setSelected(contest)}
                                            className="t-title min-h-6 min-w-40 flex-1 truncate text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                                        >
                                            {contest.organ_acronym ??
                                                contest.organ}{' '}
                                            · {contest.year}
                                        </button>

                                        <TierMark
                                            tier={contest.tier}
                                            variant="chip"
                                        />

                                        <span className="t-body w-40 shrink-0 truncate text-muted-fg">
                                            {contest.archive_reason ===
                                            'dismissed'
                                                ? (contest.dismissed_reason ??
                                                  'sem motivo')
                                                : contest.effective_status_label}
                                        </span>

                                        <span className="t-body w-24 shrink-0 text-muted-fg tabular-nums">
                                            {contest.left_at
                                                ? dateLabel(
                                                      contest.left_at.slice(
                                                          0,
                                                          10,
                                                      ),
                                                  )
                                                : '—'}
                                        </span>

                                        <Tag
                                            label={
                                                contest.archive_reason ===
                                                'dismissed'
                                                    ? 'DESCARTADO'
                                                    : 'EXPIRADO'
                                            }
                                            kind={
                                                contest.archive_reason ===
                                                'dismissed'
                                                    ? 'neg'
                                                    : 'neu'
                                            }
                                        />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </main>

                <DossierModal
                    contest={selected}
                    allowPin={false}
                    onOpenChange={(open) => !open && setSelected(null)}
                    onContestChange={handleChange}
                    onDismissed={() =>
                        router.reload({ only: ['contests', 'counts'] })
                    }
                />

                <ErrorToast />
            </div>
        </>
    );
}
