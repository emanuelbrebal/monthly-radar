import assert from 'node:assert/strict';
import { test } from 'node:test';

import { buildRows, moveSelection } from './case-nav.ts';

const GRID = buildRows([Array.from({ length: 12 }, (_, i) => i)]);

// "elite" com 2 itens, "alto" com 5 (linha cheia + linha com 1), "base" com 1.
const TIERS = buildRows([[0, 3], [1, 2, 5, 6, 9], [11]]);

test('buildRows slices each block by 4 columns', () => {
    assert.deepEqual(GRID, [
        [0, 1, 2, 3],
        [4, 5, 6, 7],
        [8, 9, 10, 11],
    ]);
    assert.deepEqual(TIERS, [[0, 3], [1, 2, 5, 6], [9], [11]]);
});

test('grid: up/down keep the column and stop at the edges', () => {
    assert.equal(moveSelection(GRID, 5, 'ArrowDown'), 9);
    assert.equal(moveSelection(GRID, 5, 'ArrowUp'), 1);
    assert.equal(moveSelection(GRID, 10, 'ArrowDown'), 10);
    assert.equal(moveSelection(GRID, 2, 'ArrowUp'), 2);
});

test('left/right follow visual order and clamp at the ends', () => {
    assert.equal(moveSelection(GRID, 3, 'ArrowRight'), 4);
    assert.equal(moveSelection(GRID, 0, 'ArrowLeft'), 0);
    assert.equal(moveSelection(GRID, 11, 'ArrowRight'), 11);
    assert.equal(moveSelection(TIERS, 3, 'ArrowRight'), 1);
});

test('tiers: down crosses groups clamping to the last column of a short row', () => {
    // 3 (col 1 do grupo elite) → col 1 da 1ª linha do alto = slot 2
    assert.equal(moveSelection(TIERS, 3, 'ArrowDown'), 2);
    // 6 (col 3) → linha curta do alto [9] → clampa para 9
    assert.equal(moveSelection(TIERS, 6, 'ArrowDown'), 9);
    // 9 → grupo seguinte [11]
    assert.equal(moveSelection(TIERS, 9, 'ArrowDown'), 11);
    assert.equal(moveSelection(TIERS, 11, 'ArrowUp'), 9);
    assert.equal(moveSelection(TIERS, 11, 'ArrowDown'), 11);
});

test('a selection outside the rows falls back to the first item', () => {
    assert.equal(moveSelection(TIERS, 7, 'ArrowDown'), 0);
});
