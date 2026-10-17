# Plano — Auditoria de DoD e subida local (13/10/2026)

Branch: `chore/local-dod-audit` (base `main`, working tree limpa na criação). Tipo: chore (infra/docs/tooling). Nenhum código alterado nesta fase.

## FASE 1 — Scope Analysis

**Objetivo**: o Emanuel abrir o app em `localhost` hoje, logar e usar (mesa, calendário, dossiê, maleta, descarte, varredura) com um único comando de subida.

**Áreas afetadas**: `.env` (local, não versionado), `php.ini` do PHP local (fora do repo), `composer.json` (script `dev`), `tests/Feature/Console/SweepCommandTest.php`, `README.md`, `docs/04-plano-execucao.md` e `docs/05-seed-inicial.md` (contagens do DoD).

**Escopo**
- Fazer o banco local (Postgres 17) conectar e `migrate:fresh --seed` rodar.
- Corrigir SSL do PHP local para `radar:source`/`radar:sweep` funcionarem em rede real.
- Deixar `php artisan test` verde sem depender de rede.
- Um comando de subida que funcione no Windows (server + queue + vite).
- Alinhar DoD escrito (27 → 28 concursos) com o seed real.

**Não-escopo (fora de hoje)**: deploy no VPS; Backlog v2 (varredura agendada, notificações, .ics, headless, PDF, mobile, itens de tamanhos diferentes, anotações); nova fonte de scraping; preencher `official_pages.pages`; teste real do `AnthropicProvider` (exige `ANTHROPIC_API_KEY`); limpeza dos artefatos `legacy/` do Radar Hub; remover toggle de aparência morto.

**Perguntas bloqueadoras**
1. **Senha do usuário `postgres` local.** `DB_PASSWORD` está vazio no `.env` e o Postgres recusa (`fe_sendauth: no password supplied`). Não adivinho. Precisa do usuário informar a senha (ou autorizar criar uma role dedicada `radar`). Fallback validado hoje: SQLite de arquivo.
2. ~~Quer usar `ANTHROPIC_API_KEY` hoje?~~ **Respondida (13/10): sem API key.** App roda em modo `skipped`, custo zero. Dados novos vêm de pesquisa no chat do Claude, devolvida como `contests-seed.json` e aplicada com `php artisan db:seed --class=ContestSeeder` (idempotente; nunca `migrate:fresh` depois da primeira vez). Tudo local por enquanto.

**Premissas**: Windows 11, PowerShell/Git Bash; PHP 8.4.11 local (projeto exige ^8.3); Node 22.19; banco `monthly_radar` pode ainda não existir.

## FASE 2 — Evidence Gathering

| # | Evidência | Âncora / comando | Achado |
|---|---|---|---|
| 1 | Postgres | `Get-Service` → `postgresql-x64-17 Running`; `php artisan db:show` | Roda, mas exige senha; `.env` `DB_PASSWORD=` vazio |
| 2 | Testes | `php artisan test` | 169 testes, 165 ok, **2 falham** (`SweepCommandTest.php:33,48`) |
| 3 | Causa dos 2 testes | `SweepCommand.php:78` + `PciConcursosSource.php:41` | Teste não isola HTTP → bate no site real → erro SSL → run `Failed` → exit 1 |
| 4 | SSL | `php.ini:1820,1829` (`curl.cainfo`/`openssl.cafile` comentados) | Cadeia do site é legítima (Sectigo; ok no `curl` Schannel). Com `cacert.pem` via `-d`, `radar:source pci_concursos --dry` **funcionou** (itens reais) |
| 5 | Seed | `contests-seed.json` + `migrate:fresh --seed` em SQLite temporário | **28** concursos (docs dizem 27): 20 visíveis, 5 na maleta, 4 descartados, 4 fora pela regra das 24h |
| 6 | Subida | `composer.json` script `dev`; `php -m` sem `pcntl`; `public/hot` → `http://[::1]:5176` | `pail` não roda no Windows e `--kill-others` derruba tudo; `hot` obsoleto aponta para dev server inexistente |
| 7 | Gates | pint ✅, phpstan ✅ (0 erros), eslint ✅, `tsc` ❌ (3 erros), prettier ❌ (21 arquivos) | `tsc`: só em `resources/js/actions/.../RedirectController.ts` (gerado, ignorado pelo git, método `"query"`) |

