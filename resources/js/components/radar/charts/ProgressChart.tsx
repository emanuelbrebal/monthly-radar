import { useState } from 'react';

import {
    ChartFrame,
    ChartTooltip,
    pctLabel,
    useMeasuredWidth,
} from '@/components/radar/charts/ChartFrame';
import { dateLabel } from '@/lib/radar';

export type ProgressPoint = {
    contest_id: number;
    label: string;
    date: string | null;
    percent: number;
    cutoff_percent: number | null;
    outcome_label: string;
    source_label: string;
};

type ProgressChartProps = {
    points: ProgressPoint[];
    onSelect: (contestId: number) => void;
};

const H = 260;
const PAD = { top: 16, right: 64, bottom: 32, left: 44 };
const TICKS = [0, 25, 50, 75, 100];

/**
 * "Estou melhorando?" — % de cada prova no tempo (série única, sem caixa de
 * legenda), com o corte de cada prova como traço curto. Tooltip no hover e
 * no foco; clique abre o dossiê.
 */
export function ProgressChart({ points, onSelect }: ProgressChartProps) {
    const [active, setActive] = useState<number | null>(null);
    const [boxRef, W] = useMeasuredWidth(640);

    const times = points.map((p, i) =>
        p.date ? new Date(`${p.date}T12:00:00`).getTime() : i,
    );
    const minT = Math.min(...times);
    const maxT = Math.max(...times);
    const innerW = W - PAD.left - PAD.right;
    const innerH = H - PAD.top - PAD.bottom;

    const x = (i: number) =>
        maxT === minT
            ? PAD.left + innerW / 2
            : PAD.left + ((times[i] - minT) / (maxT - minT)) * innerW;
    const y = (pct: number) => PAD.top + (1 - pct / 100) * innerH;

    const path = points
        .map((p, i) => `${i === 0 ? 'M' : 'L'}${x(i)},${y(p.percent)}`)
        .join(' ');
    const last = points.length - 1;
    const activePoint = active !== null ? points[active] : null;

    return (
        <ChartFrame
            title="Progresso no tempo"
            subtitle="% da nota máxima, por prova"
            legend={
                <p className="t-label flex items-center gap-4 text-muted-fg">
                    <span className="inline-flex items-center gap-1.5">
                        <svg width="18" height="10" aria-hidden="true">
                            <line
                                x1="1"
                                y1="5"
                                x2="17"
                                y2="5"
                                stroke="var(--viz-series)"
                                strokeWidth="2"
                            />
                            <circle
                                cx="9"
                                cy="5"
                                r="4"
                                fill="var(--viz-series)"
                            />
                        </svg>
                        minha nota
                    </span>
                    <span className="inline-flex items-center gap-1.5">
                        <svg width="18" height="10" aria-hidden="true">
                            <line
                                x1="1"
                                y1="5"
                                x2="17"
                                y2="5"
                                stroke="var(--ink-soft)"
                                strokeWidth="2"
                            />
                        </svg>
                        corte
                    </span>
                </p>
            }
            table={{
                columns: ['prova', 'data', 'nota', 'corte', 'resultado'],
                rows: points.map((p) => [
                    p.label,
                    p.date ? dateLabel(p.date) : '—',
                    pctLabel(p.percent),
                    p.cutoff_percent !== null
                        ? pctLabel(p.cutoff_percent)
                        : '—',
                    p.outcome_label,
                ]),
            }}
        >
            <div ref={boxRef} className="relative">
                <svg
                    width={W}
                    height={H}
                    viewBox={`0 0 ${W} ${H}`}
                    className="block"
                    role="img"
                    aria-label={`Nota em % por prova: ${points
                        .map((p) => `${p.label} ${pctLabel(p.percent)}`)
                        .join(', ')}`}
                >
                    {TICKS.map((t) => (
                        <g key={t}>
                            <line
                                x1={PAD.left}
                                x2={W - PAD.right}
                                y1={y(t)}
                                y2={y(t)}
                                stroke="var(--viz-grid)"
                                strokeWidth="1"
                            />
                            <text
                                x={PAD.left - 8}
                                y={y(t)}
                                textAnchor="end"
                                dominantBaseline="middle"
                                className="fill-muted-fg text-[11px] tabular-nums"
                            >
                                {t}%
                            </text>
                        </g>
                    ))}

                    {/* Eixo de tempo: datas (o nome vai no tooltip e na tabela). */}
                    {points.map((p, i) => (
                        <text
                            key={`x-${p.contest_id}`}
                            x={x(i)}
                            y={H - 10}
                            textAnchor={
                                points.length > 1 && i === 0
                                    ? 'start'
                                    : points.length > 1 && i === last
                                      ? 'end'
                                      : 'middle'
                            }
                            className="fill-muted-fg text-[11px] tabular-nums"
                        >
                            {p.date ? dateLabel(p.date) : '—'}
                        </text>
                    ))}

                    {points.map((p, i) =>
                        p.cutoff_percent !== null ? (
                            <line
                                key={`c-${p.contest_id}`}
                                x1={x(i) - 10}
                                x2={x(i) + 10}
                                y1={y(p.cutoff_percent)}
                                y2={y(p.cutoff_percent)}
                                stroke="var(--ink-soft)"
                                strokeWidth="2"
                            />
                        ) : null,
                    )}

                    {points.length > 1 && (
                        <path
                            d={path}
                            fill="none"
                            stroke="var(--viz-series)"
                            strokeWidth="2"
                            strokeLinejoin="round"
                            strokeLinecap="round"
                        />
                    )}

                    {points.map((p, i) => (
                        <g
                            key={`p-${p.contest_id}`}
                            role="button"
                            tabIndex={0}
                            aria-label={`${p.label}: ${pctLabel(p.percent)}, ${p.outcome_label}. Abrir dossiê.`}
                            onMouseEnter={() => setActive(i)}
                            onMouseLeave={() => setActive(null)}
                            onFocus={() => setActive(i)}
                            onBlur={() => setActive(null)}
                            onClick={() => onSelect(p.contest_id)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' || e.key === ' ') {
                                    e.preventDefault();
                                    onSelect(p.contest_id);
                                }
                            }}
                            className="cursor-pointer outline-none [&:focus-visible>circle:last-child]:stroke-focus"
                        >
                            <circle
                                cx={x(i)}
                                cy={y(p.percent)}
                                r="14"
                                fill="transparent"
                            />
                            <circle
                                cx={x(i)}
                                cy={y(p.percent)}
                                r={active === i ? 6 : 5}
                                fill="var(--viz-series)"
                                stroke="var(--paper)"
                                strokeWidth="2"
                            />
                        </g>
                    ))}

                    {last >= 0 && (
                        <text
                            x={x(last) + 10}
                            y={y(points[last].percent)}
                            dominantBaseline="middle"
                            className="fill-ink text-[12px] font-semibold tabular-nums"
                        >
                            {pctLabel(points[last].percent)}
                        </text>
                    )}
                </svg>

                {activePoint && active !== null && (
                    <ChartTooltip
                        x={(x(active) / W) * 100}
                        y={(y(activePoint.percent) / H) * 100}
                        lines={[
                            activePoint.label,
                            `${pctLabel(activePoint.percent)}${
                                activePoint.cutoff_percent !== null
                                    ? ` · corte ${pctLabel(activePoint.cutoff_percent)}`
                                    : ''
                            }`,
                            `${activePoint.outcome_label} · ${activePoint.source_label}`,
                        ]}
                    />
                )}
            </div>
        </ChartFrame>
    );
}
