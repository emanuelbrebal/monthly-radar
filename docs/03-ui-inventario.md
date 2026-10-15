# UI — Inventário (Resident Evil × papel & tinta)

Referência visual de cores, fontes e componentes-base: `design/radar mensal/design_handoff_radar_mensal/` (README + `styles.css` + `direction-c.jsx`). **Este doc só descreve o que muda.** A metáfora sai de "ficheiro de ideias" e vira **maleta de sobrevivência**: a maleta do RE4 (grade de slots, item selecionado com moldura, painel de descrição embaixo) desenhada com a paleta do projeto.

## Tokens (Tailwind 4, `resources/css/app.css`)

**Fonte única: `design/radar mensal/design_handoff_radar_mensal/styles.css`** (revisado 14/10/2026). `app.css` repete os mesmos valores em `:root`; `node scripts/contrast-check.mjs` falha se divergirem ou se algum par de contraste (WCAG 2.x AA) cair abaixo do mínimo. A tabela de razões está no README do handoff.

1. `:root` com paper/ink/red/gold/green/blue/highlight/night. Mudanças de 14/10: `--ink-mid #4a443c`, `--ink-soft #5c554b`, `--night-text #f5eedb`, `--night-text-mid #d2c9b0`, `--highlight #f0d96b`, `--red-text #8a3526` (texto/botão vermelho; `--red` fica só decorativo).
2. Variáveis do shadcn religadas: `--background: var(--paper)`, `--foreground: var(--ink)`, `--primary: var(--red-text)`, `--primary-foreground: var(--paper)`, `--border: var(--ink)`, `--muted: var(--paper-soft)`, `--muted-foreground: var(--ink-mid)`, `--destructive: var(--red-deep)`, `--ring: var(--focus)`, `--radius: 10px` (o `0px` original foi alterado a pedido do dono em 18/09).
3. `@theme` expõe `--color-*` de todos os tokens (inclusive `tier-*`, `line-strong`, `red-text`, `muted-fg`, `line`) e as fontes `--font-display|serif|sans|mono`.
4. Fontes do Google no `app.blade.php`. App sempre em tema claro; a maleta é escura por design.

Tokens de maleta, linha e foco:

```css
--case: var(--night);   --case-lining: #1b1610;
--slot-border: #7d735f;            /* borda de slot, 3.8:1+ */
--slot-selected: var(--highlight); /* anel de seleção */
--line-strong: #7c7264;            /* bordas de UI no claro, 3.28:1+ (paper-deep) */
--focus: #1d3c63;  --focus-on-night: #f0d96b;
```

Nível (tier), um conjunto por degrau `elite|alto|inter|entrada`: `--tier-X-fg` (texto), `--tier-X-bg` (fundo do chip), `--tier-X-line` (borda/ícone, >= 3:1) e `--tier-X-on-night` (cor sobre a maleta). Badges: `--badge-{pos,neu,neg,info}-{fg,bg,line}`.

Superfícies (`@layer components`): `.surface-paper`, `.surface-card`, `.surface-night` definem `background`, `color` e as variáveis `--muted-fg` (texto secundário), `--line` (divisória) e `--focus-ring`. Todo cartão/painel declara a sua; filhos usam `text-muted-fg` e `border-line`. Helpers `.rm-*` ficam em `@layer components` (utilitários Tailwind sempre vencem). `.rm-dot-grid` foi removido; `.rm-eyebrow` e `.rm-badge` são 12px/600, sem caixa-alta. Foco global: `:focus-visible` com anel de 2px e offset 2px.

Cores de evento no calendário (reaproveitando as badges do handoff):

| EventType | badge |
|---|---|
| `registration_start` | `pos` (verde) |
| `registration_end`, `fee_deadline` | `neu` (dourado) |
| `exam` | `neg` (vermelho) |
| `result`, `other` | `info` (azul) |

Status do edital → badge: `registration_open` pos · `published`/`in_preparation`/`authorized` neu · `expected` info · `registration_closed`/`exam_done`/`finished`/`suspended` neg.

## Layout da tela principal (desktop 1280+)

