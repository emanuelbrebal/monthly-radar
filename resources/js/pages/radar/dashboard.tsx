import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import { BarList } from '@/components/radar/charts/BarList';
import { pctLabel, signedLabel } from '@/components/radar/charts/ChartFrame';
import { ProgressChart } from '@/components/radar/charts/ProgressChart';
import type { ProgressPoint } from '@/components/radar/charts/ProgressChart';
import { DossierModal } from '@/components/radar/DossierModal';
import { ErrorToast } from '@/components/radar/ErrorToast';
import { RadarMark } from '@/components/radar/RadarMark';
import type { ContestDto } from '@/types/radar';

type Identity = { contest_id: number; label: string };

type Summary = {
    kpis: {
        exams_taken: number;
        with_result: number;
        approved: number;
        average_percent: number | null;
        best: (Identity & { percent: number }) | null;
    };
    timeline: ProgressPoint[];
    margins: (Identity & { margin_points: number; margin: number | null })[];
    subjects: { subject: string; percent: number; exams: number }[];
    funnel: { key: string; label: string; count: number }[];
};

type DashboardProps = {
    summary: Summary;
    contests: ContestDto[];
};

function Kpi({
    label,
    value,
    note,
}: {
    label: string;
    value: string;
    note?: string;
}) {
    return (
        <div className="border border-line-strong bg-paper px-4 py-3">
            <p className="t-label text-muted-fg">{label}</p>
            <p className="mt-1 text-3xl font-semibold">{value}</p>
            {note && <p className="t-body text-muted-fg">{note}</p>}
        </div>
    );
}

/**
 * Painel (T7) — docs/03-ui-inventario.md → "Painel". Meu progresso nas
 * provas; números vêm prontos do backend (ResultsSummary), aqui só desenho.
 */
export default function RadarDashboard() {
    const { summary, contests } = usePage<DashboardProps>().props;
    const [selected, setSelected] = useState<ContestDto | null>(null);
    const { kpis } = summary;

    function open(contestId: number) {
        setSelected(contests.find((c) => c.id === contestId) ?? null);
    }

    function reload(updated?: ContestDto) {
        if (updated) {
            setSelected(updated);
        }

        router.reload({ only: ['summary', 'contests'] });
    }

    const marginMax = Math.max(
        10,
        ...summary.margins.map((m) => Math.abs(m.margin_points)),
    );
    const funnelMax = Math.max(1, ...summary.funnel.map((f) => f.count));

    return (
        <>
            <Head title="Painel · Radar Mensal" />
            <div className="flex min-h-screen flex-col bg-paper text-ink">
                <header className="shrink-0 border-b border-line-strong bg-paper px-6 py-3">
                    <div className="flex flex-wrap items-center gap-4">
                        <RadarMark size={28} />
                        <h1 className="text-display">painel</h1>
                        <Link
                            href="/archive"
                            className="t-body ml-auto inline-flex min-h-6 items-center underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                        >
                            arquivo
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
                    aria-label="Meu progresso nas provas"
                    className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-6"
                >
                    <section
                        aria-label="Resumo"
                        className="grid grid-cols-2 gap-3 md:grid-cols-5"
                    >
                        <Kpi
                            label="provas feitas"
                            value={String(kpis.exams_taken)}
                        />
                        <Kpi
                            label="com resultado"
                            value={String(kpis.with_result)}
                        />
                        <Kpi
                            label="aprovações + CR"
                            value={String(kpis.approved)}
                        />
                        <Kpi
                            label="média"
                            value={
                                kpis.average_percent !== null
                                    ? pctLabel(kpis.average_percent)
                                    : '—'
                            }
                        />
                        <Kpi
                            label="melhor"
                            value={
                                kpis.best ? pctLabel(kpis.best.percent) : '—'
                            }
                            note={kpis.best?.label}
                        />
                    </section>

                    {summary.timeline.length === 0 ? (
                        <p className="t-body border border-line-strong p-6 text-center text-muted-fg">
                            Nenhum resultado ainda. Marca &ldquo;prova
                            feita&rdquo; num concurso e lança a nota no dossiê.
                        </p>
                    ) : (
                        <div className="grid gap-6 lg:grid-cols-2">
                            <div className="lg:col-span-2">
                                <ProgressChart
                                    points={summary.timeline}
                                    onSelect={open}
                                />
                            </div>

                            {summary.margins.length > 0 && (
                                <BarList
                                    title="Margem sobre o corte"
                                    subtitle="pontos percentuais acima (+) ou abaixo (−)"
                                    domain={[-marginMax, marginMax]}
                                    rows={summary.margins.map((m) => ({
                                        key: String(m.contest_id),
                                        label: m.label,
                                        value: m.margin_points,
                                        valueLabel: signedLabel(
                                            m.margin_points,
                                            ' p.p.',
                                        ),
                                        detail:
                                            m.margin !== null
                                                ? [
                                                      `${signedLabel(m.margin)} pontos na escala da prova`,
                                                  ]
                                                : undefined,
                                        onSelect: () => open(m.contest_id),
                                    }))}
                                    table={{
                                        columns: [
                                            'prova',
                                            'margem (p.p.)',
                                            'margem (pontos)',
                                        ],
                                        rows: summary.margins.map((m) => [
                                            m.label,
                                            signedLabel(m.margin_points),
                                            m.margin !== null
                                                ? signedLabel(m.margin)
                                                : '—',
                                        ]),
                                    }}
                                />
                            )}

                            {summary.subjects.length > 0 && (
                                <BarList
                                    title="Por matéria"
                                    subtitle="% somando todas as provas, da mais fraca pra mais forte"
                                    domain={[0, 100]}
                                    rows={summary.subjects.map((s) => ({
                                        key: s.subject,
                                        label: s.subject,
                                        value: s.percent,
                                        valueLabel: pctLabel(s.percent),
                                        detail: [
                                            `${s.exams} ${s.exams === 1 ? 'prova' : 'provas'}`,
                                        ],
                                    }))}
                                    table={{
                                        columns: ['matéria', '%', 'provas'],
                                        rows: summary.subjects.map((s) => [
                                            s.subject,
                                            pctLabel(s.percent),
                                            s.exams,
                                        ]),
                                    }}
                                />
                            )}
                        </div>
                    )}

                    <BarList
                        title="Funil"
                        subtitle="todos os concursos, até onde cheguei em cada um"
                        domain={[0, funnelMax]}
                        rows={summary.funnel.map((f) => ({
                            key: f.key,
                            label: f.label,
                            value: f.count,
                            valueLabel: String(f.count),
                        }))}
                        table={{
                            columns: ['passo', 'concursos'],
                            rows: summary.funnel.map((f) => [f.label, f.count]),
                        }}
                    />
                </main>

                <DossierModal
                    contest={selected}
                    onOpenChange={(open) => !open && setSelected(null)}
                    onContestChange={reload}
                    onDismissed={() => reload()}
                />

                <ErrorToast />
            </div>
        </>
    );
}
