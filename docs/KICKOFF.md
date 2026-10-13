# Prompt de abertura para o Claude Code

Cole isto como primeira mensagem, depois de copiar `CLAUDE.md` e `docs/` para o repo.

```text
Contexto:
- Este repo (monthly-radar) mudou de produto. Deixou de ser um curador de ideias SaaS e virou um acompanhador pessoal de concursos públicos de TI. O CLAUDE.md e a pasta docs/ já descrevem o novo produto.
- Intenção: eu quero uma maleta estilo inventário de Resident Evil com os concursos que estou acompanhando e um calendário com os prazos, para não perder inscrição nem prova. Precisão de datas e links importa mais do que volume de concursos.
- Restrições: stack atual do repo (Laravel 13, Inertia 3, React 19, Tailwind 4, Postgres). Visual só com os tokens do handoff em design/radar mensal/. A única operação do sistema é a varredura manual por área de TI. Sem cron de busca, sem e-mail, sem multiusuário.

Tarefa: leia CLAUDE.md, docs/00-spec.md e docs/04-plano-execucao.md e execute a T0 (limpeza do pivot). Em seguida, execute a T1 (domínio), de modo que as regras de status efetivo, visibilidade de 24h e pin fiquem cobertas por testes.

Critérios de aceite:
1. Os arquivos listados na T0 foram removidos e o cadastro público está desligado.
2. As migrations e os enums batem com docs/01-modelo-de-dados.md, sem coluna extra.
3. Os testes obrigatórios da camada de domínio existem e passam (php artisan test).
4. O teste de paridade entre isExpired() e scopeVisible() passa.
5. php artisan migrate:fresh --seed carrega os 12 concursos de database/seeders/data/contests-seed.json sem duplicar nada ao rodar de novo.

Formato de saída: ao terminar cada tarde, liste o que foi feito, os comandos de teste rodados com o resultado e as decisões [inferido] que você encontrou nos docs e que precisam da minha confirmação antes da próxima tarde. Se alguma instrução dos docs se contradizer, pare e me pergunte antes de escolher.
```

Próximas tardes: "Execute a T2 conforme docs/04-plano-execucao.md e docs/02-pipeline.md, com o mesmo formato de saída."
