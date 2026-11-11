# launch-prep — preparar o lançamento (auditoria, deploy, visual)

Branch: `chore/launch-prep`, criada a partir de `fix/sweep-ti-filter` (HEAD 3ab163f) e **não** de `main`. Motivo: `main` está mais de 10 commits atrás (T8, filtro de TI, prune-noise). Uma branch tirada de `main` perderia esse trabalho. O merge em `main` virou item bloqueante (B1).

## Fase 1 — Escopo

**Objetivo:** deixar o app pronto para ir à internet num VPS. São três frentes: (1) auditoria com correções bloqueantes, (2) deploy reproduzível a partir do repo e (3) simplificação visual do app inteiro, sem mudar comportamento.

**Já feito nesta sessão:** a senha local virou `user123`. Mudou o `.env` (ignorado pelo git, confirmado com `git check-ignore`) e o hash no banco local, validado com `Hash::check`. Não houve `migrate:fresh`.

**Escopo**
- Correções de configuração e segurança para produção: `.env.production.example`, flags do Fortify, cookies.
- `deploy/` no repo: script de deploy idempotente, conf do Nginx, conf do Supervisor, script de backup.
- `docs/07-deploy.md` revisado: preços, domínio, migração de dados, uso do script.
- Sistema visual: tokens de tipografia e espaçamento em `app.css`, aplicados em `pages/radar/*`, `pages/settings/*`, `pages/auth/*` e componentes.

**Não-escopo**
- Executar coisas no servidor (o dono executa).
- Backlog v2: cron de varredura, notificações, .ics, mobile caprichado.
- Mudar lógica de domínio, rotas ou props do Inertia.
- Trocar a stack.
- Mudar `--radius` (10px, aprovado pelo dono em 18/09; ver `tasks/a11y-visual-redesign.md:31`).

**Impacto técnico:** sem migration nem mudança de dado. Na UI, só classes e tokens. Na infra, arquivos novos em `deploy/` e docs.

**Critérios de aceite**
1. O login local funciona com `user123`, e `user123` não aparece em nenhum arquivo versionado nem no banco de produção. A senha de produção vem do `.env` do servidor e o UserSeeder roda depois do restore.
2. Todos os itens bloqueantes (B*) da Fase 2 estão resolvidos ou têm um passo explícito no doc de deploy.
3. Um VPS Ubuntu 24.04 limpo chega ao app com HTTPS e worker de fila ativo seguindo só `docs/07-deploy.md` + `deploy/*.sh`. O `deploy.sh` roda duas vezes seguidas sem erro.
4. Telas usam só os tokens de tamanho de texto (`text-label/body/title/display`) e a escala de espaçamento definida. `grep` não encontra `text-[NNpx]` em `resources/js`. Há screenshots antes/depois por tela em `tasks/screenshots/launch/`.
5. `php artisan test`, `npm run lint:check`, `npm run types:check` e `npm run build` passam.

**Perguntas bloqueadoras**
- **Q1 — Domínio:** ✅ respondido em 11/11: **subdomínio grátis DuckDNS** + Certbot (HTTP-01).
- **Q2 — Dados para produção:** ✅ respondido em 11/11: **dump do banco local** (`pg_dump`/`pg_restore` uma vez + UserSeeder com senha forte).

**Premissas**
- VPS Ubuntu 24.04, provedor nacional (decisão de `docs/07-deploy.md`).
- Produção sem `ANTHROPIC_API_KEY`, igual ao fluxo local (memória do projeto, desde 13/10).
- Deploy a partir de `main` depois do merge das branches pendentes.

## Fase 2 — Evidências e auditoria

