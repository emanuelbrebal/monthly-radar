import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

import { RadarAnim } from '@/components/radar/RadarAnim';
import { StateFilterPopover } from '@/components/radar/StateFilterPopover';
import { csrfFetch } from '@/lib/csrf-fetch';
import type { AreaOption, RegionOption, SearchRunDto, TiAreaValue } from '@/types/radar';

type SweepControlProps = {
    areas: AreaOption[];
    regions: RegionOption[];
    states: string[];
    lastRun: SearchRunDto | null;
};

const ACTIVE_STATUSES = ['queued', 'scraping', 'enriching'];

function statusLine(run: SearchRunDto | null): string {
    if (run === null) {
        return 'Nenhuma varredura ainda.';
    }

    if (ACTIVE_STATUSES.includes(run.status)) {
        return 'Varrendo fontes…';
    }

    if (run.status === 'failed') {
        return 'Sinal perdido. Verifica as fontes.';
    }

    const created = run.counts.created ?? 0;
    const updated = run.counts.updated ?? 0;

    return `Varredura feita. ${created} novos, ${updated} atualizados.`;
}

/** docs/03-ui-inventario.md → "Controle de varredura (masthead)". */
export function SweepControl({ areas, regions, states, lastRun }: SweepControlProps) {
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
                router.reload({ only: ['visibleContests', 'lastRun'] });
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

    const sweeping = starting || (run !== null && ACTIVE_STATUSES.includes(run.status));
    const sourceErrors = !sweeping ? (run?.meta.errors ?? []) : [];

    return (
        <div className="flex flex-wrap items-center gap-3">
            <select
                className="rm-mono rounded-md border border-ink bg-card px-2 py-1.5 text-[11px] tracking-wide uppercase"
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
                className="flex items-center gap-2 rounded-md border-[1.5px] border-ink bg-red px-4 py-1.5 text-sm font-semibold text-paper disabled:opacity-60"
                disabled={sweeping}
                onClick={startSweep}
                type="button"
            >
                {sweeping && <RadarAnim size={16} />}
                {sweeping ? 'varrendo…' : 'varrer'}
            </button>

            <span className="rm-mono text-[11px] text-ink-mid">{error ?? (starting ? 'Varrendo fontes…' : statusLine(run))}</span>

            {sourceErrors.length > 0 && (
                <span
                    className="rm-mono cursor-help text-[11px] text-red underline decoration-dotted"
                    title={sourceErrors.map((e) => `${e.source}: ${e.message}`).join('\n')}
                >
                    {sourceErrors.length} fonte{sourceErrors.length > 1 ? 's' : ''} {sourceErrors.length > 1 ? 'falharam' : 'falhou'}
                </span>
            )}
        </div>
    );
}
