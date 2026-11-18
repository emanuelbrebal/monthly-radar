# Plano — Atualização dos concursos + v1 local "1 clique" (18/11/2026)

Branch: `chore/local-v1` (base `main` em `a2b67b1`, depois do merge do PR #19). Tipo: chore (dados + tooling local). Pedido do dono em 18/11/2026 via `/aprimorar` → `/plan`.

## FASE 1 — Scope Analysis

**Objetivo**
- T1: os 22 concursos visíveis no banco local com situação conferida em fonte datada; proposta de descarte e de até 5 concursos novos, aprovadas pelo dono antes de aplicar.
- T2: o dono abre o radar todo dia com um duplo clique, sem terminal, com backup automático do banco.

**Áreas afetadas**
- Dados: `database/seeders/data/contests-seed.json` (via `ContestSeeder`, sem migration).
- Tooling novo: `scripts/windows/` (launcher + parada + backup), atalho na Área de Trabalho (fora do repo).
- Docs: `README.md` (seção "uso diário local"), memória do projeto.
- Código PHP/TS: nenhum, salvo bloqueador encontrado no item 8 do checklist.

**Escopo**
- Pesquisa web da situação dos 22 visíveis (6 na maleta + 16 fora dela), fonte oficial > agregador.
- Atualizar o JSON e aplicar com `php artisan db:seed --class=ContestSeeder`.
- Lista de descarte proposta (nunca maleta) e lista de até 5 sugestões novas, separadas.
- Launcher PowerShell: Postgres → assets → backup → server + queue ocultos → navegador.
- Script de parada e backup com rotação.
- Lista de bloqueadores classificada.

**Não-escopo**
- Deploy na VPS (`docs/07-deploy.md` segue valendo, só não é agora).
- Backlog v2: cron de varredura, notificações, `.ics`, headless, PDF, mobile.
- NativePHP/Electron/instalador `.exe` (troca de stack).
- LLM/`ANTHROPIC_API_KEY` (o botão "atualizar acompanhados" continua desabilitado).
- `migrate:fresh` e delete físico de concurso.
- Mexer em `enrollment_status`/`pinned_slot` dos 6 da maleta.

**Impacto técnico**
- Dados: só `contests` + `contest_events` + `contest_changes` via seeder idempotente por slug.
- APIs/UI: nenhuma mudança.
- Infra: processos locais (`php artisan serve` na 8000, `queue:work`), dumps em pasta local.

**Critérios de aceite**
1. Tabela dos 22: nome | situação anterior | situação nova | fonte (URL) | data da verificação. Sem fonte oficial → "não confirmado".
2. `ContestSeeder` roda sem erro; `php artisan test` verde (base hoje: 285 ✓, 2 skipped).
3. Para os 6 da maleta, `pinned_slot` e `enrollment_status` iguais antes e depois do seed (conferido por consulta).
4. Lista de descarte e lista de sugestões entregues separadas, motivo de 1 linha cada. Nada descartado/adicionado sem "ok" do dono.
5. Com o server parado, duplo clique no atalho → navegador abre `http://localhost:8000` em ≤ 30 s, sem janela de terminal visível.
6. Segundo duplo clique com o app no ar → só abre o navegador (não sobe processo duplicado, não dá erro de porta).
7. Varredura pela UI sai de "varrendo…" e chega em "Varredura feita." (prova que o worker subiu).
8. A cada subida existe um `.dump` novo na pasta de backup; ficam no máximo 14; `pg_restore --list` lê o último.
9. Script de parada encerra server e worker (porta 8000 livre depois).
10. Bloqueadores em 3 grupos: impede uso diário | incomoda | pode esperar.

**Perguntas bloqueadoras**: nenhuma que impeça começar. Duas decisões de default ficam registradas como premissa (P4, P5); o dono pode trocar.

**Premissas**
- P1. Postgres 17 é serviço `Automatic` e está `Running` — o launcher só confere e tenta `Start-Service` se parado.
- P2. `.env` local tem `DB_PASSWORD`, `APP_KEY` e `RADAR_USER_*`; nenhum segredo vai para o repo nem para o plano.
- P3. PHP 8.4.11 fora do PATH padrão (`OneDrive\Documentos\php-8.4.11...`); o launcher resolve via `Get-Command php`, sem caminho fixo.
- P4. Pasta de backup default: `%USERPROFILE%\OneDrive\Backups\monthly-radar` (OneDrive dá cópia fora da máquina de graça). Configurável no topo do script. `[inferido — sem confirmação]`
- P5. `APP_ENV=local` continua. Trocar para `production` exige `config:cache` a cada mudança de `.env` e esconde erro; para uso só local, o ganho é pequeno. `[inferido]`
- P6. Área de Trabalho real: `C:\Users\manub\OneDrive\Área de Trabalho` (via `GetFolderPath('Desktop')`, não caminho fixo).

## FASE 2 — Evidence Gathering

| # | Evidência | Âncora / comando | Achado |
|---|---|---|---|
| 1 | Banco local | `Contest::count()` / `Contest::visible()` | 102 concursos, **22 visíveis**, 6 na maleta (slots 0–4 e 11: CGU, TJPB, ANPD, BACEN, IBRAM-DF, Dataprev). 74 descartados, 6 expirados não descartados. `source_checked_at` dos visíveis entre 17/09 e 15/10 — todos com mais de 30 dias |
| 2 | Seeder | `database/seeders/ContestSeeder.php:25-26,95,159,210-213` | Não sobrescreve `enrollment_status`, `pinned_slot`, descarte, `personal_notes` de quem já existe. **Descarte de concurso existente não passa pelo seed** |
| 3 | Descarte | `app/Http/Controllers/Radar/DismissController.php` (`store`) | Descarte = `enrollment_status=dismissed` + `dismissed_at` + `dismissed_reason`. Reversível (`restore`) |
| 4 | Seed JSON | `contests-seed.json` | 34 itens; campos de situação: `status`, `registration_*`, `exam_date`, `edital_url`, `source_checked_at`, `confidence`, `extra_events`, `divergences`, `gaps` |
| 5 | Subida atual | `composer.json` script `dev` | `concurrently` com `npm run dev` + `--kill-others`; exige terminal aberto e Vite. Não serve para uso diário |
| 6 | Ambiente | `Get-Service postgresql*`, `php -v`, `node -v`, `public/build/manifest.json` | PG17 Running/Automatic; PHP 8.4.11; Node 22.19; build de 11/11, `resources/js` com arquivos de 18/11 (checkout) → launcher precisa detectar build velho. Sem `public/hot` |
| 7 | Fila/sessão/backup | `.env` (`QUEUE_CONNECTION=database`, `SESSION_LIFETIME=120`); `deploy/backup.sh`; login com "lembrar de mim" | Sem worker a varredura trava em `queued`. Backup só existe para servidor (bash/cron). Sessão expira em 2 h sem "lembrar de mim" |

Gates hoje: `php artisan test` → 287 testes, 285 ✓, 2 skipped (18/11). `tsc` com o erro pré-existente em `resources/js/actions/.../RedirectController.ts`.

**Fluxo atual de uso**: abrir terminal → `composer dev` → esperar Vite → navegador manual. Dados novos: pesquisa no chat → JSON → `db:seed --class=ContestSeeder`.

**Incertezas**
- Quantos concursos terão fonte oficial nova: só se sabe na pesquisa.
- Se `php artisan serve` com `PHP_CLI_SERVER_WORKERS` funciona no Windows (sem `pcntl`); default é 1 worker, suficiente para 1 usuário.

## FASE 3 — Architecture Planning

### Bloco 1 — Como o app sobe sem terminal

| Abordagem | Corretude | Blast radius | Manutenção | Alinhamento |
|---|---|---|---|---|
| A. Launcher PowerShell sob demanda (atalho `.lnk` → `powershell -WindowStyle Hidden -File start.ps1`), processos `Start-Process -WindowStyle Hidden`, PIDs num arquivo | 5 | 5 (só a máquina do dono, nada no app) | 5 | 5 |
| B. Tarefa agendada no logon do Windows mantendo server + worker sempre no ar | 4 | 3 (processo eterno, porta presa, falha silenciosa) | 3 | 3 (beira "agendamento") |
| C. Serviço Windows via NSSM / Laragon / Herd | 4 | 3 (ferramenta externa, admin) | 3 | 2 (acoplamento externo) |

**Vence A.** Sobe quando o dono quer, morre no `stop.ps1` ou no desligamento, zero dependência nova. B descartada: processo eterno sem supervisão e cheiro de agendamento. C descartada: o dono pediu explicitamente evitar acoplamento com ferramenta externa.

### Bloco 2 — Assets e worker

| Abordagem | Corretude | Blast radius | Manutenção | Alinhamento |
|---|---|---|---|---|
| A. Build estático (`public/build`), launcher roda `npm run build` só se `resources/` for mais novo que o manifest; `queue:work` oculto junto | 5 | 4 | 5 | 5 |
| B. Manter `npm run dev` (Vite) oculto | 3 | 3 (`public/hot` órfão quebra a tela se Vite morrer) | 3 | 3 |
| C. Sem worker, `QUEUE_CONNECTION=sync` | 3 | 3 (varredura trava o request; muda comportamento testado) | 4 | 2 |

**Vence A.** O launcher também apaga `public/hot` órfão (já quebrou antes, `tasks/local-dod-audit.md`). C descartada: muda contrato da varredura assíncrona.

### Bloco 3 — Como aplicar descarte proposto

| Abordagem | Corretude | Blast radius | Manutenção | Alinhamento |
|---|---|---|---|---|
| A. Depois do "ok", descartar pela UI (botão de descarte) ou, se forem muitos, um `tinker --execute` que repete exatamente os 3 campos de `DismissController::store` com `dismissed_reason` | 5 | 5 (reversível por `restore`) | 5 | 5 |
| B. `enrollment_status: dismissed` no JSON | 1 | — | — | 0 (seeder ignora para existentes) |
| C. Delete físico | 1 | 1 | — | 0 (proibido) |

**Vence A.** Não cria código novo para uma operação pontual.

### Bloco 4 — Backup local

| Abordagem | Corretude | Blast radius | Manutenção | Alinhamento |
|---|---|---|---|---|
| A. `pg_dump -Fc` a cada subida pelo launcher, rotação de 14, pula se já existe dump das últimas 12 h | 5 | 5 | 5 | 5 (espelha `deploy/backup.sh`) |
| B. Tarefa agendada diária do Windows | 4 | 4 | 3 | 3 |
| C. Sem backup (confiar no OneDrive da pasta do projeto) | 1 | 2 (dados vivem no Postgres, não no repo) | 5 | 2 |

**Vence A.** Senha lida do `.env` para `PGPASSWORD` só no processo filho, nunca impressa.

**Validações**
- Regra de negócio na camada certa: nenhuma regra nova; launcher só orquestra processo.
- Acoplamento: zero dependência nova (PowerShell + binários já instalados).
- Reversível: apagar `scripts/windows/` e o atalho volta ao estado atual; descarte tem `restore`; seed é idempotente e há dump antes.
- Contratos: nenhuma rota, schema ou payload muda.

## FASE 4 — Execution Planning

1. **Backup antes de tudo** — `pg_dump -Fc` manual do `monthly_radar` para a pasta P4. Aceite: arquivo existe e `pg_restore --list` lê.
2. **Pesquisa dos 6 da maleta** (CGU, TJPB, ANPD, BACEN, IBRAM-DF, Dataprev) — edital, inscrição, provas, resultado, homologação; fonte oficial primeiro, URL + data em cada campo. Aceite: linha na tabela do critério 1 para cada um.
3. **Pesquisa dos 16 fora da maleta** — mesmo método. Aceite: tabela completa (22 linhas), "não confirmado" onde não houver fonte.
4. **Atualizar `contests-seed.json`** — só campos de situação (`status`, datas, `edital_url`, `registration_url`, `source_checked_at`, `confidence`, `extra_events`, `divergences`, `gaps`); validar JSON. Aceite: `node -e "require(...)"` ok e diff só nesses campos.
5. **Aplicar seed + gates** — `php artisan db:seed --class=ContestSeeder`, consulta antes/depois dos 6 da maleta, `php artisan test`. Aceite: critérios 2 e 3.
6. **Propostas para o dono** — lista de descarte (candidatos óbvios: expirados não descartados como IFPE/IFPI/UFPE/TCE-GO/Sefaz-AL, cargos "não confirmados" sem TI, fora do Nordeste sem diferencial) e até 5 novos (prioridade NE + nacionais de TI). **Pausa para aprovação.** Depois do "ok": descarte (Bloco 3A) e novos entram no JSON + seed. Aceite: critério 4.
7. **`scripts/windows/start.ps1`** — confere/sobe Postgres; apaga `public/hot`; build se velho; backup (Bloco 4A); se porta 8000 já responde → só abre navegador; senão sobe `serve` e `queue:work` ocultos com PIDs em `storage/app/local-run.json` e logs em `storage/logs/`; espera HTTP 200/302 (timeout 30 s, mensagem amigável se falhar); abre navegador. Aceite: critérios 5, 6, 8.
8. **`stop.ps1` + atalho** — `stop.ps1` mata pelos PIDs registrados (fallback: dono da porta 8000); `install-shortcut.ps1` cria "Radar Mensal" e "Parar Radar" na Área de Trabalho (P6). Aceite: critérios 7 e 9 testados à mão.
9. **Bloqueadores de uso diário** — revisar telas com o app subido pelo launcher (mesa, calendário, dossiê, maleta, arquivo, descartados, conta) e consolidar: `SESSION_LIFETIME` vs "lembrar de mim", `LOG_LEVEL=debug` crescendo `laravel.log`, 6 expirados não descartados, `source_checked_at` velho em massa, erro `tsc` pré-existente, ritual de atualização via seed. Aceite: critério 10. Bloqueador "impede uso diário" → corrigir nesta branch; os outros ficam listados.
10. **Docs + fechamento** — seção "Uso diário (Windows)" no `README.md` (subir, parar, backup, restaurar, atualizar dados via seed), registrar execução aqui, atualizar memória do projeto, commit em inglês. Aceite: README cobre os 5 tópicos; `php artisan test` verde no fim.

## FASE 5 — Verificação do Plano

- [x] Nenhuma regra de negócio na camada errada (zero lógica nova no app)
- [x] Nenhum acoplamento desnecessário (sem NSSM/Laragon/Electron)
- [x] Critérios de aceite objetivos e testáveis (10)
- [x] Checklist ≤ 10 itens
- [x] Máx. 3 abordagens por bloco, 1 escolhida com justificativa (4 blocos)
- [x] Evidências ≤ 7
- [x] Perguntas bloqueadoras: nenhuma; P4/P5 explícitas como default trocável

**Gate = Livre para execução.** Pausa obrigatória no item 6 (aprovação de descarte e de novos concursos).

## Execução (18/11/2026)

Pesquisa delegada a 4 subagents em paralelo: grupo A (maleta, 6), B (8), C (8) e sugestões novas. Fiz uma checagem cruzada à parte: a fonte do TJ-CE tinha slug `tc-ce`, mas confirmei que é o TJ-CE (FCC, prova 09/08/2026).

### Stage de Execução — Entregáveis

| # | Descrição | Caminho | Status |
|---|---|---|---|
| 1 | Backup antes de mexer | `%USERPROFILE%\OneDrive\Backups\monthly-radar\radar-20261118-1505-pre-local-v1.dump` | ✅ |
| 2–4 | Situação dos 22 visíveis pesquisada e aplicada ao JSON (formato original preservado) | `database/seeders/data/contests-seed.json` | ✅ |
| 5 | Seed aplicado | `db:seed --class=ContestSeeder` | ✅ |
| 6 | Propostas de descarte e de novos concursos | resposta da sessão | ⏸ aguarda o dono |
| 7 | Launcher | `scripts/windows/start.ps1` | ✅ |
| 8 | Parada + atalhos | `scripts/windows/stop.ps1`, `scripts/windows/install-shortcut.ps1`, atalhos na Área de Trabalho | ✅ |
| 9a | **Desvio:** bug do `closed_at` recarimbado no re-seed | `app/Services/Radar/ContestMerger.php`, `tests/Feature/Services/Radar/ContestMergerTest.php` | ✅ |
| 9b | **Desvio (pedido do dono no meio da execução):** auto-login local | `app/Http/Middleware/AutoLoginLocalUser.php`, `bootstrap/app.php`, `config/radar.php`, `.env.example`, `tests/Feature/Auth/AutoLoginLocalUserTest.php` | ✅ |
| 10 | README "Daily use on Windows" | `README.md` | ✅ |

**Desvio 9a:** o re-seed fazia o `ContestMerger::applyClosedAtRule` gravar `closed_at = now()` em concurso que já tinha fechado pela data (`closed_at` nulo e `registration_end` passado). Com isso, Sefaz-AL e TCE-GO voltaram para a mesa por mais 24h. Pela definição de `docs/01-modelo-de-dados.md:37` ("primeiro momento em que o status efetivo virou encerrado"), quem fechou pela data deve receber o fim do dia do `registration_end`. Fiz a correção, com teste que falha sem ela, e reparei 4 registros (Sefaz-AL, TCE-GO, TJ-CE, CGU). Sem isso, toda atualização via seed ressuscitaria expirados.

**Desvio 9b:** o dono pediu para tirar o login. Não removi o Fortify, porque o deploy na VPS continua no plano. Fiz um middleware com três travas: `RADAR_AUTO_LOGIN=true`, `APP_ENV=local` e IP de loopback. Ele fica antes de `auth`/`guest` na lista de prioridade. O "sair" perde efeito com a flag ligada.

**Item 9, revisão das telas:** a automação de navegador não tinha sessão e eu não digito a senha real do dono. A cobertura vem dos testes de Feature e do `GET /` → 200 com auto-login ligado. A revisão visual fica com o dono.

### Stage de Execução — Validação

| Critério | Resultado | Observação |
|---|---|---|
| 1. Tabela dos 22 com fonte e data | ✅ | IFAL e UFAL ficaram "não confirmado" (sites não carregaram); maioria das fontes é agregador |
| 2. Seed sem erro + testes verdes | ✅ | Pest 292 ✓ / 2 skipped; pint ✓; phpstan 0 erros (precisa `--memory-limit=1G`) |
| 3. Maleta intacta | ✅ | `pinned_slot`/`enrollment_status` dos 6 iguais antes/depois (diff vazio) |
| 4. Descarte e novos só com "ok" | ⏸ | Propostas entregues; nada aplicado |
| 5. Subida a frio ≤ 30 s sem janela | ✅ | 20 s com rebuild do front; 5 s sem rebuild |
| 6. Segunda subida só abre navegador | ✅ | 1 s, sem processo duplicado |
| 7. Fila processa | ✅ | job de teste consumido pelo worker em < 1 s (varredura real não rodada para não sujar o banco antes da curadoria) |
| 8. Backup + rotação + legível | ✅ | `radar-20261118-1508.dump`, `pg_restore --list` = 144 entradas; pula se < 12 h |
| 9. Stop libera a porta | ✅ | porta 8000 livre, 0 processos php |
| 10. Bloqueadores classificados | ✅ | ver abaixo |

### Bloqueadores de uso diário

| Grupo | Item |
|---|---|
| Impede uso diário | nenhum restante (launcher, fila, backup e login resolvidos nesta branch) |
| Incomoda | "sair" sem efeito com auto-login; `LOG_LEVEL=debug` faz `laravel.log` crescer sem rotação (trocar para `LOG_STACK=daily`); maioria das fontes é agregador, então a confiança é média; atualização de dados depende de pesquisa no chat |
| Pode esperar | erro `tsc` pré-existente em `resources/js/actions/.../RedirectController.ts`; phpstan precisa de `--memory-limit=1G` no Windows; botão "atualizar acompanhados" desabilitado sem API key (esperado) |

### Item 6 — decisão do dono (18/11/2026)

- **Descartados** (pelos mesmos campos do botão de descarte, com motivo): MPU, TCE-SE, TJ-PE, TRF3.
- **Entraram no seed:** Rede SARAH (Analista de TI, Edital 13/2026) e SEAD-MA (STC, Auditor de TI, FCC). O TRT-5 ficou de fora.
- **IFAL e UFAL viraram esqueleto:** saiu a faixa salarial presumida e o título de cargo presumido. Ficaram `confidence=unconfirmed` e lacunas explícitas (cargo, vagas, banca, datas e remuneração a confirmar quando sair o edital TAE).
- Resultado: 19 concursos na mesa, maleta intacta, Pest verde.