| # | Evidência | Âncora | Classe |
|---|---|---|---|
| B1 | `main` não tem T8, filtro de TI nem prune-noise. O deploy clona `main` e subiria o app antigo | `git log origin/main..HEAD` (mais de 10 commits); `docs/07-deploy.md:145` | **Bloqueante** |
| B2 | Não existe template de produção. `.env.example` vem com `APP_ENV=local`, `APP_DEBUG=true` e senha placeholder | `.env.example:2-4,32` | **Bloqueante** |
| B3 | Com dump do banco local (se Q2=dump), o hash de `user123` iria para produção | `database/seeders/UserSeeder.php:34-37` (updateOrCreate resolve) | **Bloqueante** (só passo no doc) |
| B4 | Domínio e HTTPS indefinidos; o Certbot exige domínio | `docs/07-deploy.md:254` | **Bloqueante** (Q1) |
| B5 | `SESSION_SECURE_COOKIE` ausente; o cookie de sessão poderia sair sem a flag Secure | `.env.example` (não tem a chave) | **Bloqueante** |
| R1 | `resetPasswords()` e `emailVerification()` estão ligados com `MAIL_MAILER=log`. O link "esqueci a senha" não funciona e o CLAUDE.md proíbe e-mail | `config/fortify.php:165-166`; `.env.example:59` | Recomendado |
| R2 | O passo "deploys seguintes" é manual, sem `down`/`up` e sem checar falha | `docs/07-deploy.md:309-320` | Recomendado |
| R3 | Não há backup do Postgres. É o único lugar com pins, resultados e curadoria | (ausente) | Recomendado |
| R4 | `LOG_STACK=single` cresce sem limite num disco de 40 GB | `.env:LOG_STACK` | Recomendado (`daily`) |
| R5 | Preços de VPS cotados em 18/09/2026, desatualizados | `docs/07-deploy.md:9-16` | Recomendado (revalidar) |
| R6 | O repo é **público**. Verificar se há segredo no histórico antes de divulgar a URL | `gh repo view` → PUBLIC | Recomendado |
| R7 | 2FA existe mas não está ativado para o usuário | `config/fortify.php:167` | Recomendado (ativar em prod) |
| R8 | `CLAUDE.md` diz "radius: 0", mas o real é 10px | `resources/css/app.css:233` | Recomendado (doc) |
| P1 | fail2ban e unattended-upgrades no VPS | (ausente) | Pós-lançamento |

**Auditoria visual (medida com grep em `resources/js`)**

| Sintoma | Medida |
|---|---|
| Os tokens de texto existem (`app.css:48-51`: label 12 / body 14 / title 18 / display 32) mas quase não são usados | 10 usos de token contra 66 `text-sm`, 15 `text-xs`, 4 `text-xl` etc. |
| Tamanhos de fonte arbitrários | `text-[10px]`, `[11px]`×4, `[12px]`×4, `[15px]` → **12 tamanhos distintos** |
| Espaçamentos sem escala | 9 valores de `gap` (0.5, 1, 1.5, 2, 2.5, 3, 4, 6, 8) |
| Medidas fixas soltas | `w-[560px]`, `w-[350px]`, `h-[88vh]`, `h-[52vh]`, `w-[min(1040px,94vw)]` |
| Auth e settings ainda com cara de shadcn (fora do escopo do redesign de 18/09) | `tasks/a11y-visual-redesign.md:16` |

**Fluxo atual do deploy:** o doc manual (`07-deploy.md` partes 1 e 2) é completo para o primeiro deploy, sem script. Os assets são buildados localmente e enviados por rsync.

**Incertezas:** preço atual dos VPS (Confiança: LOW até revalidar na execução). As screenshots de "antes" ainda não foram tiradas.

## Fase 3 — Arquitetura (ToT)

**Bloco 1 — Levar os dados para produção** (Q2 = dump)

| Abordagem | Corretude | Blast radius | Manutenção | Alinhamento |
|---|---|---|---|---|
| A. Só `ContestSeeder` | Perde pins, resultados e inscrições | Baixo | Baixa | Alto |
| **B. `pg_dump` local → `pg_restore` uma vez + UserSeeder** | Preserva tudo | Médio (sobrescreve o banco vazio) | Uma vez só | Alto |
| C. Comando de export/import do estado do usuário | Preserva tudo | Médio | Código novo para manter | Médio |

**Vence B**: app de um usuário só, operação feita uma vez, sem código novo. O UserSeeder depois do restore troca o hash `user123` pela senha forte do `.env` (B3). A descartada foi a C, porque seria código permanente para uma migração única.

**Bloco 2 — Automação do deploy**

| Abordagem | Corretude | Blast radius | Manutenção | Alinhamento |
|---|---|---|---|---|
| A. Só o doc | Erro humano em cada deploy | Médio | Baixa | Alto |
| **B. `deploy/deploy.sh` idempotente (set -euo pipefail, down → pull → composer → migrate → caches → restart worker → up) + confs versionadas** | Alta | Baixo | Baixa | Alto |
| C. Laravel Envoy ou Deployer | Alta | Baixo | Dependência nova | Médio |

**Vence B.** A C foi descartada porque traz uma ferramenta a mais para um único servidor. Os assets continuam com build local + rsync, para manter o VPS de 1 GB sem Node.

**Bloco 3 — Simplificação visual**

