export type NavKey = 'ArrowLeft' | 'ArrowRight' | 'ArrowUp' | 'ArrowDown';

/** Colunas da grade da maleta (`grid-cols-4`), na grade e em cada grupo de nível. */
const COLUMNS = 4;

/**
 * Linhas visuais da maleta. Cada bloco (a grade inteira ou um grupo de nível)
 * é uma grade própria de 4 colunas, então é fatiado de 4 em 4; a última linha
 * de um bloco pode ser incompleta.
 */
export function buildRows(blocks: number[][]): number[][] {
    return blocks.flatMap((block) =>
        Array.from({ length: Math.ceil(block.length / COLUMNS) }, (_, i) =>
            block.slice(i * COLUMNS, (i + 1) * COLUMNS),
        ),
    );
}

/**
 * Próxima seleção ao apertar uma seta. ←/→ andam pela ordem visual; ↑/↓ vão
 * para a linha de cima/baixo na mesma coluna (ou na última, se a linha for
 * mais curta). Sem linha na direção pedida, a seleção não muda.
 */
export function moveSelection(
    rows: number[][],
    selected: number,
    key: NavKey,
): number {
    const flat = rows.flat();
    const rowIndex = rows.findIndex((row) => row.includes(selected));

    if (rowIndex === -1) {
        // Seleção fora das linhas (ex.: slot vazio ao trocar de vista).
        return flat[0] ?? selected;
    }

    if (key === 'ArrowLeft' || key === 'ArrowRight') {
        const step = key === 'ArrowRight' ? 1 : -1;
        const next = Math.min(
            flat.length - 1,
            Math.max(0, flat.indexOf(selected) + step),
        );

        return flat[next];
    }

    const target = rows[rowIndex + (key === 'ArrowDown' ? 1 : -1)];

    if (target === undefined) {
        return selected;
    }

    const column = rows[rowIndex].indexOf(selected);

    return target[Math.min(column, target.length - 1)];
}
