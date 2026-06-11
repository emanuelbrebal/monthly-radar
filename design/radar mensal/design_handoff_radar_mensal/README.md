# Handoff: Radar Mensal — Design Guideline

## Overview

**Radar Mensal** é um app interno (uso pessoal) que coleta ideias de negócio scrapadas da web e ajuda a triá-las e organizá-las como projetos futuros. O fluxo central é estilo "Tinder de ideias": cada manhã caem ~80 ideias novas, o usuário passa o olho rapidamente (swipe ← passa / swipe → aposta), e as aceitas vão pra um kanban de prioridades. Lente regional: ideias do mundo que façam sentido para o Nordeste brasileiro / Brasil.

Este handoff contém o **design system completo** (cores, tipografia, voz, componentes) + **protótipo das telas chave** em três direções comparativas. A direção final escolhida pelo usuário é a **Direção C (Mashup)**, com a logomarca **radar mensal** (radar sweep com blip vermelho).

## About the Design Files

Os arquivos `.html`, `.jsx` e `.css` neste bundle são **referências de design criadas em HTML/React in-browser** — protótipos mostrando aparência e comportamento pretendidos, não código de produção para copiar diretamente.

A tarefa é **recriar esses designs no ambiente existente do codebase alvo** (Next.js / Vite / Remix / etc), usando os padrões e bibliotecas já estabelecidos lá. Se ainda não existe um ambiente, escolha o framework mais apropriado para o projeto (recomendado: **Next.js 14+ com App Router + Tailwind CSS + shadcn/ui** para componentes-base) e implemente os designs lá.

Não tente importar o `design-canvas.jsx` ou usar React via CDN no app real — aquilo é só o palco de apresentação. O que importa são as decisões visuais e de interação.

## Fidelity

**High-fidelity (hifi).** Os mocks usam cores finais (hex exatos abaixo), tipografia final (Google Fonts), espaçamentos definidos. Recrie pixel-perfect usando a stack do codebase.

## Identidade

- **Nome**: radar mensal
- **Conceito-irmão**: boletim de ideias / edição mensal / dossiê
- **Logomark**: radar circular com sweep diagonal e blip vermelho (ver `shared.jsx → RadarMark`). Adotar como SVG inline ou exportar para o sistema de ícones do projeto.
- **Tagline**: "boletim de ideias · edição n.º NN · maio · MMXXVI"
- **Voz**: brasileira, coloquial mas seca, editorial. Frases curtas, verbos no presente, números quando souber. Vocabulário: *aposta*, *radar*, *edição*, *boletim*, *manchete*, *dossiê*, *gabinete*, *campo*. NUNCA corporativo ("Welcome back!", "Discover opportunities", etc).

Exemplos de microcopy ("fala assim ↔ nunca assim"):
- ✅ "Boletim do mês. 87 ideias pra você passar o olho."
- ❌ "Welcome back! Discover new opportunities today 🚀"
- ✅ "Ideia mais ou menos. Anota e vamos pra próxima."
- ❌ "This idea did not match your preferences."
- ✅ "Aposta arquivada. Bora pra investigação."
- ❌ "Success! Item added to your kanban board."

## Design Tokens

### Colors (paleta papel & tinta)

| Token | Hex | Uso |
|---|---|---|
| `--paper` | `#f3ebda` | bg base, "papel" |
| `--paper-soft` | `#ede2cb` | bg secundário, headers |
| `--paper-deep` | `#e3d6b9` | bg terciário, footers |
| `--card` | `#fbf6e8` | bg de cards/ficheiros |
| `--ink` | `#1a1612` | texto principal, bordas fortes |
| `--ink-mid` | `#5a544c` | texto secundário |
| `--ink-soft` | `#8a8175` | texto terciário, placeholders |
| `--red` | `#c8553d` | aceitar/aposta/carimbo |
| `--red-deep` | `#9c3d28` | hover red, em-construção |
| `--green` | `#4a7c3d` | tag positiva validada |
| `--blue` | `#2a4b6e` | meta-info, foodtech bars |
| `--gold` | `#b08a3e` | estrelas, tag neutra, aba dossiê |
| `--highlight` | `#f0d96b` | marca-texto inline |
| `--night` | `#14110d` | modo escuro/console |
| `--night-card` | `#221c14` | cards no modo escuro |
| `--night-text` | `#f0e8d4` | texto no modo escuro |
| `--night-text-mid` | `#b7ad94` | texto secundário modo escuro |
| `--rule` | `#1a1612` | régua / divisor forte |
| `--rule-soft` | `rgba(26,22,18,0.18)` | divisor sutil |

