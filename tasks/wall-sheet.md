# Plano — Folha da parede (24/11/2026)

Branch: `feat/wall-sheet`. Tipo: feat. Pedido do dono, fora das tardes T0–T6; não toca Backlog v2 (não é export `.ics` nem anotação livre no app — as anotações são à caneta, no papel).

**Objetivo**: botão "imprimir p/ parede" na mesa → página `/wall-sheet` com uma folha A4 retrato, P&B, para colar na parede e marcar à mão.

**Conteúdo da folha**
1. Próximas datas: eventos de hoje em diante dos concursos acompanhados, em lista ordenada por data (não grade). Linha = [ ] · data · dia da semana · dias que faltam · concurso · evento. Provas em negrito.
2. Fase de cada concurso: uma linha por acompanhado (prova mais próxima primeiro) com 9 quadrados vazios — edital previsto, edital publicado, inscrito, taxa paga, local de prova, prova feita, gabarito/recurso, resultado, nomeação. A fase atual do sistema aparece em texto ("hoje: …").
3. Sem data à vista: acompanhados sem nenhum evento futuro, com linha em branco para a previsão.
4. Anotações: linhas pautadas que ocupam o resto da folha; "atualizado em" no topo.

**"Acompanhado"** = `visible()` e (na maleta ou `enrollment_status` ∈ interested/enrolled+). Ignora o filtro de estados.

**Não-escopo**: migration, LLM, geração de PDF no servidor, QR code (proposto como extra, aguardando OK).

**Critérios de aceite**
1. Ctrl+P / botão imprime só a folha (barra de cima com `print:hidden`), `@page` A4 retrato 12 mm.
2. Linha do tempo crescente, sem datas passadas.
3. Quadrados de 5 mm, borda preta; nada depende de cor.
4. Tela da mesa só ganha o botão.
5. `tests/Feature/WallSheetControllerTest.php` verde.
