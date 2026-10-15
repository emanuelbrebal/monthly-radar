# Plano — Reformulação visual: contraste WCAG 2.x AA, tipografia e hierarquia (14/10/2026)

Branch: `feat/a11y-visual-redesign` (base `main` @ `fdf542d`). Tipo: feat (UI). **Nenhum código alterado nesta fase.**
Desvio da Fase 0.5: working tree tinha só untracked não relacionados (`storage/framework/lsp-*.php`, `tasks/`); não abortei, não toquei neles, branch criada sem checkout/pull.

## FASE 1 — Scope Analysis

**Objetivo**: toda a tela principal passa no WCAG 2.x **AA** e comunica de relance o que importa (o quê, quando, quão importante), com menos ruído visual.

**Decisões já tomadas (não reabrir)**: atualizar tokens no handoff `design/radar mensal/` (código só consome o handoff) · manter creme + maleta escura · AA · cortar fundo pontilhado, rótulos mono caixa-alta, filtros de status em linha, tier só por cor.

**Áreas afetadas**: `design/radar mensal/design_handoff_radar_mensal/styles.css`, `resources/css/app.css`, `resources/js/components/radar/*` (AttacheCase, CaseSlot, TierMark, ItemDescription, MonthCalendar, EventFolder, ItemBox, ItemRow, Tag, Stamp, SweepControl, StateFilterPopover, DossierModal), `resources/js/pages/radar/index.tsx`, `resources/js/types/radar.ts` (só metadados visuais de tier), `docs/03-ui-inventario.md` (seção Tokens).

**Escopo**: tokens e razões de contraste · tipografia · cartão da maleta · calendário · caixa de itens/filtros · badges/tier · foco visível · documentação dos tokens.

**Não-escopo**: lógica de domínio (resolver de status, pin, 24h), rotas, props Inertia, backend · tema escuro geral · mobile · Backlog v2 (itens de tamanhos diferentes na maleta etc.) · telas de auth/settings (shadcn) · novas fontes · mudar `--radius`.

**Critérios de aceite** (testáveis)
1. Tabela de contraste no handoff; **todo** par texto/fundo ≥ 4.5:1 (texto ≥ 18px ou 14px bold: ≥ 3:1), bordas/ícones/foco de UI ≥ 3:1. Verificado por script (`scripts/contrast-check.mjs`) que lê os tokens e falha com exit 1.
2. Corpo ≥ 14px; rótulos ≥ 12px peso ≥ 500; **zero** `text-[9px|10px|11px]` e zero `uppercase` + `tracking-[0.1+em]` em `resources/js/components/radar` e `pages/radar`.
3. Cartão da maleta: sigla em ≥ 18px, cor `ink`, dominante; nível = ícone + texto + cor; próximo evento secundário; slot vazio ≠ slot ocupado (borda tracejada + "vazio"/número do slot).
4. Calendário sem `rm-dot-grid`; 4 pesos distintos: hoje, evento de tier elite/alto, evento comum, dia fora do mês (legível, sem `opacity-40`).
5. Caixa de itens: 9 chips viram 1 controle compacto (multi-select em popover, mesma semântica de `Set<EditalStatusValue>`); badge de status com texto e AA.
6. Foco visível ≥ 3:1 em toda superfície (clara e escura); nenhuma informação só por cor.
7. `npm run build`, `npm run types:check`, `npm run lint:check`, `php artisan test` verdes; nenhum diff em `app/`, `routes/`, `tests/`.
8. Screenshots antes/depois (1280×800) em `tasks/screenshots/a11y/`.

**Perguntas bloqueadoras**: nenhuma. Premissas abaixo.