### Typography

Quatro famílias do Google Fonts:

```html
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;0,6..72,600;1,6..72,400&family=Inter+Tight:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet"/>
```

| Token | Família | Uso |
|---|---|---|
| `--f-display` | `'Instrument Serif'` | manchetes grandes, números display, capa de seção |
| `--f-serif` | `'Newsreader'` | sub-manchetes, citações, copy editorial, body de long-read |
| `--f-sans` | `'Inter Tight'` | body de UI, labels, navegação, botões |
| `--f-mono` | `'JetBrains Mono'` | eyebrows, tags, dados tabulares, timestamps |

Escala:
- display-xl: 72px
- display-l: 48px
- display-m: 32px
- title: 24px
- body-l: 17px
- body: 15px
- body-s: 13px
- caption: 11px

Display sempre com `line-height: 0.95–1.05` e `letter-spacing: -0.01em` a `-0.015em`. Mono sempre com `letter-spacing: 0.10em–0.18em` e `text-transform: uppercase` quando usado como eyebrow.

### Espaçamento

Sem escala formal. Usa múltiplos de 4: 4, 6, 8, 10, 12, 14, 18, 22, 28, 36, 44. Padding de card: 14–24px. Gap entre seções de página: 18–32px.

### Bordas e sombras

- Border padrão: `1px solid var(--ink)`
- Régua dupla (editorial): `3px double var(--ink)`
- Régua sutil: `1px solid var(--rule-soft)`
- Border radius: **0 em quase tudo** (estética editorial). Apenas badges-pill e o botão circular do avatar.
- Sombra neo-stamp (cards/fichas): `4px 4px 0 var(--ink)` ou `6px 6px 0 rgba(26,22,18,0.18)`
- Sombra suave (hover): `0 4px 16px rgba(26,22,18,0.08)`

### Padrões repetidos

- **Dotted grid background** (B & C): `radial-gradient(circle, rgba(26,22,18,0.18) 1px, transparent 1px) 0 0/16px 16px`
- **Ruled lines (index card body)**: `repeating-linear-gradient(transparent 0 22px, rgba(42,75,110,0.16) 22px 23px)` com `background-position: 0 8px`
- **Placeholder stripes** (para imagens): `repeating-linear-gradient(45deg, rgba(26,22,18,0.06) 0 6px, rgba(26,22,18,0) 6px 12px)` + borda tracejada interna

## Sistema de Tags

Cada ideia recebe 1–4 tags automáticas. Três níveis de cor:

### Verdes (positivas)
- `bem tropicalizado` ◉
- `inédita no brasil` ◇
- `poucos concorrentes` ↑
- `boa recepção do público` ♥
- `TAM grande` / `mercado grande` ∞
- `ideia boa` ★

Estilo: `background: #d8e7c6 · border: 1px solid #4a7c3d · color: #2d4d22`

### Amarelas (neutras / ressalvas)
- `ideia boa de melhorar` ✎
- `ideia mais ou menos` ~
- `ideia comum` =
- `difícil distribuição` ?

Estilo: `background: #f0e2b8 · border: 1px solid #b08a3e · color: #6b5220`

### Vermelhas (alerta)
- `regulação pesada` !
- `mercado saturado` ×
- `ideia muito comum` ×

Estilo: `background: #f0d4c8 · border: 1px solid #c8553d · color: #7d2814`

### Anatomia da tag