Outros fatos: `.env` tem `APP_KEY` e `RADAR_USER_*` preenchidos; Fortify sem `registration()`; `UserSeeder` pula se e-mail/senha vazios; `QUEUE_CONNECTION=database` (varredura pela UI precisa de `queue:work`); `public/build` existe (14:31 de hoje).

## FASE 3 — Architecture Planning

### Bloco 1 — Banco local
| Abordagem | Corretude | Blast radius | Manutenção | Alinhamento |
|---|---|---|---|---|
| A. Senha no `.env` (usuário informa) | 5 | 5 | 5 | 5 |
| B. Role dedicada `radar` + banco `monthly_radar` | 5 | 4 | 5 | 5 |
| C. SQLite de arquivo | 4 | 5 | 4 | 2 (spec manda Postgres) |

**Vence A** (ou B se o usuário preferir não reusar `postgres`). C fica como fallback registrado, já validado hoje. Descartado: mexer em `pg_hba.conf` para `trust` (enfraquece o Postgres da máquina inteira).

### Bloco 2 — SSL do PHP local
| Abordagem | Corretude | Blast radius | Manutenção | Alinhamento |
|---|---|---|---|---|
| A. `cacert.pem` + `curl.cainfo`/`openssl.cafile` no `php.ini` | 5 | 4 (afeta PHP global) | 5 | 5 |
| B. `withOptions(['verify'=>false])` no adapter | 1 | 1 | 1 | 0 |
| C. Opção `verify` configurável por env | 3 | 3 | 2 | 2 |

**Vence A**. B e C descartadas: desligar verificação no código é exatamente o que o critério proíbe. O `php.ini` fica fora do repo, então o procedimento vai documentado no README.

### Bloco 3 — Testes sem rede
**Vence**: `Http::preventStrayRequests()` + `Http::fake()` com HTML das fixtures existentes (`tests/Fixtures/pci_concursos_sample.html`) no `SweepCommandTest`. Descartado: pular os testes sem rede (esconde o problema) e consertar só o SSL (teste continuaria dependente de internet e do site no ar).

### Bloco 4 — Subida em um comando no Windows
| Abordagem | Corretude | Blast radius | Manutenção | Alinhamento |
|---|---|---|---|---|
| A. `composer dev` sem `pail` (server + `queue:listen` + vite) | 5 | 5 | 5 | 5 |
| B. Script PowerShell `scripts/dev.ps1` | 4 | 5 | 3 | 3 |
| C. Manter e instalar `pcntl` | 1 (inexistente no Windows) | — | — | — |

**Vence A**: editar o script `dev` do `composer.json` removendo o `pail` (logs ficam em `storage/logs/laravel.log`). Também remover o `public/hot` obsoleto antes do primeiro start (o Vite recria o arquivo).

## FASE 4 — Execution Planning (checklist ≤10, ordem de dependência)

