# Plano — Folha da parede (24/11/2026)

Branch: `feat/wall-sheet`. Tipo: feat. Pedido do dono, fora das tardes T0–T6; não toca Backlog v2 (não é export `.ics` nem anotação livre no app — as anotações são à caneta, no papel).

**Objetivo**: botão "imprimir p/ parede" na mesa → página `/wall-sheet` com uma folha A4 retrato, P&B, para colar na parede e marcar à mão.

**Conteúdo da folha**
1. Próximas datas: eventos de hoje em diante dos concursos acompanhados, em lista ordenada por data (não grade). Linha = [ ] · data · dia da semana · dias que faltam · concurso · evento. Provas em negrito.
2. Fase de cada concurso: uma linha por acompanhado (prova mais próxima primeiro) com 9 quadrados vazios — edital previsto, edital publicado, inscrito, taxa paga, local de prova, prova feita, gabarito/recurso, resultado, nomeação. A fase atual do sistema aparece em texto ("hoje: …").
3. Sem data à vista: acompanhados sem nenhum evento futuro, com linha em branco para a previsão.
4. Anotações: linhas pautadas que ocupam o resto da folha; "atualizado em" no topo.

**"Acompanhado"** = `visible()` e (na maleta ou `enrollment_status` ∈ interested/enrolled+). Ignora o filtro de estados.

**Extras aprovados (24/11)**: destaque "próxima prova" no topo com contagem grande de dias; coluna "minha nota" em branco para preencher à mão; QR code do edital (cai para o link de inscrição) gerado com `bacon/bacon-qr-code`, que já vem transitivo pelo Fortify.

**Não-escopo**: migration, LLM, geração de PDF no servidor.

**Critérios de aceite**
1. Ctrl+P / botão imprime só a folha (barra de cima com `print:hidden`), `@page` A4 retrato 12 mm.
2. Linha do tempo crescente, sem datas passadas.
3. Quadrados de 5 mm, borda preta; nada depende de cor.
4. Tela da mesa só ganha o botão.
5. `tests/Feature/WallSheetControllerTest.php` verde.

## Revisão (25/11/2026) — `fix/wall-sheet-fit`

Regra: **só imprime fato absoluto ou já passado** (data, dia da semana, etapa concluída). Nada relativo a "hoje", porque a folha fica semanas na parede.

- Cabe em 1 A4 com dados reais (antes: 2 páginas). Tabela de fases só com quem tem data à vista; "sem data à vista" em 2 colunas.
- Ordem das fases: prova futura mais próxima → próximo evento futuro → nada à vista.
- Destaque: data da prova em destaque + **régua de 1 quadrado por dia** (do dia da impressão até a prova, ≤ 70 dias) para riscar a lápis. Sai o "N dias".
- Próximas datas: sai a coluna "faltam"; linha mais forte entre semanas.
- Fases: sai "hoje: <fase>". Colunas que o app já sabe cumpridas saem **preenchidas de preto** (`WallSheetController::phaseDone`, 0–6). 1ª coluna passa de "edital previsto" (sempre verdadeiro) para **autorizado**. Gabarito/resultado/nomeação seguem só a lápis.
- "conferido __" por concurso (retificação/adiamento); "sem data à vista" perde o status ("previsto").
- Rodapé com legenda: preto = app sabia; marcas a lápis precisam ir pro app antes de reimprimir.
- (26/11) QR para quem não tem edital: edital → inscrição → fonte salva em `field_sources` (oficial primeiro via `ContestMerger::isOfficialUrl`, depois a mais recente) — `WallSheetController::qrLink`. Na tabela, "· QR: fonte/inscrição" no subtítulo; "sem data à vista" vira cards com QR + domínio.
- (26/11) Botão "imprimir paisagem": `@page` injetado pela página conforme a orientação (saiu do `app.css`); paisagem em 2 colunas (próxima prova + datas | fases + sem data + anotações).