```
display: inline-flex
gap: 6px
font: 'JetBrains Mono' 10px / 0.06em letter-spacing
padding: 4px 8px 3px
border: 1px solid (cor variante)
text-transform: uppercase
border-radius: 0
```

## Componentes-chave

### IdeaCard (Index Card)

A unidade central. Usada no feed, no kanban, no dossiê. Anatomia:

```
┌─────────────────────────────────────────┐
│ ▮ ficha · 003   FOODTECH · IA   ★★★★★  │  ← fita preta (ink) com tab branca
├─────────────────────────────────────────┤
│                                         │
│   Gerador de cardápio digital com       │  ← display serif, 26-36px
│   IA para bares de rua                  │
│                                         │
│   Foto do cardápio escrito à mão...     │  ← newsreader 14px italic, mid
│                                         │
│   ┌─────────────────────────────────┐   │
│   │ [placeholder 16:9 do produto]   │   │
│   └─────────────────────────────────┘   │
│                                         │
│   [inédita] [boa recepção] [melhorar]   │  ← tags row
│                                         │
├─────────────────────────────────────────┤
│ fonte · producthunt   modelo · SaaS R$29│  ← paper-deep footer mono
└─────────────────────────────────────────┘
```

- Container: `background: var(--card) · border: 1px solid var(--ink) · box-shadow: 4px 4px 0 var(--ink)`
- Header strip: tab preta com `ficha · NNN` (mono, paper text), depois categoria mid, depois estrelas. Border-bottom 1px ink.
- Body: padding 22px, com ruled-line bg pattern (azul fraco) atrás do texto.
- Footer: 8px 14px, `background: var(--paper-deep)`, mono uppercase, source à esquerda, model à direita.

Variações:
- **Compact** (no kanban): sem placeholder, summary truncado a 90 chars + ...
- **Pinned** (kanban C/B): adiciona pin vermelho redondo (`width: 10px · height: 10px · border-radius: 50%`) absoluto no topo centro.
- **Dimmed** (under-deck preview no feed): `opacity: 0.45 · transform: translate(-6px, 10px) rotate(-2deg)`

### Estados do card no feed (swipe)

- **default**: posição central
- **drag**: `transform: translate(${drag.x}px, ${drag.y}px) rotate(${drag.x * 0.05}deg)` · `transition: none`
- **drag-aceita** (drag.x > 0): overlay `Stamp` vermelho rotacionado +10° no canto direito, opacity = `drag.x / 100`
- **drag-rejeita** (drag.x < 0): overlay `Stamp` cinza rotacionado -14° no canto esquerdo, opacity = `-drag.x / 100`
- **commit-aceita**: `transform: translateX(600px)` · transition 250ms cubic-bezier(.2,.7,.3,1)
- **commit-rejeita**: `transform: translateX(-600px)`
- **reset (não foi longe o suficiente)**: `transform: translate(0, 0)` · transition 250ms

Threshold de commit: ±80px de deslocamento horizontal.

### Stamp (carimbo)

Selo de tinta vermelha rotacionado, usado para `APOSTA`, `ENCERRADO 31/05`, etc.

```
display: inline-block
font: 'JetBrains Mono' 13px / 700 / 0.18em letter-spacing / uppercase
color: var(--red)
border: 2px solid var(--red)
padding: 5px 10px 4px
transform: rotate(-4deg até -14deg)
background: rgba(200,85,61,0.04)
```

### Stars

Sistema 1–5 estrelas usando glyph Unicode `★` (preenchida) + `☆` (vazia, opacity 0.35), cor `var(--gold)`. Tamanhos típicos: 10/11/13/15/20px.

### Botões

- **Primary (aposta)**: `background: var(--red) · color: var(--paper) · border: 1.5px solid var(--ink)`, padding `10px 16px 8px`, font Inter Tight 14/600.
- **Secondary**: `background: var(--card) · border: 1px solid var(--ink)`, font Inter Tight 13/500.
- **Key button** (atalhos do feed, ↑ ↓ ← →): box 48×48 com `box-shadow: 2px 2px 0 var(--ink)`, mono 22px. Emphasis = red bg.
- **Tag filter** (kanban toolbar): `padding: 6px 10px · border: 1px solid var(--rule-soft) · mono 10px uppercase`. Variante ativa: border preta + bg card.

