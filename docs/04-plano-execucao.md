# Plano de execução

Uma "tarde" = uma sessão de Claude Code. Cada tarde termina com testes verdes e commit.

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

## Critérios de aceite do MVP

- [ ] Escolher uma área e apertar varrer popula calendário e caixa de itens sem nenhum passo manual.
- [ ] Sem `ANTHROPIC_API_KEY`, a varredura termina com dados raspados e nenhuma tela quebra.
- [ ] Todo concurso tem status do edital; `registration_open` com prazo vencido aparece como encerrado.
- [ ] Concurso encerrado e não inscrito some da tela entre 24h e 24h+1 request após o encerramento, some da maleta junto e continua no banco.
- [ ] Descartar tira o concurso da mesa e da maleta na hora; "trazer de volta" devolve; varreduras não o enriquecem.
- [ ] Marcar "inscrito" impede o sumiço; reabrir inscrições traz o concurso de volta.
- [ ] `php artisan migrate:fresh --seed` sobe a tela com os 12 concursos semeados: 10 na mesa (6 deles na maleta) e 2 no toggle de descartados.
- [ ] Marcar só alguns estados muda o que aparece na mesa sem tirar nada da maleta, e a próxima varredura só busca as páginas desses estados.
- [ ] Varrer duas vezes seguidas a mesma área não duplica concursos nem eventos.
- [ ] Maleta: 12 slots, fixar/desafixar/reordenar por mouse e teclado, aviso de maleta cheia.
- [ ] Modal mostra link do edital (ou o aviso de que não saiu), checklist de inscrição, fontes com data e confiança.
- [ ] Todo campo exibido tem origem registrada em `field_sources`.
- [ ] Custo de LLM e nº de buscas de cada varredura visíveis em `search_runs.meta`.
- [ ] Visual usa só os tokens do handoff; radius 0; nenhuma tela com cara de shadcn padrão.

## Backlog v2 (não tocar)

Varredura agendada · notificações · export .ics · itens de tamanhos diferentes na maleta · anotações livres · headless browser · leitura do PDF do edital · mobile.