**Premissas** (`[inferido]` = ajustar se discordar)
- `--radius: 10px` fica. `CLAUDE.md` e `docs/03` ainda dizem "radius 0", mas `app.css` foi alterado a pedido do dono em 18/09 (memória do projeto). Não toco; sugiro corrigir o texto do `CLAUDE.md` à parte.
- `[inferido]` Mínimos 14px/12px; filtro de status vira popover multi-select no padrão de `StateFilterPopover`.
- `[inferido]` Tier ganha ícone por forma (●▲■◆ via SVG/lucide), não só texto, para distinguir sem cor.
- Handoff hoje está **defasado** de `app.css` (`--red #c8553d`, `--ink-soft #8a8175`); `app.css` já divergiu dele. A task 1 reconcilia: handoff vira fonte única.

## FASE 2 — Evidence Gathering

Medições (script `scratchpad/contrast.mjs`, fórmula WCAG relative luminance):

| Sintoma | Causa raiz (arquivo:linha) | Medido |
|---|---|---|
| Cabeçalho "MALETA · 5/12" ilegível | `.rm-eyebrow` define `color: var(--ink-mid)` **fora de `@layer`**; vence o utilitário `text-night-text-mid` (`app.css:181-187`, `AttacheCase.tsx:139`) | 2.52:1 |
| Nome do cartão invisível | `CaseSlot.tsx:90` usa `bg-card` mas herda `text-night-text` do `<section>` (`AttacheCase.tsx:134`); `rm-display` não define cor | 1.13:1 |
| Dia fora do mês | `opacity-40` (`MonthCalendar.tsx:196`) | 2.48:1 |
| Borda de slot vazio | `--slot-border` rgba .14 (`app.css:122`) | 1.46:1 |
| Slot selecionado | `border-slot-selected` = `--red` sobre forro (`CaseSlot.tsx:84`) | 2.78:1 |
| Tier na legenda da maleta | `bg-red`/`bg-blue` sobre night (`AttacheCase.tsx:158`, `types/radar.ts:38-41`) | 2.91 / 2.06 |
| Placeholder, divisórias | `ink/40`, `border-ink/10` (`ItemBox.tsx`, `ItemRow.tsx:42`) | 2.53 / 1.22 |
| Fontes minúsculas | 40 ocorrências de `text-[9|10|11px]` em 15 arquivos | — |
| Pontilhado | `rm-dot-grid` em `index.tsx:109` e `MonthCalendar.tsx:179` | ruído |
| Hierarquia plana | tudo `rm-mono` 10px, mesma borda `border-ink`, tier = quadradinho 8px (`TierMark.tsx:25`) | — |

Fluxo atual: tokens `:root` em `app.css` → `@theme` → classes Tailwind; helpers `.rm-*` em CSS solto (sem layer) → ganham de utilitários. Chips do calendário usam hex hardcoded (`EventFolder.tsx:4-16`) duplicando `.rm-badge`.

## FASE 3 — Architecture Planning (ToT)

| | A. Retokenizar + remendar componentes | **B. Camada de superfície + retokenizar + componentes** | C. Reescrever os componentes visuais |
|---|---|---|---|
| Corretude | Média: o bug de herança volta | **Alta: elimina a classe do bug** | Alta |
| Blast radius | Baixo | **Médio-baixo (só CSS/classes)** | Alto (risco de regressão em pin/drag/teclado) |
| Manutenção | Média | **Baixa** | Baixa |
| Alinhamento | Alto | **Alto** | Baixo |

**Vence B.** Duas causas estruturais: (1) helpers fora de layer sobrescrevem utilitários; (2) cor de texto herdada entre superfícies. Correção: helpers `.rm-*` em `@layer components`; classes `.surface-paper` / `.surface-card` / `.surface-night` que definem `background`, `color` **e** `--muted-fg`, `--line` próprios; todo cartão/painel declara sua superfície. Descartei A (conserta sintomas) e C (reescrita sem ganho de acessibilidade, arrisca drag/pin/atalhos).

**Paleta alvo** (todos calculados; AA salvo indicação):

