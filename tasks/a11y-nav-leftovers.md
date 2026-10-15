# Plano — Pendências de ordenação, erro de mês, teclado e landmarks (15/10/2026)

Branch: `feat/a11y-visual-redesign` (indicada pelo usuário; sem checkout). Árvore só com não rastreados (`storage/framework/lsp-*.php`, `tasks/local-dod-audit.md`) — não tocar. Tipo: fix/refactor (UI, sem backend, sem migration). Origem: `/code-review` de `tasks/calendar-case-phase-polish.md`; os 5 itens já existiam antes daquele trabalho.

## FASE 1 — Scope Analysis

**Objetivo**: corrigir 5 pendências pré-existentes de UI (ordem da caixa de itens, erro silencioso do calendário, setas na vista "por nível", landmarks/foco da maleta, campo `color` morto) sem mudar o visual.

**Áreas afetadas**: `resources/js/lib/radar.ts`, novo `resources/js/lib/case-nav.ts`, `ItemBox.tsx`, `MonthCalendar.tsx`, `AttacheCase.tsx`, `pages/radar/index.tsx`, `types/radar.ts`, `package.json` (1 script), `docs/03-ui-inventario.md`.

**Triagem dos 5 itens (confirmados lendo o código)**

| # | Veredito | Causa real | Vale agora? |
|---|---|---|---|
| 1 | **Confirmado** | `ContestPresenter` já ordena `events` por data asc; `ItemBox.tsx:~108` ordena por `events[0]` (o evento **mais antigo**, possivelmente passado) enquanto `nextEventLabel` mostra o primeiro evento ≥ hoje | Sim |
| 2 | **Confirmado, reformulado** | `goToMonth` só faz toast no erro; `fetchedContests` guarda **um** mês sem a chave do mês, então após falha o mês novo fica vazio (e com dados do mês anterior "pendurados") sem aviso na tela. O "voltar mostra em branco" do review é falso: voltar refaz o fetch | Sim |
| 3 | **Confirmado** | `AttacheCase.tsx:~143`: ↑/↓ somam ±4 sobre `navOrder` (só ocupados); na vista "por nível" cada grupo tem grade própria de 4 colunas com linhas incompletas | Sim |
| 4 | **Confirmado + agravante** | `<section aria-label="Maleta">` (`pages/radar/index.tsx:107`) envolve `<section tabIndex=0>` sem nome (`AttacheCase.tsx:~164`), e a classe `outline-none` apaga o indicador de foco | Sim |
| 5 | **Confirmado, reformulado** | `CONTEST_TIERS[].color` só é lido em `buildTierGroups` (`AttacheCase.tsx:~324`) para preencher `TierGroup.color`, que **ninguém lê**. A faixa do slot não usa mais `color` (o review errou aqui). É campo morto | Sim (remoção trivial) |
| 6 | **Achado novo** (fora da lista; verificar no exec) | `CalendarController` carrega `events` **só do mês**; `openDossier(contest)` guarda o DTO vindo do calendário, então a linha do tempo do dossiê pode mostrar só os eventos daquele mês | Sim, se confirmado (1 linha) |

**Escopo**: itens 1–5 e, se confirmado, o 6.

**Não-escopo**: backend/migration; nova biblioteca de teste (vitest/jest); mudar fluxo de pin/descarte, regra das 24h; redesenhar a maleta; `parseMonth` e outras otimizações (já descartadas no simplify).

**Impacto técnico**: dados nenhum; API nenhuma; UI 5 componentes; infra 1 script npm.

