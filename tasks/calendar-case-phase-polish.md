# Plano — Calendário, cartões e fase atual na maleta (15/10/2026)

Branch: `feat/a11y-visual-redesign` (indicada pelo usuário; mantida, sem checkout para `main`). Tipo: feat (UI + 1 enum/serviço de domínio). Working tree só com não rastreados (`storage/framework/lsp-*.php`, `tasks/local-dod-audit.md`) — não tocar neles.

## FASE 1 — Scope Analysis

**Objetivo**: calendário com ano arábico e cards curtos; cartões mais informativos e com menos links; maleta sem corte inferior; títulos legíveis; fase atual de cada concurso em destaque no slot da maleta.

**Áreas afetadas**: `MonthCalendar.tsx`, `EventFolder.tsx`, `CaseSlot.tsx`, `AttacheCase.tsx`, `ItemDescription.tsx`, `DossierModal.tsx`, `SourceList.tsx`, `Tag.tsx`, `ItemRow.tsx`, `resources/js/types/radar.ts`, `resources/css/app.css` (só uso de tokens), `ContestPresenter.php`, novo `app/Enums/ContestPhase.php`, novo `app/Services/Radar/ContestPhaseResolver.php`, docs `03-ui-inventario.md`.

**Escopo**
1. Ano arábico no título do calendário (`OUTUBRO · 2026`).
2. Cartões: dados relevantes no lugar de links; só edital + inscrição como links.
3. Card do calendário = só título do evento; clique abre o dossiê.
4. Maleta: layout sem corte em 1366×768 e 1920×1080.
5. Hierarquia tipográfica dos títulos dos cartões.
6. Fase atual derivada, em destaque no slot e no painel da maleta.
7. Tag "novo" em concursos recém-inseridos (flag derivada `is_new`, sem coluna) e default explícito: concurso sem interação do usuário fica `not_enrolled` e mostra a fase do edital.

**Não-escopo**: coluna/migration nova; mudar pin/descarte/regra das 24h; scraping; Backlog v2 (notificações, .ics, itens de tamanhos diferentes); mudar fluxo do `EnrollmentChecklist`; tema escuro/aparência.

**Impacto técnico**
- Dados: nenhum (sem migration). 4 campos novos só no payload: `phase`, `phase_label`, `phase_tone`, `is_new`.
- API/Inertia: `ContestPresenter::present()` ganha 4 chaves (aditivo, não quebra contrato). Afeta `RadarPageController`, `CalendarController`, `PinController`.
- UI: 8 componentes React. Infra: nada.

**Critérios de aceite** (testáveis)
1. `h2` do calendário mostra `{mês} · {ano}` com 4 dígitos; ano do mês atual = `new Date().getFullYear()`; navegar ←/→ muda o ano na virada (dez→jan).
2. Em `ItemDescription` e no resumo do dossiê há no máximo 2 links externos (edital, inscrição); `SourceList` fica recolhida por padrão.
3. `EventFolder` renderiza só `{sigla} · {event.label ?? event.type_label}`; clique chama `onSelectContest` e abre o `DossierModal` do concurso certo.
4. Maleta sem texto/botão cortado em 1366×768 e 1920×1080 (verificado no navegador, grade e "por nível").
5. `ContestPhaseResolver` é PHP puro, sem migration, com teste Pest em tabela cobrindo todas as fases.
6. Título (sigla) do slot e do painel não sobrepõe nem corta o tier/dot; truncamento só com `title` completo.
7. `php artisan test`, `pint --test`, `phpstan`, `eslint`, `tsc`, `prettier --check` verdes; falhas preexistentes reportadas (ver `tasks/local-dod-audit.md` achado 7: `tsc` falha em `resources/js/actions/.../RedirectController.ts`, arquivo gerado).
8. Plano termina com passos de revisão (Fase 5).
9. `is_new` = `created_at` nos últimos 7 dias **e** `enrollment_status = not_enrolled` **e** `pinned_slot` nulo **e** não descartado. Teste Pest em tabela (6 dias, 8 dias, interessado, fixado, descartado). Tag "novo" aparece na caixa de itens, no slot e no dossiê, com texto (não só cor).
10. Concurso criado por seed/varredura sem `enrollment_status` informado nasce `not_enrolled` (já é o default da migration; teste de regressão no `ContestSeeder`/merger).

