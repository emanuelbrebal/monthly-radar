import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

import { RadarAnim } from '@/components/radar/RadarAnim';
import { StateFilterPopover } from '@/components/radar/StateFilterPopover';
import { csrfFetch } from '@/lib/csrf-fetch';
import type { AreaOption, RefreshInfo, RegionOption, SearchRunDto, TiAreaValue } from '@/types/radar';

type SweepControlProps = {
    areas: AreaOption[];
    regions: RegionOption[];
    states: string[];
    lastRun: SearchRunDto | null;
    refresh: RefreshInfo;
};

const ACTIVE_STATUSES = ['queued', 'scraping', 'enriching'];

function statusLine(run: SearchRunDto | null): string {
    if (run === null) {
        return 'Nenhuma varredura ainda.';
    }

    const isRefresh = run.kind === 'refresh';

    if (ACTIVE_STATUSES.includes(run.status)) {
        return isRefresh ? 'Revisando acompanhados…' : 'Varrendo fontes…';
    }

    if (run.status === 'failed') {
        return isRefresh ? 'Revisão falhou. Tenta de novo.' : 'Sinal perdido. Verifica as fontes.';
    }

    if (isRefresh) {
        const checked = run.counts.enriched ?? 0;
        const changed = run.counts.updated ?? 0;
        const failed = run.counts.failed ?? 0;

        return `Revisão feita. ${checked} conferidos, ${changed} mudaram${failed > 0 ? `, ${failed} falharam` : ''}.`;
    }

    const created = run.counts.created ?? 0;
    const updated = run.counts.updated ?? 0;

    return `Varredura feita. ${created} novos, ${updated} atualizados.`;
}

/** docs/03-ui-inventario.md → "Controle de varredura (masthead)". */
export function SweepControl({ areas, regions, states, lastRun, refresh }: SweepControlProps) {
    const [area, setArea] = useState<TiAreaValue>(areas[0]?.value ?? 'development');
    const [run, setRun] = useState<SearchRunDto | null>(lastRun);
    const [error, setError] = useState<string | null>(null);
    const [starting, setStarting] = useState(false);
    const intervalRef = useRef<number | null>(null);

    useEffect(() => {
        if (run === null || !ACTIVE_STATUSES.includes(run.status)) {
            return;
        }

        intervalRef.current = window.setInterval(async () => {
            const response = await csrfFetch(`/sweeps/${run.id}`);

            if (!response.ok) {
                return;
            }

            const data: { run: SearchRunDto } = await response.json();

            setRun(data.run);

            if (!ACTIVE_STATUSES.includes(data.run.status)) {
                router.reload({ only: ['visibleContests', 'lastRun', 'refresh'] });
            }
        }, 2500);

        return () => {
            if (intervalRef.current !== null) {
                window.clearInterval(intervalRef.current);
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [run?.id, run?.status]);

    async function startSweep() {
        setError(null);
        setStarting(true);

        const response = await csrfFetch('/sweeps', {
            method: 'POST',
            body: JSON.stringify({ area }),
        });

        setStarting(false);

        if (response.status === 409) {
            const data: { run: SearchRunDto } = await response.json();
            setRun(data.run);

            return;
        }

        if (!response.ok) {
            const data: { message?: string } = await response.json().catch(() => ({}));
            setError(data.message ?? 'Não deu pra varrer agora.');

            return;
        }

        const data: { run: SearchRunDto } = await response.json();
        setRun(data.run);
    }

    /** Botão "atualizar acompanhados" (T8) — mesma run/polling da varredura. */
    async function startRefresh() {
        setError(null);
        setStarting(true);

        const response = await csrfFetch('/contests/refresh-tracked', { method: 'POST' });

        setStarting(false);

        if (response.status === 409) {
            const data: { run: SearchRunDto } = await response.json();
            setRun(data.run);

            return;
        }

        if (!response.ok) {
            const data: { message?: string } = await response.json().catch(() => ({}));
            setError(data.message ?? 'Não deu pra revisar agora.');

            return;
        }

        const data: { run: SearchRunDto } = await response.json();
        setRun(data.run);
    }

    const sweeping = starting || (run !== null && ACTIVE_STATUSES.includes(run.status));
    const refreshing = sweeping && run?.kind === 'refresh';
    const refreshHint = !refresh.available
        ? 'Sem ANTHROPIC_API_KEY: a revisão automática precisa de LLM.'
        : refresh.tracked === 0
          ? 'Nenhum concurso na maleta ou marcado como "quero fazer".'
          : `Re-checa na web os ${refresh.tracked} concursos acompanhados (custa buscas de LLM).`;
    const sourceErrors = !sweeping ? (run?.meta.errors ?? []) : [];

    return (
        <div className="flex flex-wrap items-center gap-3">
            <select
                aria-label="Área de TI"
                className="text-body border border-line-strong bg-card px-2 py-1.5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                value={area}
                disabled={sweeping}
                onChange={(e) => setArea(e.target.value as TiAreaValue)}
            >
                {areas.map((a) => (
                    <option key={a.value} value={a.value}>
                        {a.label}
                    </option>
                ))}
            </select>

            <StateFilterPopover regions={regions} states={states} />

            <button
                className="flex items-center gap-2 border border-line-strong bg-red-text px-4 py-1.5 text-sm font-semibold text-card focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:cursor-not-allowed"
                disabled={sweeping}
                onClick={startSweep}
                type="button"
            >
                {sweeping && !refreshing && <RadarAnim size={16} />}
                {sweeping && !refreshing ? 'varrendo…' : 'varrer'}
            </button>

            <button
                className="flex items-center gap-2 border border-line-strong bg-card px-3 py-1.5 text-sm font-semibold text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:cursor-not-allowed disabled:text-muted-fg"
                disabled={sweeping || !refresh.available || refresh.tracked === 0}
                onClick={startRefresh}
                title={refreshHint}
                aria-describedby="refresh-tracked-hint"
                type="button"
            >
                {refreshing && <RadarAnim size={16} />}
                {refreshing ? 'revisando…' : refresh.available ? `atualizar acompanhados (${refresh.tracked})` : 'atualizar acompanhados (sem LLM)'}
            </button>
            <span id="refresh-tracked-hint" className="sr-only">
                {refreshHint}
            </span>

            <span role="status" className="text-body text-muted-fg">{error ?? (starting ? 'Começando…' : statusLine(run))}</span>

            {sourceErrors.length > 0 && (
                <>
                    <span
                        tabIndex={0}
                        aria-describedby="sweep-source-errors"
                        className="text-body cursor-help font-semibold text-red-text underline decoration-dotted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                        title={sourceErrors.map((e) => `${e.source}: ${e.message}`).join('\n')}
                    >
                        {sourceErrors.length} fonte{sourceErrors.length > 1 ? 's' : ''} {sourceErrors.length > 1 ? 'falharam' : 'falhou'}
                    </span>
                    <span id="sweep-source-errors" className="sr-only">
                        {sourceErrors.map((e) => `${e.source}: ${e.message}`).join('. ')}
                    </span>
                </>
            )}
        </div>
    );
}