**Critérios de aceite** (testáveis)
1. Dado contestos com primeiro evento passado e próximo evento futuro, a caixa de itens ordena pelo **próximo evento exibido** (futuros asc, depois só-passados pelo mais recente primeiro, depois sem data). Teste automatizado da função de ordenação.
2. Falha no fetch de um mês mostra `role="alert"` com texto e botão "tentar de novo" dentro do calendário; sucesso limpa o erro; dados de um mês nunca aparecem em outro.
3. Na vista "por nível": ↓ vai para o item na mesma coluna da próxima linha (cruzando grupos, ajustando à última coluna existente), ↑ o inverso; sem linha acima/abaixo, a seleção não muda. ←/→ seguem a ordem visual. Teste automatizado da função pura de navegação. Na grade 4×3 o resultado é igual ao atual, exceto ↓/↑ na última/primeira linha, que deixam de "saltar" para a ponta (ver Decisão 2).
4. Existe uma única região com nome "Maleta" (sem `section` aninhada duplicada); o elemento focável tem nome acessível, descrição das teclas (`aria-describedby`) e indicador de foco visível (≥ 3:1 sobre o fundo night).
5. `CONTEST_TIERS` e `TierGroup` sem `color`; `grep -rn "\.color" resources/js` sem usos de tier; nenhuma mudança visual (captura antes/depois idêntica).
6. (item 6) Dossiê aberto pelo calendário mostra todos os eventos do concurso.
7. `php artisan test`, `pint --test`, `phpstan`, `eslint`, `tsc` (exceto `RedirectController.ts` gerado, preexistente), `prettier --check` nos arquivos tocados e o novo `npm run test:js` verdes; verificação no navegador dos itens 2–4 e 6.

**Perguntas bloqueadoras**: nenhuma.

**Premissas**
- Node 22.19 (instalado) roda `.ts` com `node --test` sem flag (type stripping) e `@types/node` já existe; os módulos testados só importam **tipos** (`import type`), então não dependem do alias `@/`. **Confiança: MÉDIA** — validar no passo 2; se falhar, fallback: extrair a lógica e validar no navegador, documentando o desvio.
- O hook de seleção na maleta usa o índice do **slot** (0–11), não a posição na tela.

## FASE 2 — Evidence Gathering

| # | Evidência | Âncora | Achado |
|---|---|---|---|
| 1 | Sort da caixa | `ItemBox.tsx:~108-112` | `a.events[0]?.date ?? '9999-99-99'` |
| 2 | Eventos ordenados | `ContestPresenter.php` (`sortBy('date')`) | `events[0]` = mais antigo, não o próximo |
| 3 | Rótulo do próximo | `lib/radar.ts` (`nextEventLabel`) | primeiro `date >= todayIso()` ou o último |
| 4 | Fetch do mês | `MonthCalendar.tsx` (`goToMonth`, `fetchedContests`) | um único estado sem chave de mês; `.catch` só `notifyError` |
| 5 | Dados do mês | `CalendarController.php:~34-40` | `with(['events' => whereBetween(mês)])` ⇒ DTO do calendário tem eventos parciais |
| 6 | Navegação | `AttacheCase.tsx:~76-79,141-160` | `navOrder` achatado; `arrows = {ArrowDown: 4, ArrowUp: -4}` |
| 7 | Landmarks/foco | `pages/radar/index.tsx:107`, `AttacheCase.tsx:~164` | `section aria-label` > `section tabIndex=0 outline-none` |
| 8 | `color` morto | `types/radar.ts:86,110-146`, `AttacheCase.tsx:307,324,330` | só atribuído, nunca lido |
| 9 | Testes JS | `package.json` | sem runner de teste JS |

**Fluxo atual**: `RadarPageController` → página `radar/index` → `ItemBox`/`MonthCalendar`/`AttacheCase`; clique no calendário chama `openDossier(contest)` com o DTO do calendário.

**Incertezas**: (a) item 6 depende de `openDossier` não reidratar o DTO (parece que não; confirmar no exec); (b) Node + `@types/node` aceitam o teste sem configuração extra (premissa acima).

## FASE 3 — Architecture Planning

### Decisão 1 — Ordenação e testabilidade (item 1)

**3A**
- A) Helper TS `nextEvent(contest, today)` em `lib/radar.ts`, usado pelo rótulo **e** pelo comparador; teste com `node --test`.
- B) Backend emite `next_event` (PHP + Pest) e o front só consome.
- C) Adicionar vitest só para isso.

**3B** (1–5)

| Critério | A | B | C |
|---|---|---|---|
| Corretude | 5 | 3 (o DTO do calendário tem eventos parciais ⇒ `next_event` errado nele) | 5 |
| Blast radius | 5 | 3 (muda payload em 6 endpoints) | 3 (nova dependência) |
| Manutenção | 5 | 4 | 3 |
| Alinhamento (sem stack nova; mecânico testado) | 4 | 4 | 1 |
| **Total** | **19** | 14 | 12 |