```
┌──────────────── masthead (paper, régua dupla embaixo) ─────────────────────┐
│ ◉ radar mensal · concursos   n.º 09 · setembro · MMXXVI  [área ▾] [VARRER] │
│ última varredura 14/09 23:10 · 23 concursos · 2 somem nas próximas 24h     │
├──────────── MALETA (night) ─────────┬──────── MESA (paper, dotted grid) ────┤
│ ¶ maleta · 5/12                     │  ←  setembro · MMXXVI  →   [hoje]    │
│ ┌────┬────┬────┬────┐               │  seg ter qua qui sex sáb dom         │
│ │ ▣  │ ▣  │ ▣  │ ▣  │               │  ┌──┬──┬──┬──┬──┬──┬──┐             │
│ ├────┼────┼────┼────┤               │  │  │▭ │  │  │▭▭│  │  │  ← pastas    │
│ │ ▣  │ ·  │ ·  │ ·  │               │  …                                    │
│ ├────┼────┼────┼────┤               │                                       │
│ │ ·  │ ·  │ ·  │ ·  │               ├───────────────────────────────────────┤
│ └────┴────┴────┴────┘               │ ¶ caixa de itens · 23                │
│ ── descrição do item ────────────── │ [status▾] [só inscritos] [busca /]   │
│ DATAPREV 2026                        │ DATAPREV  prova 11/10  ABERTAS  ◉ pin │
│ prova em 26 dias · inscrito          │ TCDF      prova 22/11  ENCERR. some 18h│
│ [abrir dossiê ↵]  [desafixar P]      │ …                                     │
└──────────────────────────────────────┴───────────────────────────────────────┘
```

Grid: `grid-template-columns: minmax(420px, 5fr) 7fr`. Abaixo de 1024px: empilha mesa → maleta (mobile caprichado é v2).

## Maleta (coluna esquerda)

- Fundo `--case` com textura sutil: `repeating-linear-gradient(135deg, rgba(243,235,218,0.02) 0 2px, transparent 2px 6px)`. Borda 2px `--ink`, cantoneiras metálicas nos 4 cantos (quadradinhos 10×10 `--gold` com borda ink).
- Header: eyebrow mono `¶ maleta · N/12` em `--night-text-mid` + botão secundário mono `por nível` / `grade` à direita (alterna a vista; escolha guardada só no navegador).
- **Faixa de equilíbrio** (abaixo do header, só com item fixado): um quadradinho 8×8 por nível com contagem (`elite 2 · alto 1 · inter. 1 · entrada 0`), nível zerado a 30%. Cores do nível: elite `--red`, alto `--gold`, intermediário `--blue`, entrada `--green`. Se elite+alto > intermediário+entrada, linha serif italic: "maleta pesada no topo — X de elite/alto contra Y de base."
- Grade 4×3, slots quadrados (`aspect-ratio: 1`), gap 6px, fundo `--case-lining`, borda 1px `--slot-border`, grade pontilhada interna.
- **Slot vazio:** glifo `·` centralizado, `--night-text-mid` a 30%.
- **Slot ocupado (item):** mini ficha papel (`--card`, borda ink, sombra `3px 3px 0 #000`) com:
  - sigla do órgão em Inter Tight 15px/700 (trunca; `title` com o título completo);
  - **tag de fase** (`phase_label`, forma + texto + cor, até 2 linhas) — ex. "aguardando resultado", "inscrito", "edital em formulação"; regras em `ContestPhaseResolver` (a situação pessoal vence o estado do edital; ver `tasks/calendar-case-phase-polish.md`);
  - marca do nível (ícone) e, se `is_new`, a tag "NOVO" (concurso criado nos últimos `radar.new_window_days` dias, sem inscrição/interesse, fixação ou descarte);
  - pin vermelho 8×8 no topo centro (igual ao PinCard do handoff);
  - faixa vertical 4px na borda esquerda da ficha, na cor do nível (`tier`), quando há nível;
  - se está nas 24h finais: carimbo mini "some 18h" rotacionado -8°.
- **Selecionado:** moldura 2px `--slot-selected` + cantoneiras em L animadas (pulse de opacidade 1.2s) — o cursor do RE.
- **Painel "descrição do item"** (fixo embaixo da grade, borda-top régua dupla night): título Inter Tight 18px/700 com "NOVO" e a marca do nível à direita, tag de fase em destaque, grade de dados (vagas TI, salário, taxa, banca, inscrição até, prova), linha serif italic "próximo: …", só os links de edital e inscrição, e dois botões secundários escuros (`abrir dossiê ↵`, `desafixar P`, quebram de linha em largura estreita). O painel é fixo (`shrink-0`); a grade 4×3 é a região que encolhe e rola, para nada ser cortado. Sem seleção: "Escolhe um item."
- **Vista "por nível":** os itens fixados aparecem em grupos (elite → alto → intermediário → entrada → sem nível), cada um com rótulo mono + quadradinho de cor e sua própria grade de 4 colunas; grupo vazio não aparece. Dentro do grupo, a ordem é a do slot. Nessa vista não há arrastar (a posição é derivada); setas percorrem só os itens na ordem da tela. Fixar/desafixar e abrir dossiê funcionam igual.

