# Feature: filtro por estados (estilo PCI Concursos)

> Adicionada em 15/09/2026. Entra no MVP. Implementar junto com T2 (dados) e T4 (UI).
> Decisões marcadas `[inferido]` não foram confirmadas.

## Comportamento

- Um painel de checkboxes, igual ao do PCI: **Nacional** no topo e os 26 estados + DF agrupados por região (Norte, Nordeste, Centro-Oeste, Sudeste, Sul). Cada região tem um checkbox "região inteira" com estado indeterminado quando só parte dela está marcada.
- A seleção é **persistente** (preferência do usuário) e vale para duas coisas:
  1. **Varredura:** só raspa as páginas dos estados marcados e só gasta LLM com concursos que casam com o filtro.
  2. **Listagem:** calendário e caixa de itens mostram só concursos que casam.
- Nada marcado = todos os estados (equivalente a "tudo marcado"). Padrão ao criar o usuário: tudo marcado, porque aceito lotação em qualquer estado. `[inferido]`
- Descartados nunca aparecem na mesa, com ou sem filtro.
- A **maleta ignora o filtro**, e concurso com inscrição marcada também aparece sempre. Os dois, quando estiverem fora do filtro, ganham a tag `fora do filtro`. `[inferido]`
- Concurso sem estado conhecido (`states = []` e `nationwide = false`) aparece sempre, com a tag `local a definir`. Não dá para filtrar o que não se sabe. `[inferido]`

## Regra de casamento (`StateFilter`, PHP puro, com testes)

Um concurso casa com a seleção `S` quando qualquer uma destas vale:

1. `S` está vazia;
2. `nationwide = true` e `NACIONAL ∈ S`;
3. `states ∩ S ≠ ∅`;
4. `states = []` e `nationwide = false` (local a definir).

Escopo SQL equivalente: `Contest::matchingStates(array $s)`, com teste de paridade contra `StateFilter`. A listagem usa `Contest::visible()->where(fn ($q) => $q->matchingStates($s)->orWhereNotNull('pinned_slot')->orWhereIn('enrollment_status', [...a partir de enrolled]))`.

## Dados

**Enum `BrazilianState`** (`app/Enums/`): 27 valores com a sigla (`AL`, `RN`, `DF`…), com os métodos `label()` ("Alagoas") e `region(): Region`.

**Enum `Region`:** `north`, `northeast`, `midwest`, `southeast`, `south`, com `label()` e `states()`.

**Novas colunas em `contests`:**

| coluna | tipo | nota |
|---|---|---|
| states | jsonb default `[]` | siglas de UF onde há vaga/lotação de TI. GIN index |
| nationwide | boolean default false | abrangência nacional |

`locations` continua existindo (texto livre para exibição, ex. "Natal/RN"). `states` é a versão normalizada para filtro.

**Preferência do usuário:** coluna `users.preferences jsonb default '{}'`, com a chave `states: string[]` (siglas + `"NACIONAL"`). Cast para um value object `UserPreferences`.

## Normalização (`ContestNormalizer` e `ContestMerger`)

Preenchimento de `states`:
- Sigla depois de `/` ou `-` em `locations` e no título (`Natal/RN`, `TJ-RN`).
- Nome de estado ou de capital por extenso (mapa em `config/radar.php → states.aliases`, ex. "Manaus" → AM, "Distrito Federal"/"Brasília" → DF).
- Órgão com sede conhecida e sem lotação informada: **não inferir**. Fica vazio.

`nationwide = true` quando: o texto diz "nacional", "todo o Brasil" ou "todos os estados"; ou quando há 5 ou mais UFs distintas; ou quando a página de origem é a de concursos nacionais do PCI.

O LLM ganha dois campos no schema (`docs/agents/dossie.md → fields`): `states` (array de siglas) e `nationwide` (boolean), com fonte e confiança como os demais. Merge pela mesma regra de prioridade. **União** com o que foi raspado, nunca substituição, a não ser que a fonte seja oficial.

## Varredura

- `POST /sweeps` recebe `{area}` e lê os estados da preferência salva, sem receber estados na request. Os estados usados ficam gravados em `search_runs.meta.states`.
- Adapter `pci_concursos`: em vez da listagem nacional única, busca a página de cada estado marcado mais a página nacional se `NACIONAL` estiver marcado. As URLs ficam em `config/radar.php → sources.pci_concursos.state_urls` e **devem ser verificadas na T2**. Continua valendo o limite de 1 request a cada 2s por host, então marcar tudo deixa a varredura mais lenta: logar a duração.
- Adapters sem página por estado (`provas_de_ti`, `official_pages`): raspam normalmente e o filtro é aplicado depois da normalização.
- `EnrichContestsJob` só enfileira concursos que casam com o filtro (os fixados e os inscritos entram sempre).

## Endpoints

| método | rota | faz |
|---|---|---|
| PATCH | `/preferences/states` | `{states: string[]}`, valida contra o enum + `NACIONAL` |

A tela principal passa a receber `preferences.states` e `regions` (estrutura agrupada, pronta para renderizar).

## UI

Detalhes visuais em `docs/03-ui-inventario.md → Filtro de estados`.

## Testes obrigatórios

- `StateFilter`: as 4 regras, seleção vazia, `NACIONAL` sozinho.
- Paridade `StateFilter` × `matchingStates`.
- Normalizer: "Natal/RN", "TJ-RN", "Brasília", "vagas em todo o Brasil", 5 UFs → nationwide, órgão sem lotação → vazio.
- Listagem: fixado fora do filtro aparece; inscrito fora do filtro aparece; não inscrito fora do filtro some.
- Adapter PCI com `Http::fake`: só as URLs dos estados marcados são chamadas.