**3C — Vencedora: A.** Uma fonte única de "próximo evento" no front (rótulo + ordem) e `todayIso()` local já existente. Ordem: (1) tem evento ≥ hoje → por data asc; (2) só passados → depois, pelo mais recente primeiro; (3) sem eventos → fim. Descartado B (payload parcial do calendário) e C (stack nova). Reversível.

### Decisão 2 — Setas na vista "por nível" (item 3)

**3A**
- A) Função pura `moveSelection(rows, selected, key)` em `lib/case-nav.ts`; `rows` = linhas visuais (grupos em blocos de 4; na grade, 3 linhas de 4 slots). ←/→ andam pela lista achatada; ↑/↓ mudam de linha mantendo a coluna (clamp na última coluna da linha).
- B) Manter ±4 e só documentar a limitação.
- C) Roving tabindex com navegação nativa por foco em cada slot.

**3B**

| Critério | A | B | C |
|---|---|---|---|
| Corretude | 5 | 2 | 4 |
| Blast radius | 4 | 5 | 2 (muda modelo de foco/drag) |
| Manutenção | 4 | 3 | 3 |
| Alinhamento | 5 | 2 | 3 |
| **Total** | **18** | 12 | 12 |

**3C — Vencedora: A.** Uma mudança aceita e documentada: na grade, ↓ na última linha (e ↑ na primeira) **não muda** a seleção, em vez de saltar para a ponta (comportamento atual, que não é intencional). Descartado B (deixa o bug) e C (refaz foco/drag).

### Decisão 3 — Landmark e foco da maleta (item 4)

**3A**
- A) O elemento focável vira a **única** região: `aria-label="Maleta"` + `aria-describedby` (dica das teclas, `sr-only`) em `AttacheCase`; o wrapper da página vira `<div>` (só layout). Trocar `outline-none` por `focus-visible:outline-2 outline-focus-on-night`.
- B) Só remover o `aria-label` do wrapper (deixa o focável sem nome).
- C) `role="application"` no focável.

**3B**

| Critério | A | B | C |
|---|---|---|---|
| Corretude (WCAG 2.4.7, 4.1.2, 1.3.1) | 5 | 3 | 3 (desliga atalhos do leitor de tela) |
| Blast radius | 5 | 5 | 3 |
| Manutenção | 5 | 4 | 3 |
| Alinhamento (token `--focus-on-night` existe) | 5 | 3 | 2 |
| **Total** | **20** | 15 | 11 |

**3C — Vencedora: A.** Reversível (classes e atributos).

### Decisão 3 — Erro e dados do mês (item 2)

Abordagem única coerente: guardar `{ month, contests }` do último fetch (dados só valem para o seu mês) e um estado `loadError`; renderizar `role="alert"` com "Não deu pra carregar esse mês." e botão **tentar de novo** que chama `goToMonth(month)` de novo; a grade permanece visível. Descartado: cache por mês (os DTOs ficariam velhos após pin/descarte; hoje cada navegação refaz o fetch de propósito).

### Itens sem decisão
- **5**: remover `color` de `TierMeta`, de `CONTEST_TIERS` e de `TierGroup`/`buildTierGroups`.
- **6**: `openDossier` resolve o DTO completo em `contests` por `id` antes de `setSelected` (fallback ao recebido).

**Validações obrigatórias**: lógica mecânica em funções puras testadas (ordem, navegação) ✔; sem acoplamento novo (dois módulos puros em `lib/`) ✔; reversível ✔; contratos de API inalterados ✔.

## FASE 4 — Execution Planning

