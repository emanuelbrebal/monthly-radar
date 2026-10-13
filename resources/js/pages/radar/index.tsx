import { Head, router, usePage } from '@inertiajs/react';
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
                <header className="rm-rule-double shrink-0 border-b-[3px] border-double border-ink bg-paper px-6 py-3">
                    <div className="flex flex-wrap items-center gap-4">
                        <RadarMark size={28} />
                        <h1 className="rm-display text-2xl">radar mensal · concursos</h1>
                        <div className="ml-auto">
                            <SweepControl areas={areas} regions={regions} states={preferences.states} lastRun={lastRun} />
                        </div>
                    </div>
                </header>

                <main className="grid min-h-0 flex-1 gap-6 overflow-hidden p-6" style={{ gridTemplateColumns: 'minmax(420px, 5fr) 7fr' }}>
                    <AttacheCase contests={pinnedContests} onOpen={openDossier} onContestChange={updateContest} />

                    <section className="rm-dot-grid flex min-h-0 flex-col gap-6 overflow-hidden rounded-lg border border-ink bg-paper p-4">
                        <div className="max-h-[52vh] shrink-0 overflow-y-auto">
                            <MonthCalendar initialMonth={month} initialContests={contests} onSelectContest={openDossier} />
                        </div>
                        <ItemBox contests={contests} onSelect={openDossier} onPinChange={updateContest} onRestored={reloadMesa} />
                    </section>
                </main>

                <DossierModal
                    contest={selected}
                    onOpenChange={(open) => !open && setSelected(null)}
                    onContestChange={updateContest}
                    onDismissed={handleDismissed}
                />

                {undoToast && (
                    <div className="rm-mono fixed right-4 bottom-4 flex items-center gap-3 rounded-md border border-ink bg-card px-3 py-2 text-[11px] shadow-[4px_4px_0_rgba(26,22,18,0.18)]">
                        Descartado. Fora da mesa.
                        <button type="button" className="underline" onClick={undoDismiss}>
                            desfazer
                        </button>
                    </div>
                )}

                <ErrorToast />
            </div>
        </>
    );
}
