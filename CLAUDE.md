# CLAUDE.md — radar-hub

Contexto para o Claude Code ao trabalhar neste repositório.

## O que é este projeto

Radar Hub: SaaS pessoal que descobre, pontua e gerencia ideias de SaaS/AI. Monolito Laravel 12 + Inertia + React/TS + PostgreSQL. Pipeline de agentes LLM via API (não via Claude Code) orquestrado por Jobs + Scheduler. Documento de referência: `docs/00-mvp-spec.md` — **leia antes de qualquer tarefa**.

**Repo:** `C:\Users\manub\OneDrive\Área de Trabalho\pasta dev\ideias SAAS\radar mensal` → GitHub: `https://github.com/emanuelbrebal/monthly-radar`

## Regras invioláveis

1. **Escopo é lei.** Implemente apenas o que está no MVP da spec. Se uma tarefa tocar item do "Backlog v2", pare e pergunte.
2. **Hub opera sem LLM.** Nenhuma feature de UI/gestão pode depender de chamada de LLM para funcionar. Falha de API degrada graciosamente (ideia fica `parse_failed`, pipeline segue).
3. **LLM só via `LlmProvider`.** Nunca chamar SDK/HTTP de provider diretamente em Job ou Controller. Config por agente em `config/radar.php`.
4. **Output de agente é JSON validado.** Todo prompt de agente exige resposta JSON pura (sem markdown fence). Validar contra o schema definido em `docs/agents/{agente}.md`. Retry 1x; segunda falha → `parse_failed`.
5. **Zero LLM para o mecânico.** Dedup, state machine, templates, firmeza-descarte automático: código PHP puro, com testes.
6. **System prompts dos agentes** vivem em `resources/prompts/{agente}.md` e são carregados pelos Jobs — nunca hardcoded em string PHP. Fonte canônica: `docs/agents/`.
7. **Idioma:** código, commits e README em **inglês**; conteúdo gerado pelos agentes (ideias, análises) em **português**.
8. **Custo:** logar `input_tokens`/`output_tokens` de toda chamada em `pipeline_runs.meta`. Scout e Synthesis usam modo batch quando disponível no provider.

## Convenções

- Services em `app/Services/Radar/` (DedupService, PipelineOrchestrator, LlmProvider e implementações em `Llm/`).
- Jobs em `app/Jobs/Radar/`, encadeados com `Bus::chain`. Jobs idempotentes (re-dispatch não duplica ideias — garantido pelo `content_hash` unique).
- Front: páginas Inertia em `resources/js/Pages/`, componentes em `resources/js/Components/`. Tailwind. Sem lib de UI pesada no MVP.
- Testes: Pest. Mínimo obrigatório: DedupService, parsing/validação de JSON dos agentes (com fixtures), PipelineOrchestrator (regras de decisão), score do Curator.
- Migrations seguem o modelo de dados da spec — não inventar colunas.

## Comandos úteis

```bash
php artisan radar:harvest {show-hn|github-trending|product-hunt}
php artisan radar:tick          # roda o orchestrator manualmente
php artisan radar:weekly --force
php artisan radar:deep-dive {slug} --force   # ignora cache
```

## O que NÃO fazer

- Não adicionar fontes de harvest além das 3 do MVP.
- Não implementar council-of-five, track regional ou 5 lentes do tropicalizer.
- Não trocar a stack do front (sem Next, sem tRPC, sem Filament no MVP).
- Não usar WebSearch/tools de busca nos agentes do MVP.
- Não escrever prompt de agente novo sem registrar em `docs/agents/`.