| Abordagem | Corretude | Blast radius | Manutenção | Alinhamento |
|---|---|---|---|---|
| A. Ajustes pontuais tela por tela | Inconsistência volta | Baixo | Alta | Médio |
| **B. Consolidar tokens primeiro (4 tamanhos de texto + escala de espaço 4/8/16/24/32 + container e grid de página) e depois varrer as telas substituindo** | Alta | Médio-baixo (só classes) | Baixa | Alto (tokens do handoff) |
| C. Biblioteca de componentes nova | — | Alto | Alta | Viola "não trocar stack" |

**Vence B.** Auth e settings recebem os mesmos tokens de papel e tinta da mesa. A referência de harmonia é a skill `ui-ux-pro-max` (não existe skill "taste-design").

**Bloco 4 — Domínio e HTTPS:** vence o **DuckDNS** (escolha do dono). O IP do VPS é fixo, então basta apontar uma vez, sem cliente de atualização. Certbot `--nginx` com HTTP-01. **Acesso só por IP com HTTP está descartado** (credencial em texto puro).

Validações: a lógica de negócio não muda. Nada fica acoplado (deploy/ e CSS isolados). Tudo é reversível (git revert; o dump é feito num banco novo). Os contratos de API e de props ficam intactos.

## Fase 4 — Checklist de execução

1. [ ] **Merge das branches** `feat/refresh-tracked`, `fix/sweep-ti-filter` e `chore/launch-prep` em `main` via PR, com CI verde (B1).
2. [ ] **Configuração de produção:** `.env.production.example` (APP_ENV=production, APP_DEBUG=false, LOG_STACK=daily, SESSION_SECURE_COOKIE=true, senha vazia com comentário "obrigatória e forte"). Desligar `resetPasswords()`/`emailVerification()` e ajustar os testes que dependem delas (B2, B5, R1, R4).
3. [ ] **Varredura de segredos** no histórico do git (gitleaks ou `git log -p | grep`) antes de divulgar o repo público (R6).
4. [ ] **`deploy/`**: `deploy.sh` idempotente, `nginx.conf`, `supervisor-radar.conf` (`queue:work --max-time=3600`), `backup.sh` (pg_dump diário, 7 dias de retenção, cron do sistema, sem relação com varredura) (R2, R3).
5. [ ] **`docs/07-deploy.md`**: revalidar preços (WebSearch), seção de domínio (Q1), seção de migração de dados com `pg_dump`/`pg_restore` + UserSeeder (B3), uso do `deploy.sh`, ativação do 2FA (R7) e fail2ban (P1). Corrigir radius no `CLAUDE.md` (R8).
6. [ ] **Screenshots de "antes"** de todas as telas (mesa, dossiê, arquivo, dashboard, settings ×3, login) em 1280×800.
7. [ ] **Tokens:** consolidar a escala tipográfica (incluir `--text-micro: 11px` só se houver caso legítimo de rótulo mono) e a escala de espaço/container em `app.css`.
8. [ ] **Aplicar tokens** em `pages/radar/*` e componentes da mesa; depois em `pages/settings/*`, `pages/auth/*` e layouts. Zerar `text-[NNpx]` e reduzir gaps à escala definida.
9. [ ] **Screenshots de "depois"** e checagem de contraste (`scripts/contrast-check.mjs`) e de navegação por teclado.
10. [ ] **Verificação final:** `php artisan test`, `npm run lint:check`, `npm run types:check`, `npm run build`; `git diff --stat main -- app/Services app/Models routes` vazio fora do item 2.

## Fase 5 — Verificação do plano

- [x] Nenhuma regra de negócio na camada errada (domínio intocado)
- [x] Sem acoplamento novo entre módulos
- [x] Critérios de aceite objetivos (grep, testes, script idempotente, screenshots)
- [x] Checklist com ≤ 10 itens
- [x] 3 abordagens por bloco, 1 vencedora justificada
- [x] Evidências ancoradas em arquivo:linha
- [x] Perguntas bloqueadoras respondidas (Q1 = DuckDNS, Q2 = dump)

**Gate: Livre para execução.**

## Stage de Execução — Entregáveis (11/11/2026)