1. **Baseline**: rodar os gates e anotar preexistentes (`tsc` em `RedirectController.ts`; `prettier` em `csrf-fetch.ts`).
2. **Teste JS + ordenação (item 1)**: validar `node --test` com `.ts`; `nextEvent`, `compareByNextEvent` em `lib/radar.ts`; refatorar `nextEventLabel`; `resources/js/lib/radar.test.ts` (primeiro evento passado, só passados, sem eventos, empate); script `"test:js": "node --test resources/js/lib/*.test.ts"`.
3. **`ItemBox`**: usar `compareByNextEvent` no sort.
4. **Item 5**: remover `color` (types, `AttacheCase`), conferir com `grep`; captura antes/depois do slot.
5. **Item 3**: `lib/case-nav.ts` (`buildRows`, `moveSelection`) + `case-nav.test.ts` (grade, grupos incompletos, extremos, clamp de coluna); ligar em `AttacheCase.handleKeyDown`.
6. **Item 4**: `AttacheCase` (`aria-label`, `aria-describedby`, foco visível) e `pages/radar/index.tsx` (wrapper `div`).
7. **Item 2**: `MonthCalendar` (`{month, contests}`, `loadError`, alerta + "tentar de novo").
8. **Item 6**: confirmar o bug no navegador (clique no calendário em mês com evento fora do mês) e, se real, reidratar em `openDossier`.
9. **Verificação no navegador** (SQLite temporário + usuário de teste, como na execução anterior): setas na vista "por nível", foco visível, árvore de acessibilidade (uma região "Maleta"), falha simulada do fetch (`/calendar` indisponível via bloqueio de rede ou param inválido), dossiê pelo calendário.
10. **Docs e gates**: nota em `docs/03-ui-inventario.md` (teclas, ordem da caixa, erro do calendário); gates completos; stages de execução neste arquivo.

## FASE 5 — Verificação do Plano

- [x] Nenhuma regra de negócio na camada errada (só UI e funções puras; backend intocado)
- [x] Sem acoplamento desnecessário
- [x] Critérios de aceite objetivos e testáveis
- [x] Checklist ≤ 10 itens (10)
- [x] ≤ 3 abordagens por decisão, 1 escolhida com justificativa (3 blocos de decisão)
- [x] Evidências ≤ 7 arquivos-núcleo (9 linhas, 6 arquivos-fonte + `package.json`/`CalendarController`)
- [x] Perguntas bloqueadoras: nenhuma; incertezas (a) e (b) marcadas **MÉDIA**

**Riscos**
- `node --test` com `.ts` pode não rodar no ambiente (fallback descrito na Premissa).
- A mudança em ↓/↑ nas pontas da grade é intencional e precisa constar no changelog/docs.

**Passos de revisão após o `/exec`**: `/simplify` e `/code-review` no diff; reexecutar gates e a verificação no navegador.

**Gate = Livre para execução.**

## Stage de Execução — Entregáveis

| # | Descrição | Caminho | Commit | Status |
|---|---|---|---|---|
| 1 | Baseline (Pest 200/202, pint/phpstan/eslint ok, `tsc` só em arquivo gerado) | — | — | ✅ |
| 2 | `nextEvent`, `compareByNextEvent`, `nextEventLabel` refatorado | `resources/js/lib/radar.ts` | `35c3b41` | ✅ |
| 2 | Testes `node --test` da ordenação (4 casos) + script `test:js` | `resources/js/lib/radar.test.ts`, `package.json` | `35c3b41` | ✅ |
| 2 | `allowImportingTsExtensions` (necessário para o teste importar `./x.ts`) | `tsconfig.json` | `35c3b41` | ✅ (desvio) |
| 3 | `ItemBox` ordena por `compareByNextEvent` | `components/radar/ItemBox.tsx` | `35c3b41` | ✅ |
| 4 | Remove `color` legado (`TierMeta`, `CONTEST_TIERS`, `TierGroup`) | `types/radar.ts`, `AttacheCase.tsx` | `89916e1` | ✅ |
| 5 | Navegação por linhas visuais (`buildRows`, `moveSelection`) + 5 testes | `lib/case-nav.ts`, `lib/case-nav.test.ts` | `381f6e7` | ✅ |
| 5 | Ligação em `handleKeyDown` | `AttacheCase.tsx` | `381f6e7` | ✅ |
| 6 | Região única "Maleta", `aria-describedby`, anel de foco | `AttacheCase.tsx`, `pages/radar/index.tsx` | `381f6e7`, `a64c78e` | ✅ |
| 7 | `{month, contests}` + `loadError` + alerta "tentar de novo" | `MonthCalendar.tsx` | commit "show retryable error…" | ✅ |
| 8 | Bug 6 confirmado e corrigido: `openDossier` usa o DTO completo da mesa | `pages/radar/index.tsx` | commit "open dossier with the full contest…" | ✅ |
| 10 | Docs (teclas, ordem, erro do mês, região da maleta) | `docs/03-ui-inventario.md` | este commit | ✅ |