1. **[Bloqueia uso]** Usuário informa a senha do Postgres (ou autoriza role `radar`). Criar o banco `monthly_radar` se não existir (`CREATE DATABASE`, não destrutivo). Gravar no `.env`.
2. **[Bloqueia uso]** `php artisan migrate:fresh --seed` no Postgres. Só rodar depois de confirmar que `monthly_radar` está vazio ou é descartável (comando destrutivo).
3. **[Bloqueia varredura real]** Baixar `cacert.pem` (curl.se) para pasta fixa fora do repo, descomentar e apontar `curl.cainfo` e `openssl.cafile` no `php.ini`; reabrir o terminal; validar com `php artisan radar:source pci_concursos --dry`.
4. **[Bloqueia suíte verde]** Isolar rede no `SweepCommandTest` (Bloco 3). `php artisan test` → 169/169 (+2 skipped já existentes).
5. **[Bloqueia "um comando"]** Editar script `dev` (Bloco 4) e apagar `public/hot`. Validar `composer dev`: server em `localhost:8000`, worker de fila e Vite de pé.
6. **[Incômodo]** Atualizar contagens de DoD: `docs/04-plano-execucao.md` ("27 concursos: 19 na mesa, 6 na maleta" → "28: 20 na mesa, 5 na maleta, 4 descartados, 4 fora pela regra das 24h") e `docs/05-seed-inicial.md:3`.
7. **[Incômodo]** `tsc`: regenerar wayfinder (`php artisan wayfinder:generate`) e ver se os 3 erros somem; se persistirem, registrar como pendência (arquivo gerado, não versionado). Rodar `prettier --write resources/` só se o usuário aprovar (21 arquivos, diff grande e fora do foco).
8. **Verificação manual no browser (DoD)**: login com `RADAR_USER_*` → mesa com 20 itens; maleta com 5 pinados; fixar/desafixar/reordenar (mouse e teclado); descartar/restaurar; marcar "inscrito"; filtro de estados; **Varrer** com a área `development` e fila rodando (sem chave → `enrichment_status=skipped`, sem tela quebrada); varrer 2× sem duplicar; custo em `search_runs.meta`. Drag-and-drop é teste humano (automação não dispara HTML5 DnD).
9. **README**: seção "Local on Windows" (cacert, `composer dev`, senha do Postgres, `queue` obrigatório para varrer pela UI).
10. Commit atômico por tema (inglês, Conventional Commits) e PR para `main` só quando o usuário pedir.

### Sequência de comandos do clone limpo à tela logada (PowerShell)
```powershell
git clone https://github.com/emanuelbrebal/monthly-radar; cd monthly-radar
composer install; npm install
Copy-Item .env.example .env; php artisan key:generate
# editar .env: DB_PASSWORD, RADAR_USER_EMAIL, RADAR_USER_PASSWORD
psql -U postgres -c "CREATE DATABASE monthly_radar"   # psql não está no PATH: usar C:\Program Files\PostgreSQL\17\bin\psql.exe
php artisan migrate:fresh --seed
composer dev                                          # após item 5; abrir http://localhost:8000
```

### Checklist de DoD (estado em 13/10/2026)
| Critério (`docs/04`) | Estado | Evidência |
|---|---|---|
| Seed sobe tela com concursos (nº) | ✅* | SQLite: 28 / 20 visíveis / 5 maleta / 4 desc. / 4 fora (*doc diz 27; atualizar) |
| Sem chave de LLM não quebra | ✅ (teste) / ⚠ real | `radar:sweep --no-llm` só falha por SSL/rede |
| Regra 24h, descarte, inscrito, maleta 12 slots, pin único | ✅ (Pest) | 165 testes verdes; DnD manual pendente |
| Varrer 2× não duplica | ⚠ | Coberto por testes sintéticos; confirmar com rede real após item 3 |
| Visual (tokens, radius 0) | ⚠ | Confirmar no browser (item 8) |
| Banco Postgres real | ❌ | Item 1–2 |
| Varredura real (SSL) | ❌ → ✅ após item 3 | Validado hoje com bundle temporário |
| `php artisan test` verde | ❌ 165/169 | Item 4 |
| Subida com um comando | ❌ | Item 5 |

## FASE 5 — Verificação do Plano

- [x] Nenhuma regra de negócio mexida (só config, teste, script, docs)
- [x] Sem acoplamento novo
- [x] Critérios testáveis (comandos acima)
- [x] Checklist ≤ 10 itens
- [x] Máx. 3 abordagens por bloco, 1 vencedora
- [x] Evidências rastreáveis
- [x] Perguntas bloqueadoras explicitadas (senha do Postgres; chave Anthropic)
- [x] Backlog v2 e deploy listados como fora do escopo

**Gate = Livre para execução**, condicionado à resposta da pergunta 1 (itens 1–2). Itens 3–7 e 9 podem rodar antes.

## Stage de Execução — Entregáveis (13/10/2026)

Executados só os itens sem dependência da senha do Postgres. Sem API key (decisão do usuário). Sem commit (aguarda pedido).