**Perguntas bloqueadoras**: nenhuma. Respondidas pelo usuário: fase = colunas existentes + enum derivado se faltar; clique do card abre dossiê; cartão mantém só edital + inscrição; ano arábico com navegação.

**Premissas**
- "Cartões" = `ItemDescription`/`CaseSlot` (maleta), `ItemRow` (caixa) e resumo do dossiê. **Confiança: MÉDIA** — hoje esses cartões quase não têm links; quem concentra links é `SourceList` no dossiê (`DossierModal.tsx:175`). Se você via outro cartão, ajustar no exec.
- Servidor e navegador rodam no mesmo PC (app local), então `Carbon::now()` do `RadarPageController.php:34` coincide com o relógio do PC. O mês inicial continua vindo do servidor.
- `ContestEvent.label` é texto livre em pt-BR do seed ("Resultado do gabarito preliminar"); em caixa alta só via CSS (`uppercase`), não no dado.

## FASE 2 — Evidence Gathering

| # | Evidência | Âncora | Achado |
|---|---|---|---|
| 1 | Ano em romano | `MonthCalendar.tsx:39-51,165` | `toRoman(year)` + tabela `ROMAN_TABLE`; remover ambos |
| 2 | Card do calendário | `EventFolder.tsx:37-40` | Mostra `sigla · type_label` (não o `label` do evento), com dots de tier/pin; `title` repete o texto |
| 3 | Clique já abre dossiê | `MonthCalendar.tsx:213` + `pages/radar/index.tsx:113` | `onSelectContest={openDossier}` já ligado; falta só trocar o texto |
| 4 | Layout da Mesa | `pages/radar/index.tsx:95,106-108` | `h-screen overflow-hidden`; coluna mínima 420px (5fr) ⇒ slots `aspect-square` de ~90px, 3 linhas |
| 5 | Corte da maleta | `AttacheCase.tsx:131,196-221,248` | `<section h-full flex-col>` com grade fixa (sem scroll) + `ItemDescription` no fim; sem `overflow-y-auto`/`shrink-0`, o rodapé (botões "abrir dossiê"/"desafixar") é cortado pelo `overflow-hidden` do pai. Só a vista "por nível" rola |
| 6 | Botões do painel | `ItemDescription.tsx:67-80` | `flex gap-2` sem `flex-wrap`; no 420px o texto pode estourar |
| 7 | Fase hoje | `CaseSlot.tsx:36-49,72-80`; `ItemDescription.tsx:56-58` | Só um dot de cor (effective_status) e `enrollment_status` cru em inglês quando ≠ `not_enrolled` (ex.: `enrolled`) |
| 8 | Enums existentes | `EditalStatus.php`, `EnrollmentStatus.php`, `EventType.php` | Já têm `label()`/`order()`/`countsAsEnrolled()`; **não existe** "aguardando resultado" nem "prova feita sem resultado" |
| 9 | Fontes | `app.css:11-14,246-251`; `CaseSlot.tsx:75` | Títulos usam `rm-display` = Instrument Serif 400, `leading 0.95`, `text-lg`: fino e apertado em 18px; tokens `--text-label/body/title/display` = 12/14/18/32 |
| 10 | Dossiê/links | `DossierModal.tsx:175,187-209` | Edital + inscrição já são os 2 CTAs; `SourceList` sempre aberta com N links |
| 11 | Duplicação | `nextEventLabel` em `CaseSlot.tsx:21`, `ItemBox.tsx:30`, `ItemDescription.tsx:14` | 3 cópias idênticas; o cartão novo precisa do mesmo cálculo |

**Fluxo atual**: `RadarPageController::index` → `ContestPresenter::present` (status efetivo via `Contest::effectiveStatus`) → página `radar/index` → `AttacheCase`/`MonthCalendar`/`ItemBox`.

