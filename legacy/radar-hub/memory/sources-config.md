# Sources Config

8 fontes obrigatórias para o `radar-scout`. Cada fonte tem um script em `scripts/harvest-{source}.{ps1|py}` que roda **sem LLM** e grava em `harvest/{YYYY-MM-DD}/{source}.json`. Scout consome o JSON pré-digerido.

## Fontes

### 1. Y Combinator (cohorts recentes)
- **URL**: `https://yc-oss.github.io/api/companies/all.json`
- **Método**: REST GET, JSON puro
- **Auth**: nenhuma
- **Extrair**: `name, batch, one_liner, long_description, tags, status, website`
- **Filtro**: `status == "Active"` e batch nos 2 cohorts mais recentes (ex.: W26, S25)
- **Peso**: 1.0 (signal premium — validado por aceleradora)

### 2. Show HN (Hacker News Algolia API)
- **URL**: `https://hn.algolia.com/api/v1/search_by_date?tags=show_hn&hitsPerPage=100`
- **Método**: REST GET, JSON puro
- **Auth**: nenhuma
- **Extrair**: `title, url, author, points, num_comments, story_text, created_at`
- **Filtro**: `points >= 30`, últimos 30 dias
- **Peso**: 0.8 (early-stage indie com tração mínima)

### 3. ArXiv (AI aplicado)
- **URL**: `http://export.arxiv.org/api/query?search_query=cat:cs.AI+OR+cat:cs.LG+OR+cat:cs.CL&start=0&max_results=200&sortBy=submittedDate&sortOrder=descending`
- **Método**: REST GET, Atom XML
- **Auth**: nenhuma
- **Extrair**: `title, summary, authors, link, published, categories`
- **Filtro**: abstract contém "production", "application", "deployed", ou "real-world"; últimos 30 dias
- **Peso**: 0.5 (lead indicator de 6 meses — não é tração hoje)

