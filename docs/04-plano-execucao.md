# Plano de execução

Uma "tarde" = uma sessão de Claude Code. Cada tarde termina com testes verdes e commit.

## Status (18/09/2026)

T0-T5 concluídas. **T6 quase completa nesta sessão** — scraping, estados de loading/vazio/erro e README feitos. Só falta o deploy de verdade no VPS (documentado no README, mas não executado — sem acesso SSH nesta sessão).

**T6 (loading/vazio/erro) entregou**: `resources/js/lib/notify.ts` (pub/sub mínimo) + `<ErrorToast />` — todo fetch de ação (pin/unpin, checklist, descartar/restaurar, reordenar maleta, carregar descartados, trocar de mês do calendário) que falha agora mostra a mensagem real do backend (ou uma genérica) num toast, em vez de falhar em silêncio. `SweepControl` ganhou o indicador "N fonte(s) falhou/falharam" com tooltip listando `run.meta.errors`, que estava na spec (`docs/03-ui-inventario.md`) mas nunca tinha sido implementado. `MonthCalendar` ganhou indicador "carregando…" ao trocar de mês e um botão "tentar de novo" já existente na `ItemBox` (descartados) ganhou tratamento de erro de rede (antes travava em "Carregando…" pra sempre se a fonte de erro fosse client-side). Testado no browser: rodei um `radar:sweep` real que bateu no bug de SSL local (ver pendência da T6-scraping) e confirmou o pipeline não quebra com fonte falhando — e serviu de dado real pro teste do indicador de erro.

**README reescrito em inglês** — o antigo ainda descrevia o "Radar Hub" (produto pivotado, descontinuado em 15/09/2026). Novo cobre: o que é, diagrama de arquitetura (pipeline + camadas do monolito), stack, como rodar localmente, comandos artisan, tabela de fontes, modelo de custo (sem chave = grátis; com chave, custo de busca web varia — aponta pra página de preço da Anthropic em vez de cravar um valor que muda), e passo a passo de deploy no VPS com config de Supervisor pro queue worker (documentado, não executado).

**T6 (scraping) entregou**: `PciConcursosSource` e `OfficialPagesSource` (`app/Services/Radar/Sources/`), registrados em `ContestSourceRegistry`, habilitados por padrão em `config/radar.php`. Dependências novas: `symfony/dom-crawler` + `symfony/css-selector` (só `css-selector` já vinha transitivo; agora explícitos). Testes unitários com fixtures em `tests/Fixtures/` (`pci_concursos_sample.html`, `official_page_sample.html`).

**Descoberta que corrigiu a spec original** (verificado com `curl` real em 18/09/2026, robots.txt conferido): o PCI Concursos não tem uma página por estado como `docs/02-pipeline.md` supunha — `pciconcursos.com.br/concursos/` é uma página só com nacional + todos os 26 estados + DF, cada bloco de estado marcado por `<div id="{UF}" class="ua">` seguido dos cartões de concurso até o próximo marcador. Isso simplificou bastante o adapter (1 request em vez de ~27).

**Pendência nova**: não foi possível validar o `PciConcursosSource`/`OfficialPagesSource` contra a rede real a partir desta máquina — `php artisan radar:source pci_concursos --dry` falha com `cURL error 60: SSL certificate problem: self-signed certificate in certificate chain`. `curl.cainfo` do PHP local está vazio; o `curl` do Git Bash usado pra pesquisar a estrutura do site funcionou normalmente, então é uma diferença de CA bundle entre os dois, não um problema do adapter. A cobertura de teste (fixtures com HTML real capturado via `curl`) dá confiança razoável, mas vale rodar `radar:sweep` de verdade numa máquina/ambiente sem esse problema de SSL antes de confiar 100% — mesma categoria de pendência do `AnthropicProvider` nunca testado contra a API real (ver T3).

`official_pages` está com `pages => []` — o dono do projeto ainda não passou concursos específicos pra cadastrar; o mecanismo genérico (texto + links) está pronto, só falta preencher a lista quando ele quiser.

