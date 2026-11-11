<?php

namespace App\Services\Radar\Sources;

use App\Enums\BrazilianState;
use App\Models\RawListing;
use DOMXPath;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

/**
 * `pci_concursos` (T6, docs/02-pipeline.md → "1. Fontes"). Verificado em
 * 18/09/2026: ao contrário do que a spec original supunha, não existe uma
 * página por estado — `https://www.pciconcursos.com.br/concursos/` já lista
 * TUDO (nacional + 26 estados + DF) numa página só, agrupado por marcadores
 * `<div id="{UF}" class="ua">` em ordem de documento, cada um seguido pelos
 * cartões de concurso (`div.na`/`div.ea`/`div.da`, mesma estrutura) até o
 * próximo marcador. `robots.txt` permite `/concursos/` (só bloqueia
 * `/admin/`, `/pdf/`, `*.php` etc.).
 *
 * O cartão da listagem não traz o link do edital, só a notícia do PCI sobre
 * a abertura — extrair o edital de verdade fica pro enriquecimento por LLM
 * (docs/02-pipeline.md → "3. Enriquecimento"), que já existe pra isso.
 *
 * Verificado em 23/10/2026: 57% dos cards dizem só "Vários Cargos". Pra esses,
 * a notícia do próprio card (`/noticias/...`, permitido no robots.txt) lista
 * os cargos em `#noticia ul li` — vai pra `payload.roles`, fora do
 * content_hash. Só a lista: o corpo da notícia cita "noções de informática"
 * em quase todo edital e casaria o filtro de TI.
 */
class PciConcursosSource implements ContestSource
{
    public function key(): string
    {
        return 'pci_concursos';
    }

    public function enabled(): bool
    {
        return (bool) config('radar.sources.pci_concursos.enabled', true);
    }

    public function fetch(): iterable
    {
        $url = config('radar.sources.pci_concursos.url', 'https://www.pciconcursos.com.br/concursos/');

        $response = $this->request()->retry(2, 1000)->get($url);

        $response->throw();

        $crawler = new Crawler($response->body());
        $currentState = null;
        $knownRoles = $this->knownRoles();
        $detailBudget = (int) config('radar.sources.pci_concursos.max_detail_fetches', 300);

        foreach ($crawler->filter('#concursos > .ua, #concursos > .na, #concursos > .ea, #concursos > .da') as $node) {
            $element = new Crawler($node);

            if (Str::contains($element->attr('class') ?? '', 'ua')) {
                $currentState = $this->normalizeStateMarker($element->attr('id') ?? '');

                continue;
            }

            $listing = $this->parseCard($element, $currentState);

            if ($listing === null) {
                continue;
            }

            if ($this->hidesRoles($element)) {
                $roles = $knownRoles[$listing->external_url] ?? null;

                if ($roles === null && $detailBudget > 0) {
                    $detailBudget--;
                    $roles = $this->fetchRoles($listing->external_url);
                }

                if ($roles !== null) {
                    $listing->payload = [...$listing->payload, 'roles' => $roles];
                }
            }

            yield $listing;
        }
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders([
            'User-Agent' => 'radar-mensal/1.0 (uso pessoal; contato: '.config('radar.contact_email', 'contato@example.com').')',
        ])->timeout(15);
    }

    /**
     * Só o campo de cargos do card (`.cd`) conta — a manchete diz "diversos
     * cargos" mesmo quando o card lista o cargo.
     */
    private function hidesRoles(Crawler $card): bool
    {
        $detail = $card->filter('.cd');

        return $detail->count() > 0
            && preg_match('/v[aá]rios cargos|diversos cargos/iu', $detail->text()) === 1;
    }

    /**
     * Cargos já lidos em runs anteriores, por URL da notícia — evita
     * rebuscar ~275 notícias a cada varredura.
     *
     * @return array<string, array<int, string>>
     */
    private function knownRoles(): array
    {
        return RawListing::query()
            ->where('source', $this->key())
            ->whereNotNull('payload->roles')
            ->get(['external_url', 'payload'])
            ->mapWithKeys(fn (RawListing $raw) => [$raw->external_url => $raw->payload['roles']])
            ->all();
    }

    /**
     * Lista de cargos da notícia. Falha (rede, HTML sem lista) vira null:
     * o card segue só com o próprio texto e a run não cai por uma notícia.
     *
     * @return array<int, string>|null
     */
    private function fetchRoles(string $url): ?array
    {
        usleep((int) config('radar.sources.pci_concursos.detail_delay_ms', 500) * 1000);

        try {
            $response = $this->request()->get($url);
        } catch (Throwable) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $roles = (new Crawler($response->body()))
            ->filter('#noticia ul li')
            ->each(fn (Crawler $li) => trim($li->text(normalizeWhitespace: true)));

        $roles = array_values(array_filter($roles, fn (string $role) => $role !== ''));

        return $roles === [] ? null : $roles;
    }

    /**
     * `id="NACIONAL"` → nationwide; `id="XX"` de UF válida → esse estado;
     * qualquer outro marcador (ex. `id="NORDESTE"`, região com concurso
     * multi-estado) → desconhecido, fica pro enriquecimento resolver.
     *
     * @return string|null 'NACIONAL', a sigla, ou null se desconhecido
     */
    private function normalizeStateMarker(string $id): ?string
    {
        if ($id === 'NACIONAL') {
            return 'NACIONAL';
        }

        $isValidState = in_array($id, array_column(BrazilianState::cases(), 'value'), true);

        return $isValidState ? $id : null;
    }

    /**
     * `.cd` é "R$ X<br><span>Cargos<br><span>Nível</span></span>": `text()`
     * cola tudo ("R$ 6.000,00Analista de TISuperior") e o cargo some no
     * filtro de termo inteiro. Junta os nós de texto com " | ".
     */
    private function joinTextNodes(Crawler $element): string
    {
        $node = $element->getNode(0);
        $textNodes = $node?->ownerDocument !== null ? (new DOMXPath($node->ownerDocument))->query('.//text()', $node) : false;

        if ($textNodes === false) {
            return trim($element->text(normalizeWhitespace: true));
        }

        $parts = [];

        foreach ($textNodes as $text) {
            $part = Str::squish($text->nodeValue ?? '');

            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return implode(' | ', $parts);
    }

    private function parseCard(Crawler $card, ?string $state): ?RawListing
    {
        $link = $card->filter('.ca > a')->first();

        if ($link->count() === 0) {
            return null;
        }

        $title = trim($link->text());
        $externalUrl = $link->attr('href') ?? '';
        $headline = $link->attr('title') ?? '';

        if ($title === '' || $externalUrl === '') {
            return null;
        }

        $detail = $card->filter('.cd')->count() > 0 ? $this->joinTextNodes($card->filter('.cd')) : '';
        $deadline = $card->filter('.ce')->count() > 0 ? trim($card->filter('.ce')->text(normalizeWhitespace: true)) : '';

        $textParts = [$headline, $detail, $deadline];

        // extractStates() (ContestNormalizer) só reconhece UF colada a "/" ou
        // "-" (ex. "Natal/RN"); a coluna .cc do card já dá a sigla certa, sem
        // depender de heurística de texto — mais confiável que adivinhar.
        if ($state === 'NACIONAL') {
            $textParts[] = 'nacional';
        } elseif ($state !== null) {
            $textParts[] = "-{$state}";
        }

        return new RawListing([
            'title' => $title,
            'external_url' => $externalUrl,
            'payload' => [
                'text' => implode(' ', array_filter($textParts, fn ($p) => $p !== '')),
                'links' => [],
                'state' => $state,
            ],
        ]);
    }
}