**Incertezas**: (a) aparência real dos títulos e do corte — só confirmo com screenshot no exec (há `tasks/screenshots/a11y`); (b) se o seed tem eventos `result` futuros para todos os `exam_taken`.

## FASE 3 — Architecture Planning

### Decisão 1 — Onde e como derivar a "fase atual"

**3A — Abordagens**
- A) **Enum `ContestPhase` + `ContestPhaseResolver` (serviço PHP puro)**, chamado pelo `ContestPresenter`; o front só renderiza `phase`, `phase_label`, `phase_tone`.
- B) Derivar no front (função TS em `lib/`), a partir de `effective_status`, `enrollment_status`, `events`.
- C) Só reaproveitar `effective_status_label` e `enrollment_status` e mostrar duas tags empilhadas.

**3B — Avaliação** (1–5, maior é melhor)

| Critério | A | B | C |
|---|---|---|---|
| Corretude técnica | 5 | 3 | 2 |
| Blast radius | 4 | 4 | 5 |
| Custo de manutenção | 5 | 2 | 3 |
| Alinhamento (regra 5: mecânico em PHP + Pest) | 5 | 1 | 3 |
| **Total** | **19** | 10 | 13 |

**3C — Vencedora: A.** B viola a regra "zero LLM/mecânico em PHP puro com testes Pest" e duplicaria regra de negócio no front. C não produz "aguardando resultado" nem remove o inglês cru. Reversível: remover 3 chaves do payload.

**Fases e regras de derivação** (primeira regra que casar, em ordem). Entrada: `effective_status` (E), `enrollment_status` (R), eventos (V), `now`.

| Ordem | Condição | Fase | Rótulo | Tom (`BadgeKind`) |
|---|---|---|---|---|
| 1 | E = suspended | `suspended` | suspenso | neg |
| 2 | E = finished | `finished` | encerrado | neg |
| 3 | R = exam_taken e existe evento `result` com data ≥ hoje, ou E ≠ finished e não há `result` passado | `awaiting_result` | aguardando resultado | info |
| 4 | R = exam_taken (demais casos; resultado já passou) | `result_out` | resultado saiu | pos |
| 5 | R = venue_released | `venue_released` | local de prova saiu | pos |
| 6 | R = fee_paid | `fee_paid` | taxa paga | pos |
| 7 | R = enrolled | `enrolled` | inscrito | pos |
| 8 | E = exam_done (R não inscrito) | `exam_done` | prova aplicada | neu |
| 9 | E = registration_closed | `registration_closed` | inscrições encerradas | neg |
| 10 | E = registration_open | `registration_open` | inscrições abertas | pos |
| 11 | E = published | `edital_published` | edital publicado | neu |
| 12 | E = in_preparation | `edital_in_preparation` | edital em formulação | neu |
| 13 | E = authorized | `authorized` | autorizado | info |
| 14 | E = expected | `expected` | previsto | info |

Exemplos pedidos: DATAPREV (prova feita, resultado do gabarito pendente) → **aguardando resultado**; CGU (R=enrolled) → **inscrito**; ANPD (E=in_preparation) → **edital em formulação**.

Notas: a inscrição pessoal (R) vence o estado do edital porque é o que o usuário quer ver "em evidência" depois de se inscrever; `interested` (quero fazer) não muda a fase (segue a do edital) e vira só um marcador no painel. Reutilizar `label()` já existentes quando possível (`EditalStatus::InPreparation` hoje diz "edital em produção" → o enum novo diz "edital em formulação", texto do usuário). **Confiança: MÉDIA** na regra 3 (depende de existirem eventos `result` no seed; sem evento, `exam_taken` cai em `awaiting_result` por padrão — escolha conservadora).

### Decisão 1b — Tag "novo" e estado padrão

**Estado padrão**: já resolvido pelo banco (`create_contests_table.php:41`, default `not_enrolled`; `ContestSeeder.php:211` repete o default). A fase exibida de um concurso não inscrito vem do edital (regras 8–14). Nada a criar; só teste de regressão.