### 4. Product Hunt
- **URL**: GraphQL `https://api.producthunt.com/v2/api/graphql`
- **Método**: POST GraphQL
- **Auth**: Bearer token (criar em https://api.producthunt.com/v2/oauth/applications, free)
- **Extrair**: `name, tagline, description, votesCount, makers, topics, website, launchedAt`
- **Filtro**: launched últimos 30 dias, `votesCount >= 100`
- **Peso**: 0.7 (validação inicial de demanda)
- **Setup**: token em `scripts/.env` (NÃO commitado)

### 5. GitHub Trending
- **URL**: scrape de `https://github.com/trending?since=monthly`
- **Método**: HTML scrape (sem API oficial)
- **Auth**: opcional (token aumenta rate limit)
- **Extrair**: `repo_name, description, language, stars_gained, total_stars, repo_url`
- **Filtro**: `stars_gained >= 100` no mês
- **Peso**: 0.6 (tech-first, fraco em valor de negócio direto)

### 6. Indie Hackers
- **URL**: RSS `https://www.indiehackers.com/feed.xml`
- **Método**: RSS parse
- **Auth**: nenhuma
- **Extrair**: `title, link, summary, author, pub_date`
- **Filtro**: posts em "Starting Up" e "Growth"
- **Peso**: 0.6 (founder-side stories, MRR transparency)

### 7. Newsletters (Ben's Bites + Latent Space)
- **URL**: Ben's Bites `https://bensbites.beehiiv.com/feed`; Latent Space `https://www.latent.space/feed`
- **Método**: RSS parse
- **Auth**: nenhuma
- **Extrair**: `title, link, summary, pub_date`
- **Filtro**: últimos 30 dias
- **Peso**: 0.7 (curadoria humana de AI launches)

### 8. Crunchbase (funding recent)
- **Limitação**: free tier inviável → **substituído por WebSearch ad-hoc**
- **Método**: scout faz no máx **3 WebSearches** por batch com query tipo `"Series A AI [vertical] 2026 funding"`
- **Cache**: resultado em `memory/funding-cache.json`, TTL 90 dias
- **Peso**: 0.5 (lagging indicator, mas valida tese de mercado)

### 9. TAAFT — There's An AI For That
- **URL**: scrape de `https://theresanaiforthat.com/new/` e `/trending/`
- **Método**: HTML scrape (sem API pública oficial)
- **Auth**: nenhuma
- **Extrair**: `tool_name, tagline, category, tags, upvotes, launch_date, website`
- **Filtro**: launched últimos 30 dias, `upvotes >= 50`, OR top 30 trending da semana
- **Peso**: 0.7 (catálogo AI-native denso, ângulo complementar ao Product Hunt — tem ferramentas que PH não indexa)
- **Risco**: scrape pode quebrar se site mudar markup. Mitigação: harvest script registra erro e segue com as outras 8 fontes.

## Fontes regionais (track Brasil → Nordeste)

Track paralelo: descobrir o que existe no Brasil (especialmente Sul/Sudeste) mas ainda não chegou ao Nordeste. Scout regional consulta:

| Fonte | URL | Método | Peso |
|---|---|---|---|
| Distrito Reports | `https://distrito.me/relatorios/` | HTML scrape mensal | 0.9 |
| ABStartups newsletter | `https://abstartups.com.br/feed/` | RSS | 0.7 |
| StartSe news | `https://startse.com/feed/` | RSS | 0.6 |
| Brazil Journal | `https://braziljournal.com/feed/` | RSS | 0.7 |
| NeoFeed | `https://neofeed.com.br/feed/` | RSS | 0.6 |
| LinkedIn search ad-hoc | WebSearch: `startup [vertical] -site:linkedin.com/in/* "São Paulo" OR "São Paulo" OR "Rio"` | WebSearch (3/batch) | 0.5 |

**BR→NE validation**: scout regional consulta dados públicos pra verificar se startup tem operação real em Pernambuco/Bahia/Ceará/etc. Heurística: site em PT-BR, time em LinkedIn com localização NE, parceria com Cesar/Porto Digital/etc. Se não acha sinais → marca como "ausente no NE, potencial de tropicalização regional".

## Triangulação

Uma ideia precisa de **≥2 sinais cruzados** pra entrar na pool. Exemplos:
- YC + Product Hunt → forte (validação + demanda)
- ArXiv + GitHub trending → tese técnica madura
- Indie Hackers + Show HN → tração real indie

Cálculo:
```
triangulation_score = soma(pesos das fontes onde apareceu) / soma(pesos máximos possíveis)
```

## BR Validation

Após triangular, scout valida gap BR via WebSearch:
- Query template: `"{conceito em PT}" OR "{conceito em EN}" Brasil concorrente startup site:br`
- **Teto: 5 searches por ideia.** Hard cap, librarian rejeita se exceder.
- Cache em `memory/br-competitor-checks.json` (TTL 90 dias) indexado por hash da query normalizada
- Output: `br_gap_confidence` ∈ `{low, medium, high}`
- **Linguagem mandatória**: scout NUNCA escreve "não existe no BR". Escreve "não encontrei evidência de competidor BR ativo em N searches" + nível de confiança

## Score final por ideia

```
final_score = triangulation_score   * 0.4
            + br_gap_confidence_num * 0.3
            + executability_score   * 0.2
            + tropicalizable_score  * 0.1
```

Onde:
- `br_gap_confidence_num`: high=1.0, medium=0.6, low=0.2
- `executability_score`: scout estima viabilidade técnica em 4 semanas pra dev solo (0-1)
- `tropicalizable_score`: scout estima fit cultural/regulatório/infra BR superficialmente (0-1, refinado depois pelo tropicalizer)

Top 5 destacadas (final_score desc). Restante 20-25 = long-list.

## Filtro Must Have (MoSCoW) — mandatório

Cada ideia, antes de entrar na long-list, **precisa** declarar:

1. **A dor resolvida** — formato literal: `"Eles resolvem a dor de [PERSONA] em [CONTEXTO] que [DOR ESPECÍFICA] gerando [CONSEQUÊNCIA TANGÍVEL]"`
2. **A solução exata** — formato: `"Com [TECNOLOGIA/ABORDAGEM] que [O QUE FAZ NA PRÁTICA]"`
3. **Classificação MoSCoW da dor**:
   - **Must Have**: usuário paga, sofre, ou contrata workaround caro hoje. Evidência mensurável (churn baixo, MRR comprovado, willingness-to-pay reportado).
   - **Like to Have**: melhora a vida, mas existe workaround tolerável (Excel, Notion, processo manual).
   - **Good to Have**: nice-to-have, vive sem.
4. **Evidência** — citação concreta da fonte que justifica a classificação. Sem evidência → classifica como Like-to-Have por default.

Scout passa pra long-list **apenas Must Have**. Like-to-Have vai pra pool secundária em `memory/ideas/like-to-have/` (consultável manualmente, não entra no email).

## Anti-padrões mandatórios

Scout DEVE rejeitar ideias que caiam em qualquer um:
- "AI for X" vago sem ICP definido
- Verticais já dominadas no BR sem ângulo novo (ex.: "Nubank pra pequenos", "iFood pra Y")
- Dependências de regulação BR pendente (Open Insurance fora do escopo, por ex.)
- Mercados que exigem CAPEX alto (hardware, logística física pesada)
- Cópias diretas de YC sem tropicalização possível
