# Plano — T7: resultados de prova + painel de progresso (17/10/2026)

Branch: `feat/exam-results`, empilhada em `feat/contest-archive` (PR #11, aberto). Tipo: feat. Substitui a etapa (5) da T7 ("página `/dashboard` com KPIs") por um painel de **resultados**.

## FASE 1 — Scope Analysis

**Objetivo**: registrar o resultado de cada prova que fiz (nota total, e por matéria se eu quiser) e ver meu progresso entre concursos em gráficos.

**Áreas afetadas**: migration + model `ExamResult`, enum `ExamOutcome`, `ContestPresenter`, controllers de resultado e painel, rotas, `config/fortify.php` (`home`), `DossierModal` (bloco "resultado"), página `radar/dashboard.tsx`, componentes de gráfico, docs `01`/`03`/`04`.

**Escopo**
- R1. Cadastro de resultado no dossiê, para concurso com situação "prova feita": nota obtida, nota máxima, nota de corte (opcional), situação do resultado, classificação e nº de candidatos (opcionais), origem da nota (conferi pelo gabarito / preliminar / oficial).
- R2. Detalhe por matéria **opcional**: lista de linhas `matéria · nota · máximo`. O campo matéria sugere os nomes já usados (datalist), assim "Português" não vira três matérias diferentes.
- R3. Painel `/dashboard` com KPIs e 4 gráficos (ver Bloco 3).

**Detalhe por matéria — veredito**: guardar é **fácil** (1 coluna `jsonb` + um repetidor de linhas no formulário). Comparar a mesma matéria entre concursos depende de nome consistente; a sugestão por datalist resolve no uso pessoal. Normalização automática de nomes fica fora.

**Não-escopo**: importar resultado de site/PDF; LLM (zero LLM — é dado meu); anotações livres (Backlog v2); arquivar automaticamente concurso com resultado final (pergunta aberta abaixo); export; multiusuário.

**Impacto técnico**
- Dados: tabela nova `exam_results` (registrar em `docs/01` **antes** da migration — regra do repo).
- Rotas: `PUT contests/{contest}/result`, `DELETE contests/{contest}/result`, `GET /dashboard` (deixa de ser alias da mesa).
- UI: bloco "resultado" no dossiê; página do painel; link "painel" no masthead.

**Critérios de aceite**
1. No dossiê de um concurso com "prova feita", salvo nota 62/70, corte 57,5, origem "gabarito": persiste, reabre preenchido, mostra `88,6%` e `+4,5 acima do corte`.
2. Sem situação "prova feita", o bloco não aparece; a API recusa (422).
3. Validação: `score ≤ max_score`, `max_score > 0`, posição ≤ candidatos; cada linha de matéria com `score ≤ max`. Soma das matérias **não** precisa bater com o total (prova pode ter peso) — só aviso visual.
4. Painel lista KPIs (provas feitas, com resultado, aprovações/CR, média %, melhor %) e os 4 gráficos com dados do banco; com 0 resultados mostra estado vazio com instrução, sem quebrar.
5. Cada gráfico tem alternativa em tabela (acessível por teclado/leitor de tela); cores só dos tokens do handoff, `radius: 0`; funciona em claro/escuro se a app tiver os dois.
6. Login continua caindo na mesa (`/`), não no painel.
7. Pest: model/validação, controller de resultado (salvar, editar, apagar, 422), payload do painel (agregados corretos num dataset fixo). Gates verdes (test, pint, phpstan, eslint, prettier nos arquivos novos, tsc sem erro novo).

**Perguntas abertas (não bloqueiam)**
- Concurso com resultado **oficial** e status `finished` deve ir pro Arquivo automaticamente? Hoje "prova feita" fica na mesa pra sempre. Proposta: decidir depois de usar o painel; fica anotado.

**Premissas**: volume pequeno (< 50 resultados) → agregação em PHP, sem tabela de cache; escala de nota varia por banca (70 questões, 100 pontos, Cebraspe com desconto) → comparação entre concursos sempre em **%** (`score / max_score`).

## FASE 2 — Evidence Gathering

| # | Evidência | Âncora | Achado |
|---|---|---|---|
| 1 | Situação "prova feita" já existe | `app/Enums/EnrollmentStatus.php` (`ExamTaken`) | Gatilho natural para liberar o cadastro |
| 2 | Caso real | `contests-seed.json` → `dataprev-2026.summary` | "Régua do edital: mínimo de 57,5 pontos"; gabarito preliminar antes do oficial → precisa de nota de corte e de origem da nota |
| 3 | `/dashboard` é alias da mesa | `routes/web.php:18`, `config/fortify.php:76` (`home => /dashboard`) | Trocar `home` para `/` antes de reaproveitar a rota |
| 4 | Restos do starter kit | `resources/js/components/app-header.tsx:45`, `app-sidebar.tsx:22` (`dashboard()`) | Layouts não usados pelas páginas do radar; o link passa a apontar pro painel, sem quebrar |
| 5 | Sem lib de gráfico | `package.json` | Decisão no Bloco 3 |
| 6 | Padrão de dossiê/endpoint | `DossierModal.tsx`, `EnrollmentController.php` (validação manual, JSON) | Reusar padrão: `csrfFetch` + `{ contest }` no retorno |
| 7 | Presenter central | `app/Support/Radar/ContestPresenter.php` | Incluir `result` (ou null) no DTO |

## FASE 3 — Architecture Planning

### Bloco 1 — Onde guardar o resultado
| Abordagem | Corretude | Blast radius | Manutenção | Alinhamento |
|---|---|---|---|---|
| A. Tabela `exam_results` (1 por concurso + etapa) com `breakdown jsonb` | 5 | 5 (tabela nova, nada existente muda) | 5 | 5 |
| B. Colunas novas em `contests` | 3 (mistura dado meu com dado raspado; merger/`field_sources` passam a tocar nelas) | 3 | 3 | 2 |
| C. Tabela de resultado + tabela de matérias normalizada | 5 | 4 | 2 (2 tabelas, CRUD de matéria) | 3 (over-engineering pra uso pessoal) |

**Vence A.** Schema proposto (vai pro `docs/01`):
`exam_results: id, contest_id fk (cascade), stage (string, default 'objective'; unique com contest_id), score numeric(7,2), max_score numeric(7,2), cutoff_score numeric(7,2) null, outcome (pending|passed|reserve|failed), source (self_graded|preliminary|official), position int null, candidates int null, breakdown jsonb null ([{subject, score, max}]), taken_on date null (default = exam_date), timestamps`.
`stage` deixa espaço pra discursiva/títulos sem migration nova; a UI do MVP só edita `objective`.

### Bloco 2 — Fluxo de cadastro
| Abordagem | Corretude | Blast radius | Manutenção | Alinhamento |
|---|---|---|---|---|
| A. Bloco "resultado" dentro do dossiê (form inline, `PUT` idempotente) | 5 | 4 (mexe no modal) | 4 | 5 (dossiê já é onde marco a situação) |
| B. Página própria de cadastro | 4 | 5 | 3 | 3 (mais um lugar pra navegar) |
| C. Formulário só no painel | 3 (desconectado do concurso) | 5 | 3 | 2 |

**Vence A.** Visível quando `enrollment_status = exam_taken`. Também abre pelo painel (clique na prova → dossiê).

### Bloco 3 — Gráficos
| Abordagem | Corretude | Blast radius | Manutenção | Alinhamento |
|---|---|---|---|---|
| A. SVG à mão, componentes pequenos (`LineChart`, `BarChart`) com tokens | 5 (poucos pontos, sem interação complexa) | 5 (zero dependência) | 4 | 5 (tokens do handoff, `radius: 0`, sem cara de lib) |
| B. Recharts | 5 | 3 (+dep, estilos padrão a desfazer) | 4 | 3 |
| C. Chart.js (canvas) | 4 (canvas pior pra acessibilidade) | 3 | 3 | 2 |

**Vence A.** Antes de escrever código de gráfico: carregar a skill `dataviz` (paleta, marcas, acessibilidade).

Gráficos do painel:
1. **Progresso no tempo** — linha de `%` por prova (eixo x = data da prova), com marca do corte de cada prova. É o gráfico de "estou melhorando?".
2. **Margem sobre o corte** — barras divergentes por concurso (acima/abaixo de 0).
3. **Por matéria** — barra horizontal com `%` médio por matéria (só aparece com ≥ 1 detalhe preenchido).
4. **Funil** — todos os concursos: quero fazer → inscrito → prova feita → com resultado → aprovado/CR.

KPIs: provas feitas · com resultado · aprovações + CR · média % · melhor % (concurso).

### Bloco 4 — Rota do painel
**Vence**: `GET /dashboard` vira o painel (`DashboardController`), `config/fortify.php` `home => '/'`. Descartado: `/results` (o plano da T7 e o starter kit já chamam de dashboard). Agregação em PHP numa classe `app/Services/Radar/ResultsSummary.php` (pura, testável).

**Validações**: regra (percentual, margem, agregados) no backend, testável sem front ✅; nada de LLM ✅; tabela nova não toca merger/seed ✅; reversível (drop table + rota) ✅.

## FASE 4 — Execution Planning

1. **Docs primeiro**: registrar `exam_results` em `docs/01`, bloco "resultado" e "Painel" em `docs/03`.
2. **Domínio**: migration, `ExamResult` (casts decimal/jsonb, `percent()`, `margin()`), enum `ExamOutcome`/`ResultSource`, relação `Contest::results()`, factory. Testes de model.
3. **Cadastro (API)**: `ExamResultController` `PUT`/`DELETE` com validação manual (padrão do repo), 422 se não for "prova feita". `ContestPresenter` passa `result`. Testes Feature.
4. **Cadastro (UI)**: bloco "resultado" no `DossierModal` — campos totais, repetidor opcional de matérias com datalist, prévia de % e margem.
5. **Agregação**: `ResultsSummary` (KPIs, série temporal, margens, por matéria, funil). Testes Unit com dataset fixo.
6. **Rota**: `fortify.home => '/'`; `GET /dashboard` → `DashboardController`; teste de login caindo em `/`.
7. **Gráficos**: carregar skill `dataviz`; `resources/js/components/radar/charts/` (linha, barras, barras divergentes) com tabela alternativa.
8. **Página**: `radar/dashboard.tsx` com KPIs + 4 gráficos + vazio; link "painel" no masthead (ao lado de "arquivo").
9. **Gates**: test, pint, phpstan (`--memory-limit=1G`), eslint, prettier nos arquivos novos, tsc sem erro novo, `npm run build`.
10. **Verificação manual + commits**: cadastrar o Dataprev quando sair o gabarito; conferir painel claro/escuro. Commits atômicos (`feat(radar): add exam results`, `feat(radar): add results dashboard`, `docs`). PR só quando pedir.

## FASE 5 — Verificação do Plano

- [x] Regra de negócio no backend (`ExamResult`, `ResultsSummary`), front só desenha
- [x] Sem acoplamento novo com merger/seed/varredura
- [x] Critérios objetivos e testáveis
- [x] Checklist ≤ 10 itens
- [x] Máx. 3 abordagens por bloco, 1 vencedora justificada
- [x] Evidências ≤ 7 arquivos
- [x] Perguntas bloqueadoras: nenhuma; 1 pergunta aberta registrada (arquivar com resultado final)

**Gate = Livre para execução.** Depende do PR #11 só por empilhamento de docs; código independente.