**Desvios**
1. `tsconfig.json` ganhou `allowImportingTsExtensions` (já havia `noEmit`): sem isso o `tsc` rejeita `import './radar.ts'` nos testes.
2. O commit `381f6e7` ("row-based arrow navigation") também contém as mudanças de `aria-label`/foco do `AttacheCase.tsx` (mesmo arquivo); o `a64c78e` traz só o wrapper da página.
3. Item 8 (bug 6) era condicional; foi confirmado no navegador (2 de 8 eventos no dossiê aberto de novembro) e corrigido.
4. Na verificação, `public/hot` (arquivo local do Vite, ignorado pelo git) apontava para um servidor de desenvolvimento que não estava rodando e deixava a página em branco; foi movido de lado só durante o teste e **restaurado**.
5. Comportamento intencional mudado: na grade 4×3, ↓/↑ na última/primeira linha agora não mudam a seleção (antes saltavam para a ponta).

## Stage de Execução — Validação

| Critério de aceite | Resultado | Observação |
|---|---|---|
| 1. Ordem da caixa = próximo evento exibido; teste com 1º evento passado | ✅ | `node --test`: 4 casos. No navegador: 15/10, 16/10, 16/10, 21/10, 01/12, 13/12, depois sem data (antes SEMA-MT, de evento mais antigo, vinha antes de SEFAZ-AL) |
| 2. Erro de mês com "tentar de novo"; sem dados de um mês em outro | ✅ | `fetch` de `/calendar` forçado a falhar: `role=alert` aparece, grade sem eventos; após restaurar e clicar em "tentar de novo", alerta some e novembro mostra 2 eventos |
| 3. Setas na vista "por nível" por linha; ←/→ ordem visual; teste puro | ✅ | 5 testes; no navegador CGU →BACEN ↓ANPD ↑CGU ↑(topo) CGU. Grade: coberta por teste (não repetida no navegador) |
| 4. Região única "Maleta", nome, descrição das teclas, foco visível | ✅ | Regiões: Maleta, Calendário do mês, Caixa de itens (sem aninhamento); `matches(':focus-visible')` true com outline 2px `rgb(240,217,107)` sobre a maleta |
| 5. `color` removido, sem mudança visual | ✅ | `grep` sem usos; capturas da maleta idênticas às anteriores a olho nu (não comparei pixel a pixel) |
| 6. (item 6) Dossiê pelo calendário mostra todos os eventos | ✅ | CGU: 8 eventos abrindo do mês de novembro (antes 2) |
| 7. Gates | ✅/⚠️ | pest 200/202 (2 skipped), pint ✅, phpstan ✅, eslint ✅, `test:js` 9/9 ✅, `tsc` só `RedirectController.ts` (gerado, preexistente), prettier só avisa `csrf-fetch.ts` (preexistente, não tocado) |

**Verificação em SQLite temporário** (`scratchpad/verify.sqlite`, usuário de teste), sem tocar no Postgres. 1366×768 exato não aplicado pelo navegador (viewport medido 1920×911 e ~744px de altura em capturas anteriores); este plano não mexeu em layout de altura.

**Revisão pós-exec (pendente)**: `/simplify` e `/code-review` no diff desta branch.

## Stage de Execução — Revisão pós-exec

**`/simplify` (4 agentes)** — aplicado: `CalendarController` envia a lista completa de eventos (causa raiz do dossiê parcial; `openDossier` voltou a `setSelected(contest)`; teste novo em `CalendarControllerTest`); `CASE_COLUMNS` único para setas e layout (`grid-template-columns` por `style`); `isNavKey`/`NAV_KEYS` sem casts; `nextEvent` sem genérico e com `today` obrigatório; `MonthCalendar` usa `todayIso()` de `lib/radar`. Ignorado: componente de retry compartilhado com `ItemBox` (layouts diferentes), micro-otimizações, `order.keys()`, simplificação do comparador. Efeito esperado: dias de meses vizinhos na grade passam a mostrar seus eventos nos meses navegados, como já ocorria no mês atual.

**`/code-review` (5 achados)** — corrigido: `today` do `ItemBox` fora do `useMemo` (reordena na virada do dia); `engines.node >=22.18` em `package.json` (`node --test` com `.ts`). Falsos positivos: ordem dos eventos (o `ContestPresenter` já usa `sortBy('date')`) e ordem dos grupos nas setas (`buildTierGroups` já filtra os vazios e é a mesma fonte da renderização). Mantido: grade vazia com alerta quando o mês falha (o alerta é a sinalização).
