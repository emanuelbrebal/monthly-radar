<?php

namespace App\Services\Radar;

use App\Enums\BrazilianState;
use App\Enums\EditalStatus;
use App\Enums\TiArea;
use Illuminate\Support\Str;

/**
 * Extração de sinais a partir de texto raspado. Puro — sem I/O, sem model.
 * Ver docs/02-pipeline.md → "2. Normalização e dedup".
 */
class ContestNormalizer
{
    /**
     * TiArea::value cujas palavras-chave (config('radar.area_keywords'))
     * aparecem no texto. 'all' nunca é retornado aqui — é a união calculada
     * por quem chama.
     *
     * @return array<int, string>
     */
    public function matchedAreas(string $haystack): array
    {
        $normalized = $this->foldForTerms($haystack);
        $areas = [];

        foreach (config('radar.area_keywords', []) as $area => $keywords) {
            foreach ($keywords as $keyword) {
                if (preg_match($this->termPattern($keyword), $normalized) === 1) {
                    $areas[] = $area;

                    break;
                }
            }
        }

        return $areas;
    }

    /**
     * Heurística de sigla (2+ maiúsculas, opcionalmente "-UF") e ano de
     * 4 dígitos (20xx) no título.
     *
     * @return array{acronym: string|null, year: int|null}
     */
    public function extractOrganAcronymYear(string $title): array
    {
        $acronym = null;

        if (preg_match('/\b([A-Z]{2,}(?:-[A-Z]{2})?)/', $title, $m)) {
            $acronym = $m[1];
        }

        $year = null;

        if (preg_match('/\b(20\d{2})\b/', $title, $m)) {
            $year = (int) $m[1];
        }

        return ['acronym' => $acronym, 'year' => $year];
    }

    /**
     * Palavras-chave de status, na ordem em que aparecem na spec. Sem
     * nenhum sinal → EditalStatus::Expected.
     */
    public function extractStatus(string $haystack): EditalStatus
    {
        $normalized = $this->foldForMatching($haystack);

        $signals = [
            'suspenso' => EditalStatus::Suspended,
            'inscricoes encerradas' => EditalStatus::RegistrationClosed,
            'inscricoes abertas' => EditalStatus::RegistrationOpen,
            'edital publicado' => EditalStatus::Published,
            'banca definida' => EditalStatus::InPreparation,
            'autorizado' => EditalStatus::Authorized,
            'previsto' => EditalStatus::Expected,
        ];

        foreach ($signals as $keyword => $status) {
            if (Str::contains($normalized, $keyword)) {
                return $status;
            }
        }

        return EditalStatus::Expected;
    }

    /**
     * Datas `dd/mm/aaaa` classificadas pelo contexto (até 40 caracteres
     * antes da data). Sem contexto reconhecido → type 'other'.
     *
     * @return array<int, array{type: string, date: string}>
     */
    public function extractDates(string $haystack): array
    {
        $contexts = [
            'inscri' => 'registration',
            'prova' => 'exam',
            'resultado' => 'result',
            'taxa' => 'fee_deadline',
        ];

        $dates = [];

        preg_match_all('/(\d{2}\/\d{2}\/\d{4})/', $haystack, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[1] as [$match, $byteOffset]) {
            // preg_match_all devolve offset em bytes; convertendo pra
            // caractere antes de cortar com mb_substr (texto tem acento).
            $charOffset = mb_strlen(substr($haystack, 0, $byteOffset));
            $start = max(0, $charOffset - 40);
            $before = $this->foldForMatching(mb_substr($haystack, $start, $charOffset - $start));

            // O contexto mais próximo da data (maior posição na janela)
            // vence — não o primeiro da lista.
            $type = 'other';
            $bestPosition = -1;

            foreach ($contexts as $keyword => $candidate) {
                $position = mb_strrpos($before, $keyword);

                if ($position !== false && $position > $bestPosition) {
                    $bestPosition = $position;
                    $type = $candidate;
                }
            }

            [$day, $month, $year] = explode('/', $match);

            $dates[] = ['type' => $type, 'date' => "{$year}-{$month}-{$day}"];
        }

        return $dates;
    }

    /**
     * href com "edital" e ".pdf", preferindo domínio de banca/gov.
     *
     * @param  array<int, string>  $links
     */
    public function extractEditalUrl(array $links): ?string
    {
        $candidates = array_values(array_filter(
            $links,
            fn (string $url) => Str::contains(Str::lower($url), 'edital') && Str::endsWith(Str::lower($url), '.pdf'),
        ));

        if ($candidates === []) {
            return null;
        }

        foreach ($candidates as $url) {
            if (Str::contains($url, '.gov.br')) {
                return $url;
            }
        }

        return $candidates[0];
    }

    /**
     * Siglas de UF por trás de "/" ou "-" (Natal/RN, TJ-RN), por alias de
     * nome de estado/capital (config('radar.states.aliases')) e nationwide
     * por sinal textual ou 5+ UFs distintas. Órgão sem lotação informada:
     * não infere, fica vazio.
     *
     * @return array{states: array<int, string>, nationwide: bool}
     */
    public function extractStates(string $haystack): array
    {
        $states = [];

        if (preg_match_all('/[\/\-]([A-Z]{2})\b/', $haystack, $m)) {
            foreach ($m[1] as $uf) {
                if (in_array($uf, array_column(BrazilianState::cases(), 'value'), true)) {
                    $states[] = $uf;
                }
            }
        }

        $normalized = $this->foldForMatching($haystack);

        foreach (config('radar.states.aliases', []) as $alias => $uf) {
            if (Str::contains($normalized, $alias)) {
                $states[] = $uf;
            }
        }

        $states = array_values(array_unique($states));

        $nationwide = Str::contains($normalized, ['nacional', 'todo o brasil', 'todos os estados'])
            || count($states) >= 5;

        return ['states' => $states, 'nationwide' => $nationwide];
    }

    /**
     * Similaridade ≥ 0.85 (similar_text normalizado) contra os órgãos
     * candidatos (mesmo ano). Retorna o candidato mais parecido, ou null.
     *
     * @param  array<int, string>  $candidateOrgans
     */
    public function findSimilarOrgan(string $organ, array $candidateOrgans): ?string
    {
        $normalized = $this->foldForMatching($organ);
        $best = null;
        $bestRatio = 0.0;

        foreach ($candidateOrgans as $candidate) {
            similar_text($normalized, $this->foldForMatching($candidate), $percent);
            $ratio = $percent / 100;

            if ($ratio >= 0.85 && $ratio > $bestRatio) {
                $best = $candidate;
                $bestRatio = $ratio;
            }
        }

        return $best;
    }

    /**
     * Minúsculo e sem acento, pra casar palavra-chave/alias independente
     * de acentuação.
     */
    private function foldForMatching(string $text): string
    {
        return Str::lower(Str::ascii($text));
    }

    /**
     * Fold + pontuação vira espaço, pra "Técnico Judiciário - Informática"
     * casar "tecnico judiciario informatica".
     */
    private function foldForTerms(string $text): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $this->foldForMatching($text)));
    }

    /**
     * Termo inteiro com limite de LETRA (não \b): o card do PCI cola número
     * e texto ("R$ 6.000,00Analista"), e dígito→letra não é limite de \b.
     * A primeira palavra aceita plural ("analistas", "desenvolvedores").
     */
    private function termPattern(string $keyword): string
    {
        $words = array_map(fn (string $w) => preg_quote($w, '/'), explode(' ', $this->foldForTerms($keyword)));
        $words[0] .= '(?:e?s)?';

        return '/(?<![a-z])'.implode(' ', $words).'(?![a-z])/';
    }
}