T5 entregou: `AttacheCase`/`CaseSlot`/`ItemDescription` (maleta funcional — grade 4×3, seleção por teclado com setas/Enter/P, drag and drop nativo pra reordenar), `DossierModal`/`EnrollmentChecklist`/`SourceList`, toggle "descartados" na `ItemBox` (fetch sob demanda de `GET /contests/dismissed`), toast "desfazer" (8s) ao descartar. Endpoints novos: `PATCH /contests/{contest}/enrollment`, `POST /contests/{contest}/dismiss`, `POST /contests/{contest}/restore`, `GET /contests/dismissed`, `PATCH /pins` (reorder). `ContestPresenter` ganhou `fee_cents`/`salary_min_cents`/`salary_max_cents`/`it_vacancies`/`field_sources` pros mini-stats e fontes do dossiê.

**Escopo reduzido conscientemente** (combinado com o dono do projeto antes de começar): o `DossierModal` não tem "resumo"/"cargos de TI"/"divergências"/"lacunas" nem o log de `contest_status_logs` — o schema atual do dossiê (`docs/agents/dossie.md`) só gera campo+fonte, não texto narrativo, e adicionar essas seções exigiria inventar dado que a IA não produz hoje. O modal ficou fiel ao que o app realmente tem: header, linha do tempo, mini-stats, links, checklist, pin, descartar/restaurar, fontes.

**Bug pego só no teste manual no browser** (não no `php artisan test`/`tsc`/`eslint`): `DialogContent` do shadcn tem `sm:max-w-lg` fixo; minha classe `max-w-none` (sem prefixo `sm:`) não vencia esse breakpoint no desktop porque variantes diferentes não competem no mesmo grupo do `tailwind-merge` — o modal do dossiê renderizava com ~512px em vez de `min(1040px,94vw)`. Corrigido adicionando `sm:max-w-none` também. Lição: ao sobrescrever uma classe responsiva de um componente shadcn, repetir o prefixo de breakpoint na própria sobrescrita, não só a versão base.

Pendência nova: não foi possível verificar o drag-and-drop da maleta via automação de browser (`left_click_drag` do Claude in Chrome não dispara os eventos nativos de HTML5 `dragstart`/`dragover`/`drop` que `CaseSlot`/`AttacheCase` usam) — a lógica foi validada por teste Pest (`PinControllerTest::test_it_reorders_the_case`) e por leitura de código, mas o gesto de arrastar em si precisa de um teste manual humano na próxima sessão.

T4 entregou: tokens papel & tinta + fontes do handoff em `resources/css/app.css` (app sempre claro, `--radius: 0`), `RadarPageController` (`GET /`), `CalendarController` (`GET /calendar`), `PreferencesController` (`PATCH /preferences/states`), `ContestPresenter` (serialização compartilhada). Componentes `Tag`/`Stamp`/`RadarMark`/`RadarAnim`, `SweepControl` (+ polling), `StateFilterPopover`, `MonthCalendar`/`EventFolder`, `ItemBox`/`ItemRow`. Testado ponta a ponta no Chrome contra SQLite seedado (login → varrer, filtro de estados, pin/unpin) — sem erros de console.

**Desvio combinado com o dono do projeto**: `POST/DELETE /contests/{contest}/pin` (`PinController`) foi adiantado da T5 para a T4, porque a `ItemBox` da spec já tem botão de pin e a maleta visual (T5) ainda não existe — ver decisão registrada nesta sessão.

**Bug pego só no teste manual no browser** (não no `php artisan test`): `ItemBox` copiava a prop `contests` pra dentro de `useState` na montagem; um partial reload do Inertia (ex. aplicar o filtro de estados) atualizava a prop mas o componente continuava mostrando a lista antiga. Corrigido trocando por overrides otimistas por id em cima da prop, sem cópia. Lição: qualquer componente que recebe uma lista via prop e precisa de estado local só para updates otimistas (não para a lista inteira) corre esse risco — vale desconfiar de `useState(propList)` em revisões futuras.

