# Plano — T7: Dataprev de volta, slot fantasma e Arquivo (17/10/2026)

Branch: `feat/contest-archive`, empilhada em `feat/contest-change-log` (etapa 1 da T7, commitada em 17/10, sem push). Tipo: feat + fix.

## FASE 1 — Scope Analysis

**Objetivo**: nenhum concurso que eu acompanho some sem rastro. O Dataprev volta pra mesa/maleta; expirado nunca segura slot; tudo que saiu da mesa fica consultável numa página "Arquivo".

**Áreas afetadas**: banco local (1 linha), `RadarPageController`, `Contest` (scope novo), rota + controller + página Inertia do arquivo, `docs/00-spec.md`, `docs/01-modelo-de-dados.md`, `docs/03-ui-inventario.md`, `docs/04-plano-execucao.md`.

**Escopo**
- T-A. Ajuste de dado local: `dataprev-2026` → `enrollment_status=exam_taken`.
- T-B. Slot fantasma: `UnpinExpiredContests` passa a rodar no `GET /` como a doc já promete.
- T-C. Página Arquivo: complemento exato de `scopeVisible()` (descartados ∪ expirados pela regra das 24h), só leitura, com filtro por motivo, abre o dossiê e permite mudar "minha situação" / restaurar descartado.

**Não-escopo**: tela "Atividade" e selos (etapas seguintes da T7); varredura ao abrir o app; mudar a regra das 24h; fixar a partir do arquivo; remover o toggle "descartados" da caixa de itens (fica, mesmo sobrepondo); paginação (volume pessoal < 200 linhas); Backlog v2.

**Impacto técnico**
- Dados: nenhuma migration. Só UPDATE de 1 linha local (T-A).
- API: `GET /archive` (Inertia). Reusa `PATCH contests/{c}/enrollment` e `POST contests/{c}/restore`.
- UI: link "Arquivo" no header da mesa; página nova com os componentes de item + `DossierModal` existentes.

**Critérios de aceite**
1. Após T-A: Dataprev em `Contest::visible()`, `isExpired()=false`, `pinned_slot=11`, aparece na mesa e na maleta.
2. Após T-B: abrir `/` desafixa todo expirado não-descartado (teste Pest no controller); paridade `isExpired()+isDismissed()` vs `scopeVisible()` segue verde.
3. `scopeArchived()` ∪ `scopeVisible()` = todos os concursos, interseção vazia (teste de paridade no mesmo dataset misto do `ContestScopeVisibleParityTest`).
4. Cada item do arquivo traz `archive_reason ∈ {dismissed, expired}` + status efetivo (ex.: "inscrições encerradas", "encerrado"); filtro por motivo funciona; página abre sem `ANTHROPIC_API_KEY`.
5. Marcar "inscrito" ou além num expirado pelo dossiê do arquivo → item sai do arquivo e volta pra mesa (teste Feature: enrollment PATCH + `visible()`).
6. Docs atualizados (`00` regra das 24h → "vai pro Arquivo"; `03` tela Arquivo; `04` etapas T7); `php artisan test`, pint, phpstan (`--memory-limit=1G`), eslint, prettier verdes.

**Perguntas bloqueadoras**: nenhuma. Decisões tomadas sem perguntar (reversíveis), registradas abaixo:
- Rota em inglês `/archive` (convenção do repo: código/rotas em inglês; `calendar`, `contests/dismissed`). Rótulo na UI: "Arquivo".
- Motivo "encerrado" do pedido original não é um motivo à parte: quem não está inscrito e está encerrado é *expirado*; o status efetivo aparece como selo. Inscrito encerrado continua na mesa (regra atual, confirmada em 17/10).

**Premissas**: fluxo local sem API key (memória `project_workflow_local_no_api`); nunca `migrate:fresh`; volume pequeno, query sem paginação.

## FASE 2 — Evidence Gathering