## Telas

Tamanho desktop padrão: **1280×800** (full canvas) ou responsivo. Mobile: **380×780**. A direção C é a final.

### C1 · Radar (feed de swipe)

**Propósito**: triagem rápida do dia. Usuário passa o olho em uma ideia por vez, decide passa/anota/aposta.

**Layout**: grid 3 colunas — `240px · 1fr · 280px`.
- **Esquerda**: eyebrow "¶ na fila" + lista de próximas 4 ideias (categoria mono + manchete display 16px). Abaixo: filtros (CheckRow estrelas ≥ 3, só inéditas BR, esconder saturadas).
- **Centro** (área principal): fundo `dotGrid`, com index card 420×520 centralizado + under-deck preview atrás (rotate -2°, opacity 0.45). Bottom: 3 KeyBtns (← passa, ◇ anota, → aposta emphasis).
- **Direita** (modo escuro): `background: var(--night)`. Header "◉ radar ao vivo". `RadarAnim` 150×150 (radar verde com sweep girando 4s linear infinite). Countdown "próx. varredura 02 : 47 : 12" em display serif. Log de captura recente em mono. Streak: número 12d em red + grade de 14 quadrados (12 preenchidos red, 2 vazios).

**Interação**: drag horizontal no card central. Threshold ±80px. Overlay stamps animam com opacity proporcional ao drag. Commit anima 600px de translação + auto-avança para próxima ideia depois de 280ms.

### C2 · Dossiê (detalhe de uma ideia)

**Propósito**: análise profunda da ideia que virou aposta.

**Layout**: pasta manila central em meio a dotted grid. Aba dourada `dossiê NNN · setor` no canto superior esquerdo da pasta (background gold, border ink, padding 4px 18px 6px mono uppercase). Carimbo "aposta" rotacionado no canto superior direito.

Grid interno 2 colunas (`1.3fr · 1fr`):
- **Esquerda**: eyebrow vermelho "setor · subsetor · slug". Manchete display 44px. Lead serif italic 16px. Tags row. Régua. Quatro mini-seções em grid 2×2: contexto, modelo, concorrência, distribuição. Cada uma com eyebrow mono + parágrafo serif 13/1.55. Marca-texto inline com `background: var(--highlight)`.
- **Direita**: placeholder de capa. Mini stats 2×2 (TAM, bares informais, investimento, MVP). Histórico tipo log (data · evento). Anotação à mão: caixa amarela `#f6e8b8 · border: 1px solid var(--gold) · padding: 10px`, com label "anotação à mão" gold mono e texto serif italic.

### C3 · Mesa de trabalho (kanban)

**Propósito**: visão geral do pipeline de ideias aceitas.

**Layout**: dotted grid fundo. Header com display "mesa de trabalho · NN fichas" + linha mono "arraste para mover entre colunas". Direita: filter chips (★ ≥ 3, setor: todos, + filtro).

4 colunas:
1. **I · RADAR** — caiu agora (12)
2. **II · VALIDANDO** — investigação · TAM (5)
3. **III · APOSTA** — pipeline real (3)
4. **IV · BUILD** — MVP rolando (1)

Cada coluna é um cartão com `background: rgba(251,246,232,0.6) · border: 1px solid var(--ink) · padding: 14px`. Header da coluna: nome mono uppercase bold à esquerda, contador circular preto à direita (22×22, paper text). Sub-label mono mid embaixo. Cards (PinCard) empilhados com gap 12, **sem rotação**. Footer pontilhado "+ nova ficha" mono mid.

PinCard: idêntico ao IdeaCard compact, com adição de pin vermelho redondo (10×10 absolute top -6 left 50%) e box-shadow neo-stamp.

### C4 · Gabinete (dashboard mensal)