**"Novo" — 3 abordagens**
- A) Flag derivada `is_new` no `ContestPresenter`: `created_at >= now-7d` e sem interação (não `not_enrolled` → some; fixado/descartado → some).
- B) Coluna `seen_at`/`first_seen_at` (some ao abrir o dossiê). Melhor semântica, mas exige migration: fere "não inventar coluna".
- C) Guardar "visto" em `localStorage`. Por navegador; não sobrevive a outro dispositivo e some ao limpar dados.

| Critério | A | B | C |
|---|---|---|---|
| Corretude | 4 | 5 | 3 |
| Blast radius | 5 | 2 | 4 |
| Manutenção | 5 | 3 | 3 |
| Alinhamento (PHP puro, sem coluna) | 5 | 1 | 2 |
| **Total** | **19** | 11 | 12 |

**Vencedora: A.** `is_new` é ortogonal à fase (não é uma fase nova): aparece como tag extra "novo" ao lado da fase. `created_at` só é gravado na criação (`updateOrCreate` não o altera), então só concursos realmente inseridos contam.

**Limitação conhecida**: no primeiro seed (ou `migrate:fresh`) todos os concursos ficam "novos" por 7 dias. Mitigação: a janela é uma constante (`config/radar.php → new_window_days`, default 7) e a tag some assim que você interage. Se isso incomodar, a evolução é a opção B, que precisa da sua aprovação por mexer no modelo de dados.

### Decisão 2 — Como consertar o corte da maleta

**3A**
- A) Tornar a **região do meio (grade/por nível) a que encolhe e rola** (`min-h-0 flex-1 overflow-y-auto`) e o `ItemDescription` `shrink-0`, com botões em `flex-wrap`.
- B) Reduzir o tamanho dos slots (altura máx.) para tudo caber sem scroll.
- C) Mover o painel de descrição para um popover/overlay sobre a grade.

**3B**

| Critério | A | B | C |
|---|---|---|---|
| Corretude | 5 | 3 (quebra em telas ainda menores) | 3 |
| Blast radius | 4 | 4 | 2 (muda interação e teclado) |
| Manutenção | 5 | 3 | 2 |
| Alinhamento | 5 | 4 | 2 |
| **Total** | **19** | 14 | 9 |

**3C — Vencedora: A**, com um ajuste de B: slots com `min-h` mínimo para a grade 4×3 não colapsar. Botões e rótulos nunca ficam fora da dobra porque o painel é `shrink-0` e a grade absorve a falta de altura. Reversível (só classes Tailwind).

### Decisão 3 — Cards curtos do calendário

Única abordagem coerente: manter `EventFolder` (já clicável) e trocar o conteúdo por `acronym · (event.label ?? event.type_label)` em `uppercase`, `truncate`, `title` com texto completo; manter dot de tier/pin (sinal útil e já acessível) e `sr-only` "na maleta". Descartado: criar novo componente (duplicaria); card de 2 linhas (estoura célula `min-h-[4.5rem]`).

### Decisão 4 — Tipografia e cartões

- Títulos (sigla/órgão) deixam `rm-display` fino a 18px nos slots/painel; usar `--font-sans` (Inter Tight) 600–700 com `t-body`/`t-title` e `leading-tight`, mantendo `rm-display` só em títulos de seção grandes (≥ 32px, ex.: mês do calendário). Tokens existentes, sem fonte nova.
- Slot da maleta: linha 1 sigla; linha 2 **tag de fase** (esquerda, com ícone/forma além da cor para não depender só de cor); linha 3 próximo evento. O dot de status e o `TierMark` chip vão para o canto/linha da sigla.
- Painel `ItemDescription`: fase em destaque (tag grande), depois bloco de dados (vagas TI, salário, taxa, banca, próximo evento, prazo) e só então os 2 CTAs.
- `SourceList` dentro de `<Collapsible>` (já existe `ui/collapsible.tsx`), recolhido por padrão, contador "fontes (N)".
- Extrair `nextEventLabel` para `resources/js/lib/radar.ts` e remover as 3 cópias.

**Validações obrigatórias**
- Lógica de negócio na camada certa? Sim, fase em Service PHP; front só exibe.
- Acoplamento? Enum + resolver isolados; presenter só chama.
- Reversível? Sim (aditivo).
- Contratos? Presenter só ganha chaves; `ContestDto` ganha 3 campos.

