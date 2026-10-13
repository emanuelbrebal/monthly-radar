# Radar Mensal · Concursos — Spec do MVP

> Pivot de 15/09/2026. Substitui `docs/00-mvp-spec.md` (Radar de ideias SaaS), que fica descontinuado.
> Marcadores `[inferido]` = decisão tomada sem confirmação explícita do dono do projeto. Revise antes de implementar.

## Produto em uma frase

Uma maleta pessoal de concursos de TI: eu escolho a área, o app varre a web, organiza cada concurso como dossiê, mostra tudo num calendário e me deixa fixar os que importam.

## Para quem

Só para o Emanuel (dev full-stack, alvo em concursos de TI/desenvolvimento). Sem SaaS, sem outros usuários. O login do starter kit fica (app exposto em VPS), mas o cadastro público é desligado e o usuário vem de seeder. `[inferido]`

## A única operação: varredura

1. Usuário escolhe uma área no select (`TiArea`): desenvolvimento, dados, infraestrutura, segurança, suporte, governança de TI, todas. Padrão: desenvolvimento. `[inferido]`
   Os **estados** marcados no filtro (checkbox estilo PCI, preferência salva) limitam o que é raspado e enriquecido. Ver `docs/06-filtro-estados.md`.
2. Aperta **varrer**. Cria um `SearchRun` e despacha a cadeia de jobs.
3. **Scraping** das fontes fixas → filtra por palavras-chave da área → normaliza → deduplica → cria/atualiza `contests`.
4. **Enriquecimento** (híbrido): para cada concurso com lacunas, o LLM com busca web completa campos e escreve o dossiê.
5. A tela acompanha o progresso (polling) e atualiza calendário, caixa de itens e maleta.

Não há cron de busca. Não há e-mail. Atualizar um dossiê = varrer de novo a área dele.

## Classificação por andamento (`EditalStatus`)

| Valor | Rótulo na UI | Significado |
|---|---|---|
| `expected` | previsto | há pedido/notícia, sem autorização formal |
| `authorized` | autorizado | autorização publicada (portaria/DOU) |
| `in_preparation` | edital em produção | banca contratada ou comissão formada, edital ainda não saiu |
| `published` | edital publicado | edital saiu, inscrições ainda não abriram |
| `registration_open` | inscrições abertas | |
| `registration_closed` | inscrições encerradas | |
| `exam_done` | prova aplicada | |
| `finished` | encerrado | resultado final/homologação |
| `suspended` | suspenso | suspenso ou cancelado |

O status efetivo é calculado por `ContestStatusResolver` a partir do status raspado **e** das datas (ex.: `registration_open` com `registration_end` no passado é tratado como `registration_closed`). Regras em `docs/02-pipeline.md`.

## Minha situação no concurso (`EnrollmentStatus`)

Checklist sequencial, guardado como enum (marcar um passo marca os anteriores):

`not_enrolled` (não inscrito) → `interested` (quero fazer) → `enrolled` (inscrito) → `fee_paid` (taxa paga) → `venue_released` (local de prova saiu) → `exam_taken` (prova feita)

"Inscrito" para as regras do sistema = `enrolled` ou qualquer passo depois dele.

**Descartado (`dismissed`)** é um valor do mesmo enum, mas fica fora da sequência do checklist. Serve para concurso que eu decidi não fazer (choque de data, cargo errado, lotação ruim).
- Some **na hora** do calendário e da caixa de itens. Não espera as 24h.
- Sai da maleta ao ser descartado, e não pode ser fixado enquanto estiver descartado.
- Não é enriquecido nas varreduras. A varredura continua atualizando os dados raspados, em silêncio, para o dedup.
- Guarda um motivo opcional (`dismissed_reason`, até 120 caracteres).
- É reversível: "trazer de volta" leva o concurso para `not_enrolled`. Se ele já estiver expirado pela regra das 24h, continua escondido.

## Regra das 24 horas

Quando um concurso fica efetivamente `registration_closed` (ou qualquer status posterior) e o enrollment está **antes** de `enrolled`, ele **sai de todas as listagens 24h depois** do momento de encerramento.

- É um escopo de query (`Contest::visible()`), não uma deleção. O registro fica no banco para dedup.
- Se estiver fixado, é desafixado no mesmo momento em que some (o slot é liberado). Fixar **não** protege da regra. `[inferido]`
- Se uma retificação reabrir as inscrições, ele volta a aparecer.
- Enquanto está nas 24h finais, a UI mostra o carimbo "some em Xh".

## Pin (maleta)

- A maleta tem **12 slots** (grade 4×3). Cada concurso ocupa 1 slot. `[inferido]`
- Fixar coloca no primeiro slot vazio; maleta cheia → aviso, nada acontece.
- Os slots podem ser reordenados (arrastar ou teclado).

## Telas

Uma tela principal (`/`), autenticada:

- **Esquerda — maleta:** slots com os concursos fixados + painel "descrição do item" do slot selecionado.
- **Direita — calendário:** mês com pastinhas por evento (abertura/fim de inscrição, prova, resultado) + **caixa de itens** abaixo, com a lista de todos os concursos visíveis (é dali que se fixa). `[inferido: a caixa de itens não foi pedida, mas sem ela não há como fixar concurso que ainda não tem data]`
- **Modal de dossiê:** abre ao clicar em pasta, slot ou item. Mostra tudo que há de mais recente, o link do edital, o checklist de inscrição e o botão de pin.

Detalhes visuais em `docs/03-ui-inventario.md`.

## Fora do MVP (Backlog v2)

Varredura agendada · notificações · sincronizar calendário (.ics) · itens de tamanhos diferentes na maleta · anotações livres por concurso · fontes que exigem navegador headless · leitura completa do PDF do edital · versão mobile caprichada.

## Decisões fechadas

1. Stack: a do repo (Laravel 13 + Inertia 3 + React 19 + Tailwind 4 + Postgres). Ignorar a sugestão de Next.js do handoff de design.
2. Dados: híbrido — scraping de fontes fixas primeiro, LLM com busca web só nas lacunas.
3. Gatilho: só manual.
4. Visual: paleta papel & tinta + fontes do handoff `design/radar mensal/` (Direção C), com layout de inventário RE.