Pendências deixadas para trás, a revisitar quando fizer sentido:
- `migrate:fresh --seed` nunca foi validado contra Postgres real (só via SQLite/Pest e, nesta sessão, contra um SQLite de arquivo temporário) — falta senha do Postgres local no `.env`.
- Fonte `provas_de_ti` verificada e desativada: `provasdeti.com.br/concursosdeti/abertos` é uma SPA React sem HTML no servidor, sem headless browser não dá pra raspar. Fica registrada desabilitada em `config/radar.php`; `pci_concursos` e `official_pages` (T6) são as fontes que efetivamente vão popular o scraping.
- Testes de scraping usam `raw_listings` sintéticos (não fixtures de HTML real), porque ainda não existe adapter funcional pra gerar fixture.
- `AnthropicProvider` não foi testado contra a API real — primeira varredura real deve conferir `docs/02-pipeline.md` e o preço de busca web atual.
- `memory/` e `scripts/send-email.ps1`/`SETUP-EMAIL.md` na raiz do repo parecem artefatos do produto antigo "Radar Hub" (pivotado) — não tocados, vale limpeza futura.
- Toggle de aparência (claro/escuro) do starter kit não foi removido da tela de settings — o app não usa mais `.dark`, então o toggle hoje é inofensivo mas morto. Esconder fica para quando mexer em settings de novo.
- `ItemBox` ainda não tem o toggle "descartados" da spec — depende de `GET /contests/dismissed`, que fica pra T5 junto do modal do dossiê (é lá que mora o botão "trazer de volta").

## T0 — Limpeza do pivot

Estado atual do repo (15/09): starter kit Laravel 13 + Inertia 3 + React 19 + Fortify, sem código de domínio, com docs e prompts do Radar de ideias.

- Remover `docs/00-mvp-spec.md`, `docs/agents/{scout,synthesis,curator,deep-dive}.md`, `resources/prompts/{scout,synthesis,curator,deep-dive}.md`.
- Substituir `CLAUDE.md` pelo novo. Adicionar os docs novos em `docs/`.
- **Manter** `design/radar mensal/` (referência de tokens e componentes). Mover `direction-a`, `direction-b` e `logos` só se atrapalharem a leitura — não apagar.
- Desligar cadastro público no Fortify (`Features::registration()` fora) e criar `UserSeeder` com o usuário do `.env` (`RADAR_USER_EMAIL`, `RADAR_USER_PASSWORD`).
- Rota `/` → tela principal autenticada (`radar/index`). Remover `welcome` e `dashboard` do fluxo principal.
- `config/radar.php` com as chaves: `sources`, `area_keywords`, `boards`, `enrich`, `llm`, `timezone`.

## T1 — Domínio

Enums, migrations e models de `docs/01-modelo-de-dados.md`. `ContestStatusResolver`, `Contest::isExpired()`, `scopeVisible()`, `UnpinExpiredContests`, `PinService` (fixar, desafixar, reordenar, maleta cheia). Factories + testes obrigatórios da camada. `ContestSeeder` lendo `database/seeders/data/contests-seed.json` (ver `docs/05-seed-inicial.md`); o `ContestMerger` pode entrar aqui numa versão mínima (eventos + `closed_at`) e ser completado na T2.

## T2 — Scraping

Enums `BrazilianState`/`Region`, colunas `states`/`nationwide`/`users.preferences`, `StateFilter` + escopo `matchingStates` (ver `docs/06-filtro-estados.md`). `ContestSource` + adapter `provas_de_ti` (verificar URL, seletores e robots.txt primeiro). `ContestNormalizer`, `ContestMerger`, `HarvestSourcesJob`, `NormalizeListingsJob`, comando `radar:source --dry`. Testes com HTML salvo em fixtures.

## T3 — Enriquecimento e orquestração

