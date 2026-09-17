# Agente de enriquecimento — dossiê

Registrado por CLAUDE.md regra 6. Roda em `EnrichContestJob` (T3, `docs/02-pipeline.md` → "3. Enriquecimento"). Prompt de sistema real fica em `resources/prompts/dossie.md` — este doc é a referência de humano + o schema que `App\Services\Radar\DossierValidator` valida.

## Quando roda

Só para concursos com lacuna, selecionados por `EnrichContestsJob` (ver critério em `docs/02-pipeline.md`). Nunca para expirados, descartados, ou fora do filtro de estados (exceto fixados/inscritos).

## Input que o agente recebe

Texto simples (não JSON) montado por `EnrichContestJob`:
- Campos já conhecidos do concurso (`organ`, `organ_acronym`, `year`, `title`, e os que já têm valor).
- `field_sources` atual (pra saber o que já tem fonte oficial e não precisa buscar de novo).
- Trechos de texto dos `raw_listings` ligados a este concurso (título + payload.text de cada fonte que já raspou este concurso).
- Lista explícita de campos em lacuna a preencher.

## Output esperado — schema do dossiê

Responder **só** com um JSON (sem markdown, sem texto fora do JSON) no formato:

```json
{
  "campos": {
    "<nome_do_campo>": {
      "valor": "<string|number|bool|null>",
      "fonte": {
        "url": "<string, url http(s) da página onde achou o dado>",
        "confianca": "high|medium|low"
      }
    }
  },
  "eventos_extra": [
    {
      "tipo": "fee_deadline|result|other",
      "data": "AAAA-MM-DD",
      "rotulo": "<string opcional>"
    }
  ]
}
```

Regras do schema:
- `campos` é um objeto; cada chave deve ser um dos campos permitidos (lista abaixo). Não inventar chave fora dessa lista.
- Campo que não foi encontrado: **omitir a chave** (não escrever `null` dentro de `campos`) — omissão = "não achei nada confiável".
- `fonte.url` é obrigatório em todo campo preenchido (o agente só usa `web_search`, então sempre há uma URL de origem).
- `confianca`: `high` = página oficial (banca/órgão/.gov.br/DOU) confirma o valor; `medium` = agregador de concursos confirma; `low` = inferência indireta (ex. editais anteriores do mesmo órgão).
- `eventos_extra` é opcional; usar só para datas que não cabem em `registration_start`/`registration_end`/`exam_date`.

### Campos permitidos em `campos`

| campo | tipo do `valor` | nota |
|---|---|---|
| `board` | string | nome da banca organizadora |
| `registration_start` | string `AAAA-MM-DD` | |
| `registration_end` | string `AAAA-MM-DD` | |
| `exam_date` | string `AAAA-MM-DD` | |
| `fee_cents` | integer | taxa de inscrição em centavos |
| `salary_min_cents` | integer | só cargos de TI |
| `salary_max_cents` | integer | só cargos de TI |
| `it_vacancies` | integer | 0 = só cadastro reserva |
| `regime` | string | um de `clt`, `statutory`, `temporary` |
| `edital_url` | string | preferir PDF oficial |
| `registration_url` | string | página/portal de inscrição |
| `locations` | array de string | ex. `["Natal/RN"]`, só exibição |

## Validação e retry

`DossierValidator` valida contra este schema via `Illuminate\Support\Facades\Validator`. Inválido → `EnrichContestJob` reanexa a mensagem de erro ao input e tenta **mais uma vez**. Segunda falha → `Contest::enrichment_status = failed`, nada é mergeado.

## Merge

JSON válido → `EnrichContestJob` agrupa os campos de `campos` por `(fonte.url, fonte.confianca)` e chama `ContestMerger::merge()` uma vez por grupo, com `origin: 'llm'`. `eventos_extra` entra como `extraEvents` na última chamada. `Contest::dossier` grava o JSON inteiro (bruto, pós-validação).