| Token | Valor | Par | Razão |
|---|---|---|---|
| `--ink` | `#1a1612` | paper | 15.17 |
| `--ink-mid` | `#4a443c` | paper / paper-deep | 8.11 / 6.68 |
| `--ink-soft` | `#5c554b` | paper-deep / card | 5.11 / 6.81 |
| `--night-text` | `#f5eedb` | night | 16.25 |
| `--night-text-mid` | `#d2c9b0` | night / forro | 11.40 / 10.88 |
| `--line-strong` (bordas UI) | `#8a8070` | card / paper | 3.60 / 3.28 |
| `--slot-border` (maleta) | `#7d735f` | forro / night | 3.84 / 4.02 |
| `--slot-selected` | `--highlight #f0d96b` | forro | 12.70 |
| `--red-text` / botão | `#8a3526` | card / paper | 7.43 / 6.76 |
| tier elite · alto · inter · entrada (**no claro**) | `#a3392a` `#8a6a00` `#2f5f9a` `#3f7a36` (borda/ícone) | chip bg | 4.96 · 4.08 · 5.05 · 4.16 |
| tier (**no night**) | `#ff9a86` `#f0c15a` `#8fbcf0` `#8fd08a` | night | 9.16 · 11.19 · 9.52 · 10.36 |
| tier chip texto/fundo | `#7a2a1d/#f6d9d0` `#5a4300/#f6e6b4` `#1d3c63/#d6e4f5` `#22501c/#d8ecd0` | — | 7.24 · 7.55 · 8.66 · 7.54 |
| badges pos/neu/neg/info | `#1f4a19` `#5a4300` `#7a2a1d` `#1d3c63` sobre os fundos acima | — | 8.20 · 7.55 · 7.24 · 8.66 |
| foco | `#1d3c63` (claro) · `#f0d96b` (night) | paper / night | 9.42 / 13.30 |

Validações: regra de negócio fica no backend ✔ · sem acoplamento novo ✔ · reversível (só CSS/classes; `git revert`) ✔ · contratos Inertia intactos ✔.

## FASE 4 — Execution Planning (checklist, ordem de dependência)

Cada task = 1 commit (`feat(ui): …`, inglês). Rodar `npm run build && npm run types:check` ao fim de cada uma.

