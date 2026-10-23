# Plano — filtro de TI estrito na varredura (23/10/2026)

Branch: `fix/sweep-ti-filter`, empilhada em `feat/refresh-tracked` (que ainda não foi pra `main`; partir da `main` perderia T7/T8). Tipo: fix.

Origem: primeira varredura real (run #1, área `development`, sem LLM) encheu a Mesa de concursos que não são de TI (ex.: "Prefeitura de Buritizal", processo seletivo da educação).

## Fase 1 — Escopo

**Objetivo:** sem LLM, só vira concurso na Mesa a listagem que cita **cargo de TI explícito**; o ruído já gravado sai da Mesa de forma reversível.

**Escopo**
- S1. `ContestNormalizer::matchedAreas()` passa a casar por termo inteiro (limite de **letra**, não `\b`: o card do PCI cola número e texto, ex. `R$ 6.000,00Analista`), com fold de acento.
- S2. `config('radar.area_keywords')` reescrito com termos de **cargo** de TI. Sai radical solto (`desenvolv`) e palavra genérica (`dados`, `redes`, `sistemas`, `cloud`, `dba`, `informática` solta). Entram frases de cargo (`analista de ti`, `analista de tecnologia da informação`, `técnico em informática`, `desenvolvedor`, `programador`, `analista de sistemas`, `cientista de dados`, `engenheiro de software`, `analista de segurança da informação`, ...).
- S3. `PciConcursosSource`: o texto do card (`.cd`) é montado juntando os nós filhos com separador, pra parar de colar `InformáticaMédio`.
- S4. `PciConcursosSource`: card com "Vários Cargos"/"Diversos Cargos" busca a notícia do próprio card (`/noticias/...`, mesmo domínio, permitido no `robots.txt`) e grava a lista de cargos (`#noticia ul li`) em `payload.roles`. Esses campos **não** entram no `content_hash`. Não é fonte nova.
- S5. `NormalizeListingsJob` usa `title + text + roles` como haystack. Não usa o corpo inteiro da notícia: "noções de informática" aparece em quase todo edital.
- S6. Action `DismissNonItScrapedContests` + comando `radar:prune-noise {--dry}`. Descarta (`dismissed_at`, `dismissed_reason = "sem cargo de TI (filtro automático)"`) só os concursos que atendem **todos** os critérios abaixo:
  - todas as `field_sources` têm `origin = scrape` (nenhuma `seed`/`llm`/manual);
  - `pinned_slot` nulo, `enrollment_status = not_enrolled`, sem `exam_results`;
  - o matcher novo devolve `[]` para todas as `raw_listings` ligadas.

  É idempotente e reversível pelo "restaurar" que já existe (`DismissController::restore`).
- S7. Métrica em `search_runs.counts`: `scraped`, `matched`, `created`, mais `roles_fetched` e `rejected_no_it`.

**Não-escopo:** LLM pra classificar cargo, ler edital/PDF, headless, fonte nova, agendamento (Backlog v2), qualquer coisa de maleta/`PinService`, filtro de estados.

**Impacto:** sem migration (só `payload` JSON e colunas que já existem). Sem rota nova. Sem mudança de UI. Mais requisições ao PCI por varredura (ver D3).

**Critérios de aceite**
1. Diagnóstico registrado abaixo (Fase 2), com evidência do banco ✅
2. Tabela Pest do matcher: positivos (`Analista de Sistemas`, `Técnico em Informática`, `R$ 6.000,00Analista de TI`, `Analista de Tecnologia da Informação`, `Desenvolvedor`) casam; negativos (`Fundação de Desenvolvimento da Unicamp`, `Auxiliar de Desenvolvimento Infantil`, `cuidados`, `paredes`, `noções de informática`, `Banco de Dados Ambientais`) não casam.
3. Teste do `PciConcursosSource` com fixture HTML: card "Vários Cargos" → `payload.roles` preenchido. Card com cargo listado → nenhuma requisição de detalhe. `content_hash` igual ao de antes.
4. Teste do `NormalizeListingsJob`: listing sem cargo de TI não cria concurso; listing com TI só em `roles` cria.
5. Teste da Action: dispara só no concurso 100% scrape sem TI; poupa fixado, inscrito, seed, merge seed+scrape e quem tem resultado; segunda execução não muda nada.
6. Rodada real local: `radar:prune-noise --dry` lista os 10 falsos positivos da run #1 e nenhum concurso do seed. Depois disso, nova `radar:sweep all --no-llm` com ≥ 80% de TI real numa amostra manual de 20 (ou de todos, se < 20).
7. Gates: Pest, pint, phpstan, eslint/tsc sem erro novo.

**Premissas:** a estrutura `#noticia ul li` vale para a maioria das notícias do PCI. Foi verificada em 1 página; o executor confere em mais 3 (uma com TI). Quando a notícia não tem `ul`, `roles = []` e o card cai pelo texto do card (sem TI explícito → fica fora).

**Perguntas bloqueadoras:** nenhuma (critério de corte confirmado pelo dono: "cargo de TI explícito").

## Fase 2 — Evidências

Consultas no banco local (23/10/2026, run #1 `development`, sem LLM):

| Fato | Evidência |
|---|---|
| 485 `raw_listings` do PCI. 22 ligadas a concurso: 11 concursos novos + 1 merge num seed (SEMA-MT, id 4) | `raw_listings`, `contests` ids 35–45 |
| **10 dos 11 novos são falsos positivos**, todos por causa do radical `desenvolv` (keyword de `development`) | Cemmil, COMDERP, FAMESP, FUNCAMP, PRUDENCO, CIGEDAS, CIDEMA, ABDI ("...de Desenvolvimento..." no nome do órgão). Buritizal e Itatinga: "Auxiliar de **Desenvolv**imento Infantil" |
| Único verdadeiro: HOB (`analista de sistemas`) | raw #152 |
| Match é substring sem limite de palavra | `app/Services/Radar/ContestNormalizer.php:23-38` (`Str::contains`) |
| Keywords genéricas | `config/radar.php:90-97` |
| **275/485 (57%) cards dizem só "Vários Cargos"**: o card sozinho não basta pra decidir | `payload.text` |
| Regex de cargo estrito no texto do card acha só 3 TI (HOB, UEM, Câmara de São João do Sabugi) | consulta ad-hoc |
| Card cola campos sem espaço (`R$ 32.070,88Analista Legislativo, ...MuseólogoSuperior`) | `PciConcursosSource.php:105-108` (`.cd` via `text()`) |
| Notícia do PCI lista os cargos em `#noticia ul li` (Buritizal: 11 `li`, todos de educação) | fetch manual de `/noticias/prefeitura-de-buritizal-...` |
| `robots.txt` do PCI permite `/noticias/` | fetch manual |
| Raw sem match fica com `contest_id` nulo e é reprocessada a cada run | `app/Jobs/Radar/NormalizeListingsJob.php:43-52` |
| `content_hash` = fonte + URL + `payload.text` | `app/Jobs/Radar/HarvestSourcesJob.php` (`store()`) |
| Restaurar descartado já existe | `app/Http/Controllers/Radar/DismissController.php:42` |

**Fluxo atual:** `HarvestSourcesJob` → `PciConcursosSource::fetch()` (só a página-listagem) → `raw_listings` → `NormalizeListingsJob` → `matchedAreas(title + text)` (substring) → cria/atualiza `contests`.

**Métrica "antes" (run #1):** 11 novos, 1 de TI → **9% de precisão**. Recall desconhecido, porque 57% dos cards não mostram cargo.

## Fase 3 — Arquitetura (ToT)

**D1 — Como decidir "é TI" sem LLM?**

| | A. Só matcher estrito no card | B. Matcher estrito + cargos da notícia do PCI (escolhida) | C. Tudo entra numa fila de triagem |
|---|---|---|---|
| Corretude | precisão alta, recall ruim (57% invisível) | precisão alta, recall bom | não filtra nada, empurra o trabalho pro dono |
| Blast radius | 2 arquivos | source + job + normalizer | UI nova + estado novo |
| Manutenção | baixa | média (seletor HTML do PCI) | alta |
| Alinhamento | ✓ | ✓ (mesma fonte, PHP puro) | ✗ (o dono escolheu "TI explícito"; fila é escopo novo) |

Descartadas: A, porque perde TI escondido em "Vários Cargos" (ex.: prefeituras do Nordeste com técnico em informática). C, porque contraria o critério escolhido e abre UI nova.

**D2 — Onde mora a lista de cargos?** Em `payload.roles` da `raw_listing`, fora do hash. Assim a fonte só raspa, o normalizer só decide, e não há migration. Descartado: pôr no `payload.text`, porque mudaria o hash e duplicaria as 485 raws já gravadas.

**D3 — Custo do fetch de detalhe.** Só cards "Vários/Diversos Cargos", só quando ainda não há raw com aquela `external_url` e `roles` preenchido (consulta em lote antes do loop, que é leitura e não acoplamento de escrita). Pausa de 500 ms, teto `radar.sources.pci_concursos.max_detail_fetches` (default 300). Falha num detalhe vira `roles = []` e não derruba a fonte. Primeira varredura: cerca de 275 requisições (~2–3 min). As seguintes: só cards novos.

**D4 — Limpeza.** Descartar, não deletar: é reversível, a UI de restaurar já existe e as raws continuam ligadas (não recriam o concurso). Descartado: `DELETE`, porque é irreversível e perde o rastro.

Validações: regra de domínio no normalizer (PHP puro) ✓; fonte só raspa ✓; reversível (descarte + restore) ✓; contratos de rota/props intactos ✓.

## Fase 4 — Checklist de execução

1. Testes do matcher primeiro (tabela da Fase 1, critério 2). Precisam falhar no código atual.
2. `ContestNormalizer::matchedAreas()`: regex com lookaround de letra `(?<!\p{L})termo(?!\p{L})` sobre texto folded. Keywords de cargo em `config/radar.php`.
3. `PciConcursosSource::parseCard()`: `.cd` montado nó a nó com separador. Fixture HTML do card em `tests/Fixtures`.
4. `PciConcursosSource`: detalhe para "Vários/Diversos Cargos" → `payload.roles`, com pausa, teto, try/catch por item e reaproveitamento de raws já detalhadas. Conferir o seletor em 3 notícias reais antes de fixar.
5. `HarvestSourcesJob::store()`: confirmar que o hash ignora `roles` e que o update de raw existente com mais de 6h preserva `roles` já buscados.
6. `NormalizeListingsJob`: haystack com `roles`. Contar `rejected_no_it` e `roles_fetched` em `counts`.
7. `DismissNonItScrapedContests` (`app/Actions/Radar/`) + `radar:prune-noise {--dry}` + testes (critério 5).
8. Rodada real: `radar:prune-noise --dry` → conferir a lista → rodar sem `--dry` → `radar:sweep all --no-llm` → amostragem manual de 20 → registrar a métrica "depois" neste arquivo.
9. Gates: `php artisan test`, pint, phpstan, eslint/tsc.

## Fase 5 — Verificação do plano

- [x] Regra de negócio na camada certa (normalizer/Action, PHP puro; source só raspa)
- [x] Sem acoplamento novo entre módulos (source lê raws em lote só pra evitar refetch)
- [x] Critérios objetivos e testáveis
- [x] Checklist ≤ 10 itens
- [x] 3 abordagens avaliadas, 1 escolhida com justificativa
- [x] Evidências ≤ 7 arquivos
- [x] Sem pergunta bloqueadora em aberto

**Gate = Livre para execução.**

## Execução (23/10/2026)

**Entregáveis**

| # | Entregável | Caminho | Status |
|---|---|---|---|
| 1 | Matcher por termo inteiro (limite de letra, plural na 1ª palavra, pontuação → espaço) | `app/Services/Radar/ContestNormalizer.php` | ✅ |
| 2 | Keywords de cargo de TI | `config/radar.php` (`area_keywords`, `pci_concursos.max_detail_fetches`/`detail_delay_ms`) | ✅ |
| 3 | `.cd` do card com separador ` \| ` | `app/Services/Radar/Sources/PciConcursosSource.php` | ✅ |
| 4 | Cargos da notícia em `payload.roles` (só quando o `.cd` diz "Vários/Diversos Cargos"; reuso entre runs; teto; falha → card segue sem roles) | idem | ✅ |
| 5 | Haystack de área com `roles` + `counts.rejected_no_it` | `app/Jobs/Radar/NormalizeListingsJob.php` | ✅ |
| 6 | `DismissNonItScrapedContests` + `radar:prune-noise {--dry}` | `app/Actions/Radar/`, `app/Console/Commands/PruneNoiseCommand.php` | ✅ |
| 7 | Testes | `ContestNormalizerTest`, `PciConcursosSourceTest` (+ fixture `pci_noticia_sample.html`), `NormalizeListingsJobTest`, `DismissNonItScrapedContestsTest` | ✅ |

**Desvios**
- O separador no `.cd` muda `payload.text` e, com ele, o `content_hash`: na 1ª varredura depois do fix, cada card virou raw nova (uma vez só). É o mesmo comportamento de "texto mudou → reprocessa" que já existia. As raws antigas sem `contest_id` continuam sendo rejeitadas a cada run (`rejected_no_it` = 886 conta antigas + novas).
- `roles_fetched` não entrou em `counts`: a fonte não tem canal pra run (interface `ContestSource` genérica). Fica pra depois, se fizer falta.
- Keywords `desenvolvimento de software`/`desenvolvimento de sistemas` mantidas (especialidade real de TI, usadas nos testes do TRF).
- `dismissed_reason` é texto livre (≤ 120), não enum.

**Validação**

| Critério | Resultado | Observação |
|---|---|---|
| 1. Diagnóstico com evidência | ✅ | Fase 2 |
| 2. Tabela Pest do matcher | ✅ | 9 positivos, 8 negativos |
| 3. Fonte: roles via notícia, sem fetch em card com cargo, hash só sobre `text` | ✅ | manchete "diversos cargos" não dispara fetch |
| 4. Job: sem TI não cria; TI só em roles cria | ✅ | |
| 5. Action: poupa fixado/inscrito/seed/resultado; idempotente | ✅ | |
| 6a. `prune-noise --dry` real | ✅ | 7 listados (os outros 3 falsos positivos + HOB já tinham sido descartados à mão); nenhum seed; 2ª execução = 0 |
| 6b. `radar:sweep all --no-llm` real | ⚠️ parcial | 487 raspados, 65 matched, **57 criados** (ids 46–102), 5m25s. Todos casaram termo de cargo de TI (técnico em informática 20, tecnologia da informação 16, analista de sistemas 10…). **Amostragem manual de 20 pelo dono pendente.** Suspeitos a conferir: #48 (Marinha, "desenvolvimento de sistemas"), #63/#81/#97 (só "tecnologia da informação"), #86 ("técnico de suporte") |
| 7. Gates | ✅ | Pest 279 ✓ (2 skipped), pint ✓, phpstan 0 erros. Sem mudança de front (eslint/tsc não se aplicam) |

**Métrica:** antes, 1/11 de TI (9%). Depois, 57 novos, todos com termo de cargo de TI no texto; a precisão real fica pendente da amostragem manual.