Interação:
- Setas movem a seleção; `Enter` abre dossiê; `P` fixa/desafixa (na caixa de itens também); `Esc` fecha modal.
- Arrastar item entre slots (HTML5 drag and drop nativo, sem lib) → troca de posição → `PATCH /pins`. Otimista, com rollback em erro.
- Soltar um item da caixa de itens num slot vazio = fixar naquele slot.
- Hover: `--slot-hover`. Transições 120ms. Sem som no MVP.

## Mesa (coluna direita)

### Controle de varredura (masthead)
- `Select` de área (shadcn reestilizado: borda ink, radius 0, mono uppercase 11px).
- Botão primário vermelho **VARRER** (estilo "Primary (aposta)" do handoff). Durante a run: desabilitado, vira `RadarAnim` 18px + "varrendo…".
- Linha de status mono: última run, contagens, erros ("2 fontes falharam" com tooltip listando).

### Filtro de estados

Detalhes de regra em `docs/06-filtro-estados.md`.

- No masthead, entre o select de área e o botão VARRER: botão secundário `estados · 27 ▾` (mono 11px uppercase). O contador mostra quantos estados estão marcados; com tudo marcado, o texto vira `estados · todos`.
- Abre um `Popover` (Radix) de 560px, estilo formulário de papel: fundo `--card`, borda ink, sombra neo-stamp.
  - Linha de topo: checkbox **Nacional** e os atalhos `marcar tudo` · `limpar` · `só Nordeste` (texto mono sublinhado).
  - Grade de 5 colunas, uma por região. Título da região com o checkbox "região inteira" (tri-state) em mono bold uppercase e régua fina embaixo. Abaixo, os estados em lista: checkbox + sigla em mono bold + nome em Inter Tight 12px ("RN Rio Grande do Norte").
  - Checkbox no estilo da casa: quadrado 12×12, borda 1px ink, radius 0. Marcado = `✕` em `--red` (um "x" de caneta), indeterminado = traço `—`.
  - Rodapé: `aplicar` (primário) e `cancelar`. Nada é salvo antes do `aplicar`. Salvar faz `PATCH /preferences/states` e um partial reload da mesa.
- Se a última varredura foi feita com outros estados, a linha de status mostra: "Filtro mudou desde a última varredura. Varre de novo pra buscar os novos estados."
- Tags novas: `fora do filtro` (neu) em item da maleta ou em inscrito fora da seleção; `local a definir` (info) em concurso sem estado. A caixa de itens mostra as siglas dos estados em mono ao lado do órgão (`DATAPREV · RN CE PB SC`; nacional → `BR`).

### Calendário
- Mês em grade 7 colunas (segunda primeiro), navegação ← → e "hoje". Título `font-display` 32px "outubro · 2026" (ano arábico, derivado do mês exibido).
- Célula: `min-height: 96px`, borda `--rule-soft`, número do dia mono 10px no canto. Hoje: número com fundo `--highlight`. Dias fora do mês: 40% de opacidade.
- **Pasta (evento):** chip de 20px de altura com aba de pasta manila — `::before` de 14×5px acima à esquerda com a mesma cor da borda. Card curto, uppercase: `SIGLA · TÍTULO DO EVENTO` (`label` do evento, ou o tipo se não houver), ex. `DATAPREV · GABARITO PRELIMINAR`. Cor pela tabela de EventType. Concurso fixado ganha o pin vermelho 6px na pasta.
- Máximo 3 pastas por célula; excedente vira `+2` que abre um popover com a lista.
- Clique na pasta → modal do dossiê.
- Só eventos de concursos visíveis.

### Caixa de itens
- Box com título `¶ caixa de itens · N` (padrão Box do handoff).
- Filtros: status (multi), "só inscritos", busca por texto (`/` foca) e o toggle `descartados (N)` no fim da barra. O toggle troca a lista pelos descartados: linhas a 50% de opacidade, motivo em mono ao lado e botão `trazer de volta`. Os descartados nunca aparecem no calendário.
- Linhas compactas: quadradinho 8×8 da cor do nível (vazio se sem nível) · sigla (display 16px) · próximo evento (mono) · tag "NOVO" (se `is_new`) · tag de fase · botão pin (lucide `Pin`, stroke 1.5; vermelho se fixado).
- Ordenação: próximo evento mais cedo primeiro; sem data vai para o fim.
- Linha nas 24h finais: carimbo "some em Xh" (Stamp do handoff, 10px).
- Vazio: "Nada no radar. Escolhe uma área e varre."