**Propósito**: retrospectiva mensal do agente.

**Layout**: dotted grid fundo. Header: eyebrow "gabinete · diário do agente" + display 42px "edição n.º 17 · maio". Carimbo "encerrado 31/05" à direita.

Stats row: 6 colunas `Mini` (ideias passadas, anotadas, apostas, descartadas, streak, fontes ativas). Mini = `border: 1px solid var(--ink) · padding: 8px 10px · background: var(--card)`, número display 22px + label mono 9 uppercase.

Painéis row: 3 colunas Box equal:
1. **Calendário do mês**: heatmap 7×N (31 dias). Cada célula: `aspect-ratio: 1 · border: 1px solid rule-soft`. Background `rgba(200,85,61, opacity)` onde opacity = min(0.15 + v*0.1, 0.9) e v é a contagem de ideias daquele dia. Dia numerado no centro mono 10px.
2. **Por setor**: barras horizontais. Cada linha: label mono uppercase 70px + barra (height 14, paper-soft bg, fill colorido por setor) + número mono. Box de tendência amarela ao final.
3. **Apostas do mês**: lista numerada 01–04 com título display + categoria mono + estrelas.

Box wrapper: `background: var(--card) · border: 1px solid var(--ink) · padding: 16px`. Título "¶ ${name}" mono uppercase mid com border-bottom rule-soft.

### C5 · Mobile

380×780. Status bar (9:41 mono). Masthead editorial compacto: logo 24px + "radar mensal" display 28px + linha mono "n.º 17 · 17 novas · streak 12d", border-bottom 3px double ink. Body com dotted grid, hint mono "← passa · aposta →" no topo, IndexCard rotacionado -1° com shadow neo-stamp, 3 KeyBtns embaixo. Bottom nav: tabbar 4 itens (radar/pipe/dossiê/painel) com ícone mono + label mono uppercase, item ativo em vermelho.

## Interações & Behavior

### Swipe
- Threshold: ±80px horizontal (direção C) ou ±80px vertical (direção B alternativa)
- Drag em tempo real com `transform: translate() rotate()` aplicado via React state
- Stamp overlays com `opacity: drag.x / 100` (ou `-drag.x / 100`)
- Commit: animação 280ms com `transition: transform 250ms cubic-bezier(.2,.7,.3,1)`, depois auto-avança índice

### Atalhos de teclado (sugestão)
- `←` → passa
- `→` → aposta  
- `↓` ou `Space` → anota
- `1`–`5` → setar estrelas
- `/` → focus na busca

### Animações
- Radar sweep: `@keyframes sweep { from { rotate(0) } to { rotate(360deg) } }` 4s linear infinite
- Hover no card: `box-shadow: 0 4px 16px rgba(26,22,18,0.08) · transition: 150ms`
- Carimbo APOSTA aplicado: scale 0 → 1.2 → 1 com rotate, 320ms ease-out, sound effect opcional (stamp.mp3)

### Estados
- **Loading** (radar varrendo): `RadarAnim` centralizado + texto serif italic "varrendo fontes..."
- **Empty state** (nada no radar hoje): manchete display "Nada novo no radar hoje. Volta amanhã." + placeholder do radar parado
- **Erro**: mesma estética, "Sinal perdido. Verifica suas fontes." em red

## State Management

Estado mínimo necessário:

```ts
type Idea = {
  id: string
  title: string
  summary: string
  source: string         // ex: "producthunt.com · 03 abr"
  category: string       // ex: "fintech · social"
  model: string          // ex: "SaaS · freemium"
  tags: string[]         // labels do TAG_STYLES
  stars: number          // 1-5
  verdict: 'pos' | 'neu' | 'neg'
  art: string            // placeholder / image url
  status: 'radar' | 'validating' | 'apost' | 'building' | 'rejected'
  createdAt: Date
  acceptedAt?: Date
  notes?: string
}

type AppState = {
  ideas: Idea[]
  currentIdx: number             // posição no feed
  streak: number                 // dias seguidos
  filters: {
    minStars: number
    sectors: string[]
    showCommon: boolean
  }
  sources: Source[]              // configurações de scraping
}
```