## FASE 4 — Execution Planning

1. **Baseline**: rodar `php artisan test`, `pint --test`, `phpstan`, `eslint`, `tsc`, `prettier --check`; anotar falhas preexistentes; screenshot "antes" da Mesa em 1366×768 e 1920×1080 (maleta cortada).
2. **Domínio**: criar `app/Enums/ContestPhase.php` (backed string; `label()`, `tone()`) e `app/Services/Radar/ContestPhaseResolver.php` com `resolve(Contest, ?CarbonInterface $now)` e `isNew(Contest, ?CarbonInterface $now)` (janela em `config/radar.php`).
3. **Testes Pest do resolver** (`tests/Unit/Services/ContestPhaseResolverTest.php`, tabela de casos): uma linha por fase da tabela da Decisão 1, mais bordas (`exam_taken` com `result` futuro/passado/ausente; `interested` não altera; `suspended` vence `enrolled`).
4. **Presenter**: `ContestPresenter` ganha `phase`, `phase_label`, `phase_tone`, `is_new`; teste de feature em `RadarPageControllerTest` conferindo as chaves.
5. **Tipos + util TS**: `types/radar.ts` (`ContestPhaseValue`, 4 campos em `ContestDto`); `lib/radar.ts` com `nextEventLabel` único e mapa fase→`BadgeKind`; substituir as 3 cópias.
6. **Calendário**: remover `toRoman`/`ROMAN_TABLE`; título `{mês} · {ano}`; `EventFolder` com `acronym · event.label` em caixa alta. Revisar `CalendarControllerTest` se algo depender do texto.
7. **Maleta (layout)**: `AttacheCase` (região do meio `min-h-0 flex-1 overflow-y-auto`, `ItemDescription` `shrink-0`), botões com `flex-wrap`.
8. **Maleta (conteúdo)**: `CaseSlot` com sigla em fonte sans + tag de fase + próximo evento; `Tag` aceita ícone/forma; `ItemDescription` com fase em destaque + dados + 2 CTAs; `DossierModal` com `SourceList` recolhida; `ItemRow` usa a fase em vez de só `effective_status_label`.
9. **Verificação no navegador** (skill `run`/Chrome): Mesa em 1366×768 e 1920×1080, grade e "por nível", navegar mês dez→jan, clicar card do calendário; checar contraste (`tasks/` já tem script de contraste do T9) e foco por teclado.
10. **Docs e gates**: atualizar `docs/03-ui-inventario.md` (cards, fase, ano); rodar todos os gates; reportar o que sobrou.

## FASE 5 — Verificação do Plano

- [x] Nenhuma regra de negócio na camada errada (fase em Service PHP + Pest)
- [x] Sem acoplamento desnecessário (enum/resolver isolados; front só exibe)
- [x] Critérios de aceite objetivos e testáveis
- [x] Checklist ≤ 10 itens (10)
- [x] ≤ 3 abordagens por decisão, 1 escolhida com justificativa
- [x] Evidências ≤ 7 arquivos-núcleo (11 linhas na tabela, mas sobre 7 arquivos-fonte principais + 3 cópias de uma mesma função)
- [x] Perguntas bloqueadoras respondidas; premissa "cartões" marcada **MÉDIA**

**Riscos / pontos de atenção**
- Regra 3 (`awaiting_result`) depende de eventos `result` no seed; sem eles, `exam_taken` cai em "aguardando resultado" por padrão.
- Seed ainda mistura rótulos ("edital em produção" no enum vs "edital em formulação" pedido): só o rótulo da fase muda, o `EditalStatus::label()` fica.
- `tsc` tem falha preexistente em arquivo gerado (não relacionada).

**Passos de revisão após o `/exec`** (`/aprimorar-code` não existe; usar):
1. `/simplify` — limpar duplicações e código morto do diff.
2. `/code-review` — caçar bugs (derivação de fase, virada de ano, a11y).
3. Reexecutar os gates e o screenshot "depois" nas duas resoluções.

**Gate = Livre para execução.**