## Modal do dossiê

Radix `Dialog`, largura `min(1040px, 94vw)`, altura máx 88vh com scroll interno. Visual da pasta manila do C2 do handoff:

- Aba dourada no topo esquerdo: `dossiê · DATAPREV · 2026`.
- Carimbo no topo direito com o status efetivo ("inscrições abertas").
- Grid interno `1.3fr 1fr`.

**Esquerda**
- Eyebrow vermelho: `órgão · banca · regime`.
- Manchete display 40px (`title`). Lead serif italic: primeiro bullet do resumo.
- Tags: status, regime; se `statutory`, tag vermelha `estatutário · art. 117` (lembrete de incompatibilidade com gerência de empresa, Lei 8.112) `[inferido — confirmar se quer esse alerta]`.
- **Fontes:** lista recolhida por padrão (`fontes (N)`); os únicos links em destaque são `abrir edital` e `inscrição`.
- **Resumo** (bullets do dossiê).
- **Cargos de TI**: tabela mono (cargo · vagas · salário · requisito · lotação).
- **Conteúdo cobrado**: lista curta.
- **Divergências**: cada uma com os dois lados e a badge de confiança.
- **Lacunas**: lista em serif italic, `--ink-mid`.

**Direita**
- Mini stats 2×2: salário, vagas TI, taxa, prova (número display 22px + label mono).
- **Linha do tempo:** eventos em ordem com data mono; passados riscados.
- **Links:** botão primário "abrir edital ↗" (desabilitado com tooltip "edital ainda não saiu" se nulo) e secundário "inscrição ↗".
- **Minha situação:** checklist vertical dos 6 passos de `EnrollmentStatus`; clicar num passo define o enum (marca os anteriores, desmarca os seguintes). Otimista.
- **Descartar:** abaixo do checklist, separado por régua fina, um link mono vermelho `descartar este concurso`. Ele expande um input de uma linha ("motivo, se quiser") com os botões `descartar` (secundário, borda vermelha) e `cancelar`. Ao confirmar, o modal fecha, o item sai da mesa e da maleta e aparece um toast com `desfazer` (8s).
- **Concurso descartado aberto** (pelo toggle da caixa de itens): o carimbo do topo vira `DESCARTADO` em `--ink-soft`, o motivo aparece em serif italic abaixo da manchete, o checklist fica desabilitado e o único botão ativo é `trazer de volta`.
- **Pin:** toggle "na maleta / fora da maleta".
- **Histórico:** `contest_status_logs` como log mono (data · de → para).
- **Fontes:** lista com domínio, data da coleta, origem (raspado/IA) e badge de confiança. Rodapé: "atualizado em 14/09 23:10".
- `enrichment_status = failed|skipped`: faixa amarela "Dossiê incompleto — só dados raspados." no topo.

## Microcopy (voz do handoff: seca, brasileira)

| situação | texto |
|---|---|
| varrendo | "Varrendo fontes…" |
| run terminou | "Varredura feita. 3 novos, 5 atualizados." |
| nada encontrado | "Nada novo nessa área. Tenta outra." |
| erro geral | "Sinal perdido. Verifica as fontes." |
| maleta cheia | "Maleta cheia. Tira alguma coisa antes." |
| fixou | "Guardado na maleta." |
| some em breve | "Inscrição encerrada. Sai da mesa em 18h." |
| inscrição marcada | "Anotado. Esse fica." |
| descartou | "Descartado. Fora da mesa." |
| trouxe de volta | "Voltou pra mesa." |
| tentou fixar descartado | "Esse tá descartado. Traz de volta antes." |

## Componentes a criar (`resources/js/components/radar/`)

`Tag`, `Stamp`, `RadarMark`, `RadarAnim` (portar do `shared.jsx` do handoff) · `AttacheCase`, `CaseSlot`, `ItemDescription` · `SweepControl` · `MonthCalendar`, `EventFolder` · `ItemBox`, `ItemRow` · `DossierModal`, `EnrollmentChecklist`, `SourceList`.

Tipos TS em `resources/js/types/radar.ts`, espelhando os enums PHP (gerar à mão; manter em sincronia com um teste que compara os valores).