Mutations principais:
- `accept(id)`: `status → 'radar'` (entra no kanban), `acceptedAt = now()`, incrementa `currentIdx`
- `reject(id)`: `status → 'rejected'`, incrementa `currentIdx`
- `move(id, status)`: drag-and-drop entre colunas do kanban

## Assets

- **Fontes**: Google Fonts (4 famílias, listadas acima). Carregar com `display=swap` para evitar FOIT.
- **Imagens de produto**: o protótipo usa placeholders riscados (component `Placeholder` em `shared.jsx`). No app real, ou faz screenshot automatizado do site origem da ideia, ou usa OpenGraph thumbnail, ou deixa o placeholder mesmo.
- **Ícones**: prefira glyphs Unicode (◉ ◇ ↑ ♥ ∞ ★ ✎ ~ = ? ! ×) para tags e atalhos — coerente com a estética typewriter. Para chrome de UI (lupa de busca, menu, fechar), use lucide-react ou Tabler Icons em `stroke-width: 1.5` para combinar com a régua editorial.
- **Logomark**: SVG inline em `shared.jsx → RadarMark`. Copie a função e use em todo lugar onde precisar. Sweep animado em `RadarAnim`.

## Stack recomendada (se ainda não houver)

- **Framework**: Next.js 14+ App Router (ou Remix)
- **Estilo**: Tailwind CSS + arbitrary values para os hex específicos da paleta. Ou Vanilla Extract / CSS Modules se preferir não usar utility-first. Configure os tokens da paleta em `tailwind.config` sob `theme.extend.colors`.
- **Componentes base**: shadcn/ui (Dialog, Popover, DropdownMenu) — mas estilizar bem por cima pra fugir do look padrão shadcn.
- **Animação**: Framer Motion para swipe/drag (`useMotionValue`, `useTransform`). Threshold + commit em `onDragEnd`.
- **Estado**: Zustand para o feed/kanban (state simples, sem servidor). Para multi-device sync, Supabase ou Convex.
- **Scraping**: backend separado (Cloudflare Workers + cron) que roda diariamente e popula um banco. O front só consome.

## Files in this bundle

| Arquivo | Conteúdo |
|---|---|
| `index.html` | Entry-point que monta o design canvas com todas as seções |
| `styles.css` | Design tokens (CSS custom properties) + classes utilitárias compartilhadas |
| `shared.jsx` | Dados de exemplo (IDEAS, TAG_STYLES), componentes Tag/Stars/Placeholder/RadarMark/RadarAnim |
| `foundations.jsx` | Artboards: marca, paleta, tipografia, voz, sistema de tags, anatomia do card |
| `direction-a.jsx` | Direção A (Dispatch): masthead editorial · feed/kanban/detalhe/dashboard/mobile |
| `direction-b.jsx` | Direção B (Dossiê): topbar dark · caderno/mesa/dossiê/gabinete/mobile/onboarding |
| `direction-c.jsx` | **Direção C (escolhida)**: mashup das duas. Use isto como referência principal. |
| `logos.jsx` | 4 opções de logomarca (radar escolhida; outras só para histórico) |
| `design-canvas.jsx` | Apenas o palco de apresentação. Ignore no app real. |

## Como começar a implementação

1. **Copie os design tokens** do `styles.css` (`:root` block) para o `tailwind.config.ts` ou `globals.css` do projeto.
2. **Carregue as Google Fonts** no `<head>` (ou via `next/font`).
3. **Implemente `<Tag>`, `<Stars>`, `<IndexCard>`, `<Stamp>` primeiro** — são reusados em todas as telas.
4. **Construa o Feed (C1) antes do resto** — é o coração do produto e usa todos os componentes.
5. **Use o `direction-c.jsx` como referência visual primária**, e `foundations.jsx` para o sistema de tags.
6. Cada `Art*` function corresponde a uma tela completa — leia o JSX para entender layout exato.