| # | Item do plano | Descrição | Caminho | Status |
|---|---|---|---|---|
| 3 | CA bundle + php.ini | `cacert.pem` (Mozilla, 25/09/2026, 121 certs) em pasta fixa; `curl.cainfo` e `openssl.cafile` ativados. Backup: `php.ini.bak-pre-cacert` | `C:\Users\manub\.php-certs\cacert.pem`; `C:\Users\manub\OneDrive\Documentos\php-8.4.11-Win32-vs17-x64\php.ini` (fora do repo) | ✅ |
| 4 | Testes sem rede | `Http::preventStrayRequests()` + fake de `pciconcursos.com.br` e `*.gov.br` com as fixtures existentes | `tests/Feature/Console/SweepCommandTest.php` | ✅ |
| 5 | Subida em um comando | Script `dev` sem `pail` (server + queue + vite); `public/hot` obsoleto removido (ignorado pelo git) | `composer.json` | ✅ |
| 6 | Contagens do DoD | 27 → 28 concursos; 19/6 → 20 na mesa / 5 na maleta | `docs/04-plano-execucao.md:87`, `docs/05-seed-inicial.md:3` | ✅ |
| 9 | README Windows | Seção "Local on Windows" (senha do Postgres, cacert, `composer dev`, `public/hot`, atualizar seed, sem API key) | `README.md` | ✅ |
| 1, 2 | Banco Postgres | Aguardam senha | — | ⏳ bloqueado |
| 7 | tsc / prettier | Não executado (prettier = diff grande, pede aprovação; tsc: 3 erros em arquivo gerado) | — | ⏳ |
| 8 | Verificação no browser | Depende do banco | — | ⏳ |
| 10 | Commits | Aguarda pedido | — | ⏳ |

**Desvios do plano**
- `composer.json` ganhou também o script `dev:logs` (tail de `storage/logs/laravel.log`), não previsto: substitui o que o `pail` dava e é a referência usada no README.
- Durante a execução o repo foi levado para `main` e recebeu o PR #6 (`ContestSeeder` re-executável + `ContestSeederTest`). Movi o ponteiro da branch `chore/local-dod-audit` para `main` (ela não tinha commits próprios) e voltei para ela; as alterações não commitadas foram preservadas. O plano continua válido: o `ContestSeeder` já faz o que a seção de README descreve.
- O teste de integridade do JSON real do seed (slugs únicos, slots 0–11, enums, contagem) **não** foi feito: não confirmado pelo usuário.

## Stage de Execução — Validação

| Critério de aceite | Resultado | Observação |
|---|---|---|
| 2. `php artisan test` verde | ✅ | 175 testes: 173 passam, 2 skipped (já existentes). Antes: 165/169 com 2 falhas |
| Testes isolados da rede | ✅ | `SweepCommandTest` passa também com `curl.cainfo`/`openssl.cafile` apontando para caminho inexistente |
| 4. SSL do `radar:source --dry` | ✅ | `php artisan radar:source pci_concursos --dry` raspa itens reais sem flags `-d`; verificação de certificado mantida |
| Item 5: `composer dev` sobe no Windows | ✅ | Server `localhost:8000`, queue worker e Vite 5173 de pé juntos; `/login` responde 500 só por falta de senha do Postgres (`fe_sendauth`, em `storage/logs/laravel.log`). Processos encerrados e portas liberadas |
| `composer validate` | ✅ | `composer.json` válido |
| pint | ✅ | passou |
| phpstan | ⚠ | passou com `--memory-limit=1G`; com o padrão (128M) uma execução paralela estourou memória. Não alterei `phpstan.neon` (fora do escopo) |
| 3. Sequência clone limpo → tela logada | ⏳ | Documentada no plano e no README; execução ponta a ponta depende do banco |
| 5. Backlog v2 / deploy fora do escopo | ✅ | Nada implementado |
| 1. Checklist DoD com ✅/❌ | ✅ | Tabela na Fase 4; atualizar após itens 1, 2 e 8 |

**Próximo**: senha do Postgres → itens 1 e 2 (`CREATE DATABASE`, `migrate:fresh --seed` em banco vazio) → item 8 (verificação no browser).
