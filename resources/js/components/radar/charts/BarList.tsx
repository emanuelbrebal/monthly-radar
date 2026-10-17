import { useState } from 'react';

import {
    barPath,
    ChartFrame,
    ChartTooltip,
    useMeasuredWidth,
} from '@/components/radar/charts/ChartFrame';
import type { TableSpec } from '@/components/radar/charts/ChartFrame';

export type BarRow = {
    key: string;
    label: string;
    value: number;
    /** Rótulo na ponta da barra (ex. "75%", "4"). */
    valueLabel: string;
    /** Linhas extras do tooltip. */
    detail?: string[];
    /** Barra divergente: abaixo de zero usa a cor "negativa". */
    onSelect?: () => void;
};

type BarListProps = {
    title: string;
    subtitle?: string;
    rows: BarRow[];
    /** Domínio [min, max]. min < 0 → barras divergentes com eixo no zero. */
    domain: [number, number];
    table: TableSpec;
};

const ROW = 32;

function clip(text: string, max: number): string {
    return text.length > max ? `${text.slice(0, max - 1)}…` : text;
}

/**
 * Rótulo da categoria. "SIGLA ano · trecho do título" (desambiguado pelo
 * ResultsSummary) vira duas linhas, pra o trecho que diferencia não ser o
 * que o corte de largura come.
 */
function RowLabel({ label, x, y }: { label: string; x: number; y: number }) {
    const [head, tail] = label.split(' · ');

    if (tail === undefined) {
        return (
            <text
                x={x}
                y={y}
                textAnchor="end"
                dominantBaseline="middle"
                className="fill-ink text-[12px]"
            >
                {clip(label, 26)}
            </text>
        );
    }

    return (
        <text x={x} y={y} textAnchor="end" className="fill-ink text-[12px]">
            <tspan x={x} dy="-0.2em">
                {clip(head, 26)}
            </tspan>
            <tspan x={x} dy="1.15em" className="fill-muted-fg text-[11px]">
                {clip(tail, 28)}
            </tspan>
        </text>
    );
}
const BAR = 20;
const LABEL_W = 180;
const VALUE_W = 72;

/**
 * Barras horizontais de série única (por matéria, funil) ou divergentes
 * (margem sobre o corte). Polaridade vem da posição (lado do zero) e do
 * sinal no rótulo; a cor só reforça.
 */
export function BarList({
    title,
    subtitle,
    rows,
    domain,
    table,
}: BarListProps) {
    const [active, setActive] = useState<number | null>(null);
    const [boxRef, W] = useMeasuredWidth(640);
    const [min, max] = domain;
    const diverging = min < 0;
    const H = rows.length * ROW + 8;
    // Divergente: reserva espaço pro rótulo do valor negativo à esquerda.
    const x0 = LABEL_W + (diverging ? VALUE_W : 0);
    const x1 = W - VALUE_W;
    const scale = (v: number) => x0 + ((v - min) / (max - min)) * (x1 - x0);
    const zero = scale(diverging ? 0 : min);
    const activeRow = active !== null ? rows[active] : null;

    return (
        <ChartFrame title={title} subtitle={subtitle} table={table}>
            <div ref={boxRef} className="relative">
                <svg
                    width={W}
                    height={H}
                    viewBox={`0 0 ${W} ${H}`}
                    className="block"
                    role="img"
                    aria-label={`${title}: ${rows
                        .map((r) => `${r.label} ${r.valueLabel}`)
                        .join(', ')}`}
                >
                    <line
                        x1={zero}
                        x2={zero}
                        y1={0}
                        y2={H}
                        stroke="var(--line-strong)"
                        strokeWidth="1"
                    />

                    {rows.map((row, i) => {
                        const y = i * ROW + (ROW - BAR) / 2 + 4;
                        const end = scale(row.value);
                        const negative = row.value < 0;
                        const interactive = row.onSelect !== undefined;

                        return (
                            <g
                                key={row.key}
                                role={interactive ? 'button' : undefined}
                                tabIndex={interactive ? 0 : undefined}
                                aria-label={
                                    interactive
                                        ? `${row.label}: ${row.valueLabel}. Abrir dossiê.`
                                        : undefined
                                }
                                onMouseEnter={() => setActive(i)}
                                onMouseLeave={() => setActive(null)}
                                onFocus={() => setActive(i)}
                                onBlur={() => setActive(null)}
                                onClick={row.onSelect}
                                onKeyDown={(e) => {
                                    if (
                                        row.onSelect &&
                                        (e.key === 'Enter' || e.key === ' ')
                                    ) {
                                        e.preventDefault();
                                        row.onSelect();
                                    }
                                }}
                                className={
                                    interactive
                                        ? 'cursor-pointer outline-none focus-visible:[&>rect]:stroke-focus'
                                        : undefined
                                }
                            >
                                <rect
                                    x={0}
                                    y={i * ROW + 4}
                                    width={W}
                                    height={ROW}
                                    fill={
                                        active === i
                                            ? 'var(--paper-soft)'
                                            : 'transparent'
                                    }
                                    stroke="transparent"
                                    strokeWidth="2"
                                />
                                <RowLabel
                                    label={row.label}
                                    x={LABEL_W - 10}
                                    y={y + BAR / 2}
                                />
                                <path
                                    d={barPath(zero, end, y, BAR)}
                                    fill={
                                        negative
                                            ? 'var(--viz-neg)'
                                            : 'var(--viz-series)'
                                    }
                                />
                                <text
                                    x={
                                        negative
                                            ? end - 6
                                            : Math.max(end, zero) + 6
                                    }
                                    y={y + BAR / 2}
                                    textAnchor={negative ? 'end' : 'start'}
                                    dominantBaseline="middle"
                                    className="fill-ink text-[12px] font-semibold tabular-nums"
                                >
                                    {row.valueLabel}
                                </text>
                            </g>
                        );
                    })}
                </svg>

                {activeRow && active !== null && activeRow.detail && (
                    <ChartTooltip
                        x={(scale(activeRow.value) / W) * 100}
                        y={((active * ROW + 4) / H) * 100}
                        lines={[activeRow.label, ...activeRow.detail]}
                    />
                )}
            </div>
        </ChartFrame>
    );
}
