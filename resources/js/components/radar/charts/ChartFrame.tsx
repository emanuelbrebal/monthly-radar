import type { ReactNode, RefCallback } from 'react';
import { useCallback, useEffect, useState } from 'react';

/**
 * Largura real do contêiner, pra desenhar o SVG em px (1 unidade = 1px).
 * Com viewBox fixo e `w-full`, o texto encolhe junto com o gráfico.
 */
export function useMeasuredWidth(
    fallback: number,
): [RefCallback<HTMLDivElement>, number] {
    const [node, setNode] = useState<HTMLDivElement | null>(null);
    const [width, setWidth] = useState(fallback);
    const ref = useCallback((el: HTMLDivElement | null) => setNode(el), []);

    useEffect(() => {
        if (node === null) {
            return;
        }

        const observer = new ResizeObserver(([entry]) => {
            setWidth(Math.max(280, Math.floor(entry.contentRect.width)));
        });
        observer.observe(node);

        return () => observer.disconnect();
    }, [node]);

    return [ref, width];
}

export type TableSpec = {
    columns: string[];
    rows: (string | number)[][];
};

type ChartFrameProps = {
    title: string;
    subtitle?: string;
    table: TableSpec;
    legend?: ReactNode;
    children: ReactNode;
};

/**
 * Moldura comum dos gráficos do painel: título, legenda opcional e a
 * alternativa em tabela (`ver como tabela`) — o gráfico nunca é o único
 * caminho até o número (docs/03-ui-inventario.md → "Painel").
 */
export function ChartFrame({
    title,
    subtitle,
    table,
    legend,
    children,
}: ChartFrameProps) {
    const [asTable, setAsTable] = useState(false);

    return (
        <figure className="flex flex-col gap-2 border border-line-strong bg-paper p-4">
            <figcaption className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                <span className="t-title">{title}</span>
                {subtitle && (
                    <span className="t-body text-muted-fg">{subtitle}</span>
                )}
                <button
                    type="button"
                    onClick={() => setAsTable((v) => !v)}
                    aria-pressed={asTable}
                    className="t-body ml-auto inline-flex min-h-6 items-center underline underline-offset-2 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                >
                    {asTable ? 'ver gráfico' : 'ver como tabela'}
                </button>
            </figcaption>

            {!asTable && legend}

            {asTable ? (
                <div className="overflow-x-auto">
                    <table className="t-body w-full border-collapse text-left">
                        <thead>
                            <tr>
                                {table.columns.map((c) => (
                                    <th
                                        key={c}
                                        scope="col"
                                        className="t-label border-b border-line-strong py-1 pr-3 text-muted-fg"
                                    >
                                        {c}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {table.rows.map((row, i) => (
                                <tr key={i}>
                                    {row.map((cell, j) => (
                                        <td
                                            key={j}
                                            className="border-b border-line py-1 pr-3 tabular-nums"
                                        >
                                            {cell}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            ) : (
                children
            )}
        </figure>
    );
}

/** Caixa de tooltip posicionada em % do contêiner do gráfico. */
export function ChartTooltip({
    x,
    y,
    lines,
}: {
    x: number;
    y: number;
    lines: string[];
}) {
    return (
        <div
            role="status"
            className="surface-card t-body pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full border border-line-strong px-2 py-1 whitespace-nowrap shadow-sm"
            style={{ left: `${x}%`, top: `calc(${y}% - 10px)` }}
        >
            {lines.map((line, i) => (
                <div key={i} className={i === 0 ? 'font-semibold' : ''}>
                    {line}
                </div>
            ))}
        </div>
    );
}

export function pctLabel(value: number): string {
    return `${value.toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`;
}

export function signedLabel(value: number, unit = ''): string {
    const abs = Math.abs(value).toLocaleString('pt-BR', {
        maximumFractionDigits: 1,
    });

    return `${value >= 0 ? '+' : '−'}${abs}${unit}`;
}

/**
 * Barra horizontal com a ponta de dado arredondada (4px) e a base reta
 * (marks-and-anatomy da skill dataviz). `x0` é a base, `x1` a ponta.
 */
export function barPath(x0: number, x1: number, y: number, h: number): string {
    const r = Math.min(4, Math.abs(x1 - x0), h / 2);

    if (Math.abs(x1 - x0) < 0.5) {
        return '';
    }

    if (x1 >= x0) {
        return `M${x0},${y} H${x1 - r} Q${x1},${y} ${x1},${y + r} V${y + h - r} Q${x1},${y + h} ${x1 - r},${y + h} H${x0} Z`;
    }

    return `M${x0},${y} H${x1 + r} Q${x1},${y} ${x1},${y + r} V${y + h - r} Q${x1},${y + h} ${x1 + r},${y + h} H${x0} Z`;
}
