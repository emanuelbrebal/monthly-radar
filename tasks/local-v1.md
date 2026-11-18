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

## Execução

_(preencher no `/exec`)_