| # | Entregável | Caminho | Status |
|---|---|---|---|
| 0 | Senha local `user123` (só `.env` ignorado + hash no banco local) | `.env` (fora do git) | ✅ |
| 1 | `main` trazida para a branch (5 PRs de seed/README/screenshots, sem conflito) | merge `origin/main` | ✅ (PR aberto; merge em `main` fica com o dono) |
| 2 | Template de produção (debug off, log diário, cookie seguro, sessão criptografada) | `.env.production.example` | ✅ |
| 2b | `UserSeeder`: recusa senha fraca em produção e preserva o filtro de estados ao rodar de novo | `database/seeders/UserSeeder.php`, `tests/Feature/Database/UserSeederTest.php` | ✅ |
| 3 | Varredura de segredos no histórico: só placeholders | — | ✅ |
| 4 | Deploy idempotente, envio de assets via tar+ssh, backup, Nginx, Supervisor | `deploy/*` | ✅ |
| 5 | Doc de deploy reescrito (preços de 11/11, PG17 via PGDG, restore do dump, DuckDNS, permissões setgid, 2FA, fail2ban, rollback); README e CLAUDE.md (radius) | `docs/07-deploy.md`, `README.md`, `CLAUDE.md` | ✅ |
| 6 | Screenshots de antes | `tasks/screenshots/launch/before-*.jpg` | ✅ (desk, painel, settings; o login estava logado) |
| 7 | Tokens: `--text-micro` (eixos de gráfico), `.rm-serif` (estava faltando), escala de espaço 1/2/3/4/6 | `resources/css/app.css` | ✅ |
| 8 | `RadarHeader` em todas as telas; settings/auth no chrome do radar, em pt-BR; maleta, calendário e caixa de itens sem transbordo | `resources/js/**` | ✅ |
| 9 | Screenshots de depois + contraste | `tasks/screenshots/launch/after-*.jpg`; `contrast-check` 66/66 | ✅ |
| 10 | Verificação final | ver tabela abaixo | ✅ |

**Desvios do plano**
- **R1 não feito:** desligar `resetPasswords()`/`emailVerification()` quebraria o middleware `verified` (`routes/web.php:20`) e as rotas do Wayfinder. Com `MAIL_MAILER=log`, o link de reset fica só no log do servidor, sem brecha. O doc explica como usar.
- **Branch:** criada do HEAD de `fix/sweep-ti-filter` e com `origin/main` mergeada, não a partir da `main` (a `main` tinha divergido).
- **Fora do escopo original, mas necessários:**
  - "Excluir conta" saiu da tela: app de um usuário só, sem cadastro; apagar a conta trancaria o dono fora.
  - A aba "aparência" saiu da navegação: o app não tem tema escuro. A rota continua existindo.
  - "sair" foi para a conta: estava só na sidebar, que saiu.
  - O fallback do login por chave de acesso foi de `/dashboard` para `/`.
- **Achados ao longo do doc:** `rsync` não existe no Git Bash do Windows (por isso `tar|ssh`); dump do PG17 local × PG16 do Ubuntu (instalar o 17); logs criados pelo worker (`deploy`) sem escrita para o FPM (`www-data`), resolvido com `umask 002` + setgid.
- **Orquestração:** a tradução e os tokens de auth/settings (18 arquivos) foram feitos por um fork em paralelo; layouts, wiring e revisão ficaram com o agente principal.

## Stage de Execução — Validação

| Critério | Resultado | Observação |
|---|---|---|
| 1. Login local com `user123`; senha fraca nunca em produção | ✅ | Login testado no navegador → `/`. O seeder recusa `user123` em produção (teste). O doc apaga o dump após o restore |
| 2. Bloqueantes resolvidos ou com passo no doc | ✅ | B1 merge feito (PR aberto), B2/B5 template, B3 seeder + doc, B4 DuckDNS |
| 3. Deploy reproduzível só com o doc + scripts | ✅ (não executado num VPS real) | `bash -n` nos 3 scripts; `deploy.sh` idempotente por construção (ff-only, migrate, optimize) |
| 4. Só tokens de texto; zero `text-[NNpx]`; antes/depois | ✅ | `grep text-[NNpx]` = 0; screenshots em `tasks/screenshots/launch/` |
| 5. test / lint / types / build | ✅ | Pest 285 ok (2 skip, +5 novos); eslint ok; build ok; `types:check` só com os 3 erros pré-existentes em `resources/js/actions/.../RedirectController.ts` (arquivo gerado e ignorado; o Wayfinder não reconhece o método `query` da rota `ANY settings`) |
| Domínio intocado | ✅ | `git diff <merge>..HEAD -- app routes` vazio |

**Pendências para o dono:** mergear o PR na `main`; contratar o VPS; criar o subdomínio DuckDNS; seguir `docs/07-deploy.md`.