`LlmProvider`, `AnthropicProvider` (web_search), `NullProvider`, `DossierValidator`, `EnrichContestJob`, `EnrichContestsJob`, `FinalizeRunJob`, `SweepController`, `radar:sweep`. Testes com fixtures JSON e `Http::fake()`. Rodar uma varredura real de `development` e conferir custo em `search_runs.meta`.

## T4 — Mesa

Tokens no `app.css`, fontes, masthead com `SweepControl` + polling, `MonthCalendar` + `EventFolder`, `ItemBox`, `StateFilterPopover` + `PATCH /preferences/states`. Portar `Tag`, `Stamp`, `RadarMark`, `RadarAnim` do handoff.

## T5 — Maleta e dossiê

`AttacheCase`, `CaseSlot`, `ItemDescription`, teclado, drag and drop, `DossierModal`, `EnrollmentChecklist`, endpoints de pin/enrollment com updates otimistas.

## T6 — Fechamento

Adapter `pci_concursos` (por estado) e `official_pages`. Estados de loading/vazio/erro. README em inglês (o que é, arquitetura com diagrama, como rodar, fontes e custo). Deploy no VPS com worker de fila (supervisor).

## T7 — Atividade e painel (pós-MVP, aprovado em 15/10/2026)

Escopo: só dentro da tela, sem e-mail nem push. Etapas: (1) `contest_changes` + log no `ContestMerger` ✅; (1a) desafixar expirados no `GET /` (slot fantasma, 17/10) ✅; (1b) página Arquivo (`/archive`, tudo que saiu da mesa — `docs/03`) ✅; (2) varredura ao abrir o app quando a última tem >24h (`--no-llm`); (3) selos "novo / mudou / falhou" nos cartões, via `users.preferences` (última visita); (4) tela "Atividade" (linha do tempo); (5) página `/dashboard` com KPIs.

## Critérios de aceite do MVP

- [ ] Escolher uma área e apertar varrer popula calendário e caixa de itens sem nenhum passo manual.
- [ ] Sem `ANTHROPIC_API_KEY`, a varredura termina com dados raspados e nenhuma tela quebra.
- [ ] Todo concurso tem status do edital; `registration_open` com prazo vencido aparece como encerrado.
- [ ] Concurso encerrado e não inscrito some da tela entre 24h e 24h+1 request após o encerramento, some da maleta junto e continua no banco.
- [ ] Descartar tira o concurso da mesa e da maleta na hora; "trazer de volta" devolve; varreduras não o enriquecem.
- [ ] Marcar "inscrito" impede o sumiço; reabrir inscrições traz o concurso de volta.
- [ ] `php artisan migrate:fresh --seed` sobe a tela com os 28 concursos semeados (seed de 13/10/2026): 20 na mesa (5 deles na maleta), 4 no toggle de descartados e 4 já fora da mesa pela regra das 24h (janelas perdidas antes de existir o app). Contagem medida em 13/10/2026; muda a cada seed novo.
- [ ] Marcar só alguns estados muda o que aparece na mesa sem tirar nada da maleta, e a próxima varredura só busca as páginas desses estados.
- [ ] Varrer duas vezes seguidas a mesma área não duplica concursos nem eventos.
- [ ] Maleta: 12 slots, fixar/desafixar/reordenar por mouse e teclado, aviso de maleta cheia.
- [ ] Modal mostra link do edital (ou o aviso de que não saiu), checklist de inscrição, fontes com data e confiança.
- [ ] Todo campo exibido tem origem registrada em `field_sources`.
- [ ] Custo de LLM e nº de buscas de cada varredura visíveis em `search_runs.meta`.
- [ ] Visual usa só os tokens do handoff; radius 0; nenhuma tela com cara de shadcn padrão.

## Backlog v2 (não tocar)

Varredura agendada (cron) · notificações por e-mail/push · export .ics · itens de tamanhos diferentes na maleta · anotações livres · headless browser · leitura do PDF do edital · mobile.