| # | Evidência | Âncora | Achado |
|---|---|---|---|
| 1 | Estado do Dataprev | tinker em 17/10 | `not_enrolled`, `exam_done`, `closed_at=2026-10-15 02:03`, `pinned_slot=11` → `isExpired()=true`, fora de `visible()` |
| 2 | Seed não toca campos do dono | `database/seeders/ContestSeeder.php:26`, `refreshContest` | `exam_taken` do JSON nunca chega ao banco para concurso já existente — por design |
| 3 | Doc promete unpin por request | `docs/01-modelo-de-dados.md:144` | "roda no início de cada request da tela principal e no fim de cada varredura" |
| 4 | Código só chama na varredura | `app/Jobs/Radar/FinalizeRunJob.php:29`; `RadarPageController::index` não chama | **Causa raiz do slot fantasma**: sem varredura (fluxo local), nunca roda |
| 5 | Slot fantasma bloqueia pin | `app/Services/Radar/PinService.php:96-100` (`firstFreeSlot`) | Conta `pinned_slot` de invisíveis como ocupado → maleta parece ter vaga e o pin falha/pula slot |
| 6 | Maleta do front | `resources/js/pages/radar/index.tsx:45` | Deriva de `visibleContests` → fantasma não renderiza (por isso "saiu da maleta") |
| 7 | Paridade e listagens existentes | `app/Models/Contest.php:313` (`scopeVisible`), `:345` (`scopeDismissed`); `tests/Feature/Models/ContestScopeVisibleParityTest.php`; `DismissedContestsController` | Base para `scopeArchived` e para o padrão de listagem |

**Fluxo atual**: varredura → `FinalizeRunJob` → unpin. Sem varredura, expirado fica com slot para sempre. Expirados e descartados só existem no banco (descartados têm toggle; expirados não têm tela).

## FASE 3 — Architecture Planning

### Bloco 1 — Slot fantasma (T-B)
| Abordagem | Corretude | Blast radius | Manutenção | Alinhamento |
|---|---|---|---|---|
| A. Chamar `UnpinExpiredContests` no `RadarPageController::index` (como a doc diz) | 5 | 5 (1 linha + teste) | 5 | 5 |
| B. Não escrever; ignorar `pinned_slot` de invisíveis em `PinService`/presenter | 3 (estado inconsistente persiste) | 3 | 2 | 2 |
| C. Middleware global de unpin | 4 | 3 (toda request) | 3 | 3 |

**Vence A**. B esconde o dado errado em vez de corrigir; C roda em rota que não precisa. Ordem crítica: **T-A antes de T-B**, senão o primeiro `GET /` desafixa o Dataprev e o slot 11 se perde.

### Bloco 2 — Ajuste do Dataprev (T-A)
| Abordagem | Corretude | Blast radius | Manutenção | Alinhamento |
|---|---|---|---|---|
| A. Marcar "prova feita" pela UI | — | — | — | Impossível: ele não aparece em lugar nenhum |
| B. `php artisan tinker --execute` com UPDATE pontual, registrado neste plano | 5 | 5 (1 linha) | 5 | 5 |
| C. Flag no seeder para sobrescrever campos do dono | 3 | 2 (pode sobrescrever outras escolhas) | 2 | 1 (quebra regra do seeder) |

**Vence B**. Comando: `Contest::where('slug','dataprev-2026')->update(['enrollment_status'=>'exam_taken'])`. Idempotente. No futuro o próprio Arquivo resolve casos assim (critério 5).

### Bloco 3 — Definição de "arquivado" (T-C)
| Abordagem | Corretude | Blast radius | Manutenção | Alinhamento |
|---|---|---|---|---|
| A. `scopeArchived` = `whereNotIn('id', visible()->select('id'))` | 5 (complemento exato, sem lógica de 3 valores) | 5 | 5 (muda junto com `visible`) | 5 |
| B. `whereNot(fn => visible())` | 3 (NOT sobre comparações com NULL pode sumir linha) | 4 | 4 | 4 |
| C. Reescrever a regra invertida à mão | 3 | 4 | 1 (duas regras para manter) | 2 |

