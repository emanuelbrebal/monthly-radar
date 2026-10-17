import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useRef, useState } from 'react';

import { AttacheCase } from '@/components/radar/AttacheCase';
import { DossierModal } from '@/components/radar/DossierModal';
import { ErrorToast } from '@/components/radar/ErrorToast';
import { ItemBox } from '@/components/radar/ItemBox';
import { MonthCalendar } from '@/components/radar/MonthCalendar';
import { RadarMark } from '@/components/radar/RadarMark';
import { SweepControl } from '@/components/radar/SweepControl';
import { csrfFetch } from '@/lib/csrf-fetch';
import { notifyError } from '@/lib/notify';
import type { AreaOption, ContestDto, RegionOption, SearchRunDto } from '@/types/radar';

type RadarPageProps = {
    visibleContests: ContestDto[];
    month: string;
    lastRun: SearchRunDto | null;
    areas: AreaOption[];
    preferences: { states: string[] };
    regions: RegionOption[];
};

/**
 * Mesa + maleta (T4/T5) — docs/03-ui-inventario.md → "Layout da tela
 * principal". `overrides` guarda updates otimistas (pin/enrollment/descarte)
 * por cima da prop do Inertia — nunca copiar a lista inteira pra useState
 * (bug de sincronização encontrado na T4, ver memória do projeto).
 */
export default function RadarIndex() {
    const { visibleContests, month, lastRun, areas, preferences, regions } = usePage<RadarPageProps>().props;
    const [overrides, setOverrides] = useState<Record<number, ContestDto>>({});
    const [selected, setSelected] = useState<ContestDto | null>(null);
    const [undoToast, setUndoToast] = useState<ContestDto | null>(null);
    const undoTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const contests = useMemo(
        () =>
            visibleContests
                .map((c) => overrides[c.id] ?? c)
                .filter((c) => c.enrollment_status !== 'dismissed'),
        [visibleContests, overrides],
    );

    const pinnedContests = useMemo(() => contests.filter((c) => c.pinned_slot !== null), [contests]);

    function updateContest(updated: ContestDto) {
        setOverrides((prev) => ({ ...prev, [updated.id]: updated }));
        setSelected((prev) => (prev !== null && prev.id === updated.id ? updated : prev));
    }

    function openDossier(contest: ContestDto) {
        setSelected(contest);
    }

    function handleDismissed(contest: ContestDto) {
        updateContest(contest);

        if (undoTimer.current) {
            clearTimeout(undoTimer.current);
        }

        setUndoToast(contest);
        undoTimer.current = setTimeout(() => setUndoToast(null), 8000);
    }

    async function undoDismiss() {
        if (undoToast === null) {
            return;
        }

        const response = await csrfFetch(`/contests/${undoToast.id}/restore`, { method: 'POST' });

        if (response.ok) {
            const data: { contest: ContestDto } = await response.json();
            updateContest(data.contest);
        } else {
            notifyError('Não deu pra desfazer. Tenta de novo pela caixa de itens.');
        }

        if (undoTimer.current) {
            clearTimeout(undoTimer.current);
        }

        setUndoToast(null);
    }

    function reloadMesa() {
        router.reload({ only: ['visibleContests'] });
    }

    return (
        <>
            <Head title="Radar Mensal · Concursos" />
            <div className="flex h-screen flex-col overflow-hidden bg-paper text-ink">
                <header className="shrink-0 border-b border-line-strong bg-paper px-6 py-3">
                    <div className="flex flex-wrap items-center gap-4">
                        <RadarMark size={28} />
                        <h1 className="text-display">radar mensal · concursos</h1>
                        <Link
                            href="/archive"
                            className="t-body ml-auto inline-flex min-h-6 items-center underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                        >
                            arquivo
                        </Link>
                        <div>
                            <SweepControl areas={areas} regions={regions} states={preferences.states} lastRun={lastRun} />
                        </div>
                    </div>
                </header>

                <main aria-label="Mesa de concursos" className="grid min-h-0 flex-1 gap-6 overflow-hidden p-6" style={{ gridTemplateColumns: 'minmax(420px, 5fr) 7fr' }}>
                    <div className="flex min-h-0 flex-col overflow-hidden">
                        <AttacheCase contests={pinnedContests} onOpen={openDossier} onContestChange={updateContest} />
                    </div>

                    <div className="flex min-h-0 flex-col gap-6 overflow-hidden border border-line-strong bg-paper p-4">
                        <section aria-label="Calendário do mês" className="max-h-[52vh] shrink-0 overflow-y-auto">
                            <MonthCalendar initialMonth={month} initialContests={contests} onSelectContest={openDossier} />
                        </section>
                        <section aria-label="Caixa de itens" className="flex min-h-0 flex-1 flex-col overflow-hidden">
                            <ItemBox contests={contests} onSelect={openDossier} onPinChange={updateContest} onRestored={reloadMesa} />
                        </section>
                    </div>
                </main>

                <DossierModal
                    contest={selected}
                    onOpenChange={(open) => !open && setSelected(null)}
                    onContestChange={updateContest}
                    onDismissed={handleDismissed}
                />

                {undoToast && (
                    <div
                        role="status"
                        className="surface-card text-body fixed right-4 bottom-4 flex items-center gap-3 border border-line-strong px-3 py-2"
                    >
                        Descartado. Fora da mesa.
                        <button
                            type="button"
                            className="inline-flex min-h-6 items-center px-1 font-semibold underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                            onClick={undoDismiss}
                        >
                            desfazer
                        </button>
                    </div>
                )}

                <ErrorToast />
            </div>
        </>
    );
}
