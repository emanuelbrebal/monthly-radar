#!/usr/bin/env node
// WCAG 2.x contrast check. Reads color tokens from resources/css/app.css (:root) and
// verifies the handoff styles.css carries the same values (handoff = single source).
// Exit 1 if any required pair fails or the two files drift.
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const APP = resolve(root, 'resources/css/app.css');
const HANDOFF = resolve(root, 'design/radar mensal/design_handoff_radar_mensal/styles.css');

function parseRoot(file) {
    const css = readFileSync(file, 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');
    const tokens = {};

    for (const block of css.matchAll(/:root\s*\{([^}]*)\}/g)) {
        for (const m of block[1].matchAll(/(--[\w-]+)\s*:\s*([^;]+);/g)) {
tokens[m[1]] = m[2].trim();
}
    }

    return tokens;
}

function resolveVar(tokens, name, seen = []) {
    let v = tokens[name];

    if (v === undefined) {
throw new Error(`token ausente: ${name}`);
}

    const m = v.match(/^var\((--[\w-]+)\)$/);

    if (m) {
        if (seen.includes(m[1])) {
throw new Error(`ciclo em ${name}`);
}

        return resolveVar(tokens, m[1], [...seen, name]);
    }

    return v;
}

const lum = (hex) => {
    const h = hex.replace('#', '');
    const [r, g, b] = [0, 2, 4].map((i) => parseInt(h.slice(i, i + 2), 16) / 255).map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};
const ratio = (a, b) => {
    const [l1, l2] = [lum(a), lum(b)].sort((x, y) => y - x);

    return (l1 + 0.05) / (l2 + 0.05);
};

const app = parseRoot(APP);
const handoff = parseRoot(HANDOFF);
const failures = [];

// 1) Drift: every hex token in the handoff must match app.css.
for (const [k, v] of Object.entries(handoff)) {
    if (/^#[0-9a-f]{6}$/i.test(v) && app[k] !== undefined && resolveVar(app, k).toLowerCase() !== v.toLowerCase()) {
        failures.push(`drift ${k}: handoff ${v} != app.css ${resolveVar(app, k)}`);
    }
}

// 2) Required pairs: [fg, bg, min, kind]. 4.5 = text, 3 = UI/large.
const TIERS = ['elite', 'alto', 'inter', 'entrada'];
const BADGES = ['pos', 'neu', 'neg', 'info'];
const pairs = [];
const P = (fg, bg, min, what) => pairs.push({ fg, bg, min, what });

for (const bg of ['--paper', '--paper-soft', '--paper-deep', '--card']) {
P('--ink', bg, 4.5, 'texto');
}

for (const bg of ['--paper', '--paper-soft', '--paper-deep', '--card']) {
P('--ink-mid', bg, 4.5, 'texto 2');
}

for (const bg of ['--paper-deep', '--card']) {
P('--ink-soft', bg, 4.5, 'texto 3');
}

P('--ink', '--highlight', 4.5, 'hoje no calendário');

for (const bg of ['--night', '--night-2', '--night-card', '--case-lining']) {
P('--night-text', bg, 4.5, 'texto maleta');
}

for (const bg of ['--night', '--night-2', '--night-card', '--case-lining']) {
P('--night-text-mid', bg, 4.5, 'texto 2 maleta');
}

for (const bg of ['--card', '--paper', '--paper-soft', '--paper-deep']) {
P('--line-strong', bg, 3, 'borda UI');
}

for (const bg of ['--case-lining', '--night', '--night-2', '--night-card']) {
P('--slot-border', bg, 3, 'borda slot');
}

P('--slot-selected', '--case-lining', 3, 'slot selecionado');

for (const bg of ['--card', '--paper']) {
P('--red-text', bg, 4.5, 'texto vermelho');
}

P('--card', '--red-text', 4.5, 'texto em botão vermelho');
P('--paper', '--red-text', 4.5, 'texto paper em botão vermelho');

for (const t of TIERS) {
    P(`--tier-${t}-fg`, `--tier-${t}-bg`, 4.5, `tier ${t} chip texto`);
    P(`--tier-${t}-line`, `--tier-${t}-bg`, 3, `tier ${t} borda/ícone`);

    for (const bg of ['--night', '--case-lining', '--night-card']) {
P(`--tier-${t}-on-night`, bg, 4.5, `tier ${t} na maleta`);
}
}

for (const b of BADGES) {
    P(`--badge-${b}-fg`, `--badge-${b}-bg`, 4.5, `badge ${b} texto`);
    P(`--badge-${b}-line`, `--badge-${b}-bg`, 3, `badge ${b} borda`);
}

for (const bg of ['--paper', '--card', '--paper-soft']) {
P('--focus', bg, 3, 'foco (claro)');
}

for (const bg of ['--night', '--case-lining', '--night-card']) {
P('--focus-on-night', bg, 3, 'foco (night)');
}

const rows = [];

for (const p of pairs) {
    let fg, bg, r;

    try {
        fg = resolveVar(app, p.fg);
        bg = resolveVar(app, p.bg);
        r = ratio(fg, bg);
    } catch (e) {
        failures.push(`${p.fg} / ${p.bg}: ${e.message}`);
        continue;
    }

    const ok = r >= p.min;
    rows.push({ ...p, fgv: fg, bgv: bg, r, ok });

    if (!ok) {
failures.push(`${p.fg} (${fg}) / ${p.bg} (${bg}) = ${r.toFixed(2)} < ${p.min} [${p.what}]`);
}
}

console.log('| par | uso | fg | bg | razão | mín | ok |');
console.log('|---|---|---|---|---|---|---|');

for (const x of rows) {
    console.log(`| \`${x.fg}\` / \`${x.bg}\` | ${x.what} | ${x.fgv} | ${x.bgv} | ${x.r.toFixed(2)} | ${x.min} | ${x.ok ? 'sim' : 'NÃO'} |`);
}

if (failures.length) {
    console.error(`\n${failures.length} falha(s):`);

    for (const f of failures) {
console.error(`- ${f}`);
}

    process.exit(1);
}

console.log(`\n${rows.length} pares OK.`);