- [ ] **T1 · Tokens + script de contraste** — Arquivos: `design/…/styles.css`, `resources/css/app.css`, `scripts/contrast-check.mjs`, `docs/03-ui-inventario.md` (Tokens). Mudança: reconciliar handoff com `app.css`; aplicar paleta da Fase 3; criar pares explícitos (`--tier-*-fg|bg|line`, `--tier-*-on-night`, `--line-strong`, `--focus`); mover `.rm-*` para `@layer components`; criar `.surface-paper|card|night`; remover `.rm-dot-grid`; `.rm-eyebrow` → 12px/600, sans, sentence case, tracking ≤ 0.02em; `.rm-badge` → 12px/600 sans, sem uppercase; anel de foco global (`:focus-visible`, 2px, 2px offset). Verificação: `node scripts/contrast-check.mjs` exit 0 e imprime a tabela; colar a tabela no README do handoff.
- [ ] **T2 · Escala tipográfica** — `app.css` (`--text-label 12px`, `--text-body 14px`, `--text-title 18px`, `--text-display`), utilitários `.t-label|.t-body|.t-title`. Verificação: critério 2 por `grep` (ver T10).
- [ ] **T3 · Tier acessível** — `types/radar.ts` (adicionar `icon`, `fgClass`, `chipClass`), `TierMark.tsx` (variants `dot|chip`, ícone por forma + texto, par claro/night via prop `on`). Verificação: tier legível sem cor (screenshot em escala de cinza).
- [ ] **T4 · Cartão da maleta + slot** — `CaseSlot.tsx`, `AttacheCase.tsx`, `ItemDescription.tsx`. Cartão: `surface-card`, sigla 18px+/`ink`, `TierMark chip`, evento em 12px; remover alfinete/sombra decorativa se competirem; vazio: borda tracejada `--slot-border` + nº do slot; selecionado: anel `--highlight` 3px; cabeçalho/legenda/ajuda em `night-text(-mid)` ≥ 12px; legenda usa tiers `on-night`. Verificação: nome visível no screenshot; razões da maleta no script.
- [ ] **T5 · Calendário** — `MonthCalendar.tsx`, `EventFolder.tsx`. Sem pontilhado; célula `--line-strong`; cabeçalho de dias 12px/600; número do dia 14px; hoje = fundo `highlight` + borda `ink` + "hoje" sr-only; fora do mês = `ink-soft` sem opacity; chips ≥ 12px usando `.rm-badge` (remover hex duplicado); evento de tier elite/alto com borda 2px + ícone; "+N" com área de clique ≥ 24px. Verificação: 4 pesos distintos no screenshot.
- [ ] **T6 · Caixa de itens + filtros** — `ItemBox.tsx`, `ItemRow.tsx`, novo `StatusFilterPopover.tsx` (padrão de `StateFilterPopover`). Trocar os 9 chips por 1 popover com contagem ("status · 3"); busca e "só inscritos" em 14px com rótulo visível; placeholder ≥ 4.5; divisórias `--line-strong`; linha: sigla 16px+ semibold, tier chip, status badge, data em 14px; pin com `aria-label`. Verificação: `statusFilter` mantém semântica (`Set`), filtragem igual.
- [ ] **T7 · Masthead + página** — `pages/radar/index.tsx`, `SweepControl.tsx`, `StateFilterPopover.tsx`. Remover `rm-dot-grid` e `rm-rule-double` decorativo; botão "varrer" `--red-text` com texto `card` (7.43); selects 14px; status da varredura 14px/`ink-mid`; um único foco primário (maleta + calendário), caixa de itens em peso secundário. Verificação: screenshot.
- [ ] **T8 · Dossiê, toasts, stamp, checklist** — `DossierModal.tsx`, `EnrollmentChecklist.tsx`, `SourceList.tsx`, `ErrorToast.tsx`, `Stamp.tsx`, toast em `index.tsx:125`. Aplicar escala, `surface-*`, fim de `text-[9–11px]` e `opacity` em texto; `Stamp` com 12px e texto vermelho AA. Verificação: abrir dossiê e checar manualmente.
- [ ] **T9 · Auditoria de resíduos** — `grep` em `components/radar` e `pages/radar`: `text-\[(9|10|11)px\]`, `opacity-` em texto, `/10|/20|/30|/40` em cor de texto/borda, `uppercase`, `rm-dot-grid`, hex hardcoded. Corrigir sobras. Verificação: 0 ocorrências.
- [ ] **T10 · Verificação final** — `node scripts/contrast-check.mjs`, `npm run lint:check`, `npm run types:check`, `npm run build`, `php artisan test`; `git diff --stat main -- app routes tests` vazio; screenshots antes/depois em `tasks/screenshots/a11y/` (Chrome 1280×800, por `/run`); navegação só por teclado (Tab/Enter/setas/P) com foco sempre visível. Atualizar o `CLAUDE.md` só se o dono aprovar (radius).

## FASE 5 — Verificação do Plano

- [x] Nenhuma regra de negócio na camada errada (só CSS/classes/TSX visual)
- [x] Sem acoplamento novo (tokens → superfícies → componentes)
- [x] Critérios objetivos e testáveis (script + grep + build + testes)
- [x] Checklist ≤ 10 itens
- [x] 3 abordagens avaliadas, B escolhida com justificativa
- [x] Evidências ≤ 7 arquivos lidos (`app.css`, `styles.css`, `CaseSlot`, `MonthCalendar`, `AttacheCase`, `ItemBox`, `EventFolder`; + `TierMark`, `ItemRow`, `index.tsx`, `types/radar.ts` por trechos)
- [x] Sem perguntas bloqueadoras; premissas explícitas

**Gate = Livre para execução.** Próximo passo: `/exec` (começa pela T1).