**Vence A**. Motivo por item calculado em PHP (`isDismissed()` → `dismissed`, senão `expired`) no presenter/controller: zero LLM, sem coluna nova.

### Bloco 4 — Forma da página (T-C)
| Abordagem | Corretude | Blast radius | Manutenção | Alinhamento |
|---|---|---|---|---|
| A. Página Inertia `radar/archive` (`GET /archive`), lista + `DossierModal` existente | 5 | 4 | 4 | 5 (pedido do usuário: página própria) |
| B. Toggle "arquivados" na caixa de itens (como descartados) | 4 | 3 (mexe na mesa) | 3 | 2 (usuário escolheu página) |
| C. Aba dentro da futura tela Atividade | 3 (depende de etapa não feita) | 4 | 3 | 2 |

**Vence A**. Ordenação: `closed_at`/`dismissed_at` desc. Filtro por motivo via query string (`?reason=expired|dismissed`), aplicado no servidor. Sem botão de fixar.

**Validações**: regra de domínio no model/action (não no front) ✅; nenhum acoplamento novo (reusa endpoints existentes) ✅; reversível (sem migration) ✅; contratos existentes intactos ✅.

## FASE 4 — Execution Planning

1. **T-A (antes de qualquer `GET /` com o código novo)**: rodar o UPDATE do Bloco 2 no Postgres local; conferir critério 1 via tinker e no browser.
2. **T-B teste vermelho**: Feature test — expirado não-descartado com `pinned_slot` → `GET /` → `pinned_slot=null`; inscrito encerrado mantém slot.
3. **T-B**: chamar `UnpinExpiredContests::handle()` no início do `RadarPageController::index`. Teste verde. Ajustar `docs/01:144` (remover `[inferido]`).
4. **T-C domínio**: `Contest::scopeArchived()` (Bloco 3) + teste de paridade (visible ∪ archived = todos, disjuntos) no dataset misto.
5. **T-C backend**: `ArchiveController@index` (`GET /archive`, nome `archive.index`), filtro `reason`, payload via `ContestPresenter` + `archive_reason`. Feature tests: lista, filtro, sem API key.
6. **T-C front**: `resources/js/pages/radar/archive.tsx` + link "Arquivo" no header da mesa; selo de motivo e status efetivo; `DossierModal` com troca de situação e "trazer de volta"; sem pin. Tokens do handoff, `radius: 0`.
7. **T-C volta pra mesa**: Feature test do critério 5 (PATCH enrollment em expirado → aparece em `visible()`, some de `archived()`).
8. **Docs**: `00-spec` (regra das 24h e Descartado → "vão pro Arquivo"), `03-ui-inventario` (tela Arquivo), `04-plano-execucao` (T7: etapa 1 ✅, novas etapas "desafixar expirados no GET /" e "Arquivo" antes da varredura ao abrir).
9. **Gates**: `php artisan test`, pint, phpstan `--memory-limit=1G`, eslint, prettier, `tsc` (tolerar só os 3 erros conhecidos de `RedirectController.ts`).
10. **Verificação manual + commits**: Dataprev na mesa/maleta; arquivo lista expirados/descartados; marcar "inscrito" num expirado traz de volta. Commits atômicos (`fix(radar): unpin expired contests on page load`, `feat(radar): add archive page`, `docs`). Push/PR só quando o usuário pedir.

## FASE 5 — Verificação do Plano

- [x] Nenhuma regra de negócio na camada errada (scope no model, unpin em action, motivo em PHP)
- [x] Nenhum acoplamento desnecessário (reusa endpoints e componentes)
- [x] Critérios de aceite objetivos e testáveis
- [x] Checklist ≤ 10 itens
- [x] Máx. 3 abordagens por bloco, 1 vencedora justificada
- [x] Evidências ≤ 7 arquivos
- [x] Perguntas bloqueadoras: nenhuma; decisões assumidas listadas na Fase 1

**Gate = Livre para execução.** Dependência dura: item 1 (T-A) antes do item 3 (T-B).
