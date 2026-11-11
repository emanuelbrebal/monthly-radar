<?php

namespace App\Services\Radar;

use App\Enums\ChangeKind;
use App\Enums\EventType;
use App\Models\Contest;
use App\Models\ContestChange;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * `merge()` (T2): escreve campo por campo respeitando prioridade de fonte,
 * então delega a `apply()` para eventos + closed_at + log de status.
 * `apply()` sozinho (T1) continua servindo pra quem já atribuiu os campos
 * na mão (ex. ContestSeeder) — ver docs/02-pipeline.md → "2. Normalização e
 * dedup" e CLAUDE.md regra 7.
 */
class ContestMerger
{
    /** Colunas que mudam a cada passada sem ser informação nova sobre o concurso. */
    private const UNTRACKED_CHANGES = [
        'updated_at', 'last_seen_at', 'last_enriched_at', 'status_changed_at', 'closed_at',
        'field_sources', 'dossier', 'enrichment_status', 'source_checked_at',
    ];

    /**
     * Prioridade, da mais alta pra mais baixa: oficial > agregador (scrape
     * ou seed sem URL oficial, mesma prioridade — docs/05-seed-inicial.md)
     * > llm sem fonte oficial. Campo vazio sempre aceita a escrita, não
     * importa a prioridade.
     *
     * `$sourceDate` (AAAA-MM-DD, data de publicação da fonte — revisão T8):
     * em empate de prioridade, fonte mais antiga que a registrada não
     * sobrescreve. Sem data de um dos lados, empate sobrescreve (como antes).
     *
     * @param  array<string, mixed>  $fields  coluna => valor (novos dados)
     * @param  array<int, array{type: string, date: string, label?: string|null}>  $extraEvents
     */
    public function merge(Contest $contest, array $fields, string $origin, ?string $sourceUrl, string $confidence, array $extraEvents = [], ?int $searchRunId = null, ?string $sourceDate = null): Contest
    {
        foreach ($fields as $field => $value) {
            if ($value === null) {
                continue;
            }

            if ($field === 'states') {
                $this->mergeStates($contest, $value, $origin, $sourceUrl, $confidence, $sourceDate);

                continue;
            }

            $this->mergeField($contest, $field, $value, $origin, $sourceUrl, $confidence, $sourceDate);
        }

        return $this->apply($contest, $extraEvents, $origin, $searchRunId);
    }

    /**
     * A escrita de `$field` vinda desta fonte seria aceita? Mesma regra de
     * `merge()` — exposta pra quem precisa registrar a recusa (RefreshContestJob
     * transforma recusa em divergência no dossiê).
     */
    public function accepts(Contest $contest, string $field, string $origin, ?string $sourceUrl, ?string $sourceDate = null): bool
    {
        $current = $contest->{$field};

        if ($current === null || $current === [] || $current === '') {
            return true;
        }

        $incoming = $this->priority($origin, $sourceUrl);
        $existing = $this->currentPriority($contest, $field);

        if ($incoming !== $existing) {
            return $incoming > $existing;
        }

        $currentDate = $contest->field_sources[$field]['source_date'] ?? null;

        return $sourceDate === null || $currentDate === null || $sourceDate >= $currentDate;
    }

    /** Oficial = .gov.br ou um dos `radar.official_domains`, por sufixo do host. */
    public static function isOfficialUrl(?string $url): bool
    {
        $host = $url !== null ? strtolower((string) parse_url($url, PHP_URL_HOST)) : '';

        if ($host === '') {
            return false;
        }

        foreach ((array) config('radar.official_domains', ['gov.br']) as $domain) {
            if ($host === $domain || Str::endsWith($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    private function mergeField(Contest $contest, string $field, mixed $value, string $origin, ?string $sourceUrl, string $confidence, ?string $sourceDate): void
    {
        if (! $this->accepts($contest, $field, $origin, $sourceUrl, $sourceDate)) {
            return;
        }

        $contest->{$field} = $value;
        $this->recordFieldSource($contest, $field, $origin, $sourceUrl, $confidence, $sourceDate);
    }

    /**
     * `states` é união com o que já existia, nunca substituição — a não ser
     * que a fonte nova seja oficial (docs/06-filtro-estados.md).
     *
     * @param  array<int, string>  $incoming
     */
    private function mergeStates(Contest $contest, array $incoming, string $origin, ?string $sourceUrl, string $confidence, ?string $sourceDate): void
    {
        $isOfficial = $this->priority($origin, $sourceUrl) === 3;

        $contest->states = $isOfficial
            ? array_values(array_unique($incoming))
            : array_values(array_unique([...($contest->states ?? []), ...$incoming]));

        $this->recordFieldSource($contest, 'states', $origin, $sourceUrl, $confidence, $sourceDate);
    }

    private function recordFieldSource(Contest $contest, string $field, string $origin, ?string $sourceUrl, string $confidence, ?string $sourceDate): void
    {
        $sources = $contest->field_sources;
        $sources[$field] = array_filter([
            'url' => $sourceUrl,
            'fetched_at' => Carbon::now()->toIso8601String(),
            'origin' => $origin,
            'confidence' => $confidence,
            'source_date' => $sourceDate,
        ], fn ($v, $k) => $k !== 'source_date' || $v !== null, ARRAY_FILTER_USE_BOTH);
        $contest->field_sources = $sources;
    }

    private function currentPriority(Contest $contest, string $field): int
    {
        $current = $contest->field_sources[$field] ?? null;

        if ($current === null) {
            return 0;
        }

        return $this->priority($current['origin'], $current['url']);
    }

    /**
     * 3 = oficial (ver isOfficialUrl), 2 = agregador ou seed sem URL oficial,
     * 1 = llm sem fonte oficial.
     */
    private function priority(string $origin, ?string $sourceUrl): int
    {
        if (self::isOfficialUrl($sourceUrl)) {
            return 3;
        }

        return $origin === 'llm' ? 1 : 2;
    }

    /**
     * Salva o contest (campos já devem estar atribuídos pelo chamador),
     * loga mudança de status bruto, aplica a regra do closed_at e regenera
     * contest_events a partir das colunas de data + eventos extra.
     *
     * @param  array<int, array{type: string, date: string, label?: string|null}>  $extraEvents
     */
    public function apply(Contest $contest, array $extraEvents = [], string $origin = 'scrape', ?int $searchRunId = null, bool $created = false): Contest
    {
        $isNew = $created || ! $contest->exists;
        $changes = $isNew ? [] : $this->collectChanges($contest);
        $statusChanged = $contest->isDirty('status');
        $from = $statusChanged ? $contest->getOriginal('status') : null;
        $to = $contest->status->value;

        $contest->save();

        $this->recordChanges($contest, $isNew, $changes, $origin, $searchRunId);

        if ($statusChanged) {
            $contest->statusLogs()->create([
                'from' => $from,
                'to' => $to,
                'origin' => $origin,
                'created_at' => now(),
            ]);
        }

        $this->applyClosedAtRule($contest);
        $this->regenerateEvents($contest, $extraEvents, $origin);

        return $contest->refresh();
    }

    /**
     * Campos alterados desde o último save, já com cast aplicado. Ignora o que
     * é bookkeeping (carimbos, field_sources, dossiê, status do enriquecimento).
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function collectChanges(Contest $contest): array
    {
        $changes = [];

        foreach (array_keys($contest->getDirty()) as $field) {
            if (in_array($field, self::UNTRACKED_CHANGES, true)) {
                continue;
            }

            $changes[$field] = [
                'old' => $this->plain($contest->getOriginal($field)),
                'new' => $this->plain($contest->{$field}),
            ];
        }

        return $changes;
    }

    /**
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     */
    private function recordChanges(Contest $contest, bool $isNew, array $changes, string $origin, ?int $searchRunId): void
    {
        $base = ['contest_id' => $contest->id, 'search_run_id' => $searchRunId, 'origin' => $origin, 'created_at' => now()];

        if ($isNew) {
            if (! $contest->changes()->where('kind', ChangeKind::Created->value)->exists()) {
                ContestChange::query()->create([...$base, 'kind' => ChangeKind::Created]);
            }

            return;
        }

        foreach ($changes as $field => $change) {
            ContestChange::query()->create([
                ...$base,
                'kind' => ChangeKind::Updated,
                'field' => $field,
                'old_value' => $change['old'],
                'new_value' => $change['new'],
            ]);
        }
    }

    private function plain(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
            default => $value,
        };
    }

    /**
     * Primeira vez que o status efetivo vira "fechado ou posterior" → seta
     * closed_at. Nunca sobrescreve depois, a não ser que reabra (volta a null).
     */
    private function applyClosedAtRule(Contest $contest): void
    {
        $effectiveStatus = $contest->effectiveStatus();

        if ($effectiveStatus->isClosedOrLater()) {
            if ($contest->closed_at === null) {
                $contest->closed_at = Carbon::now();
                $contest->save();
            }

            return;
        }

        if ($contest->closed_at !== null) {
            $contest->closed_at = null;
            $contest->save();
        }
    }

    /**
     * @param  array<int, array{type: string, date: string, label?: string|null}>  $extraEvents
     */
    private function regenerateEvents(Contest $contest, array $extraEvents, string $origin): void
    {
        $contest->events()->delete();

        $rows = [];

        if ($contest->registration_start !== null) {
            $rows[] = ['type' => EventType::RegistrationStart->value, 'date' => $contest->registration_start->toDateString(), 'label' => null];
        }

        if ($contest->registration_end !== null) {
            $rows[] = ['type' => EventType::RegistrationEnd->value, 'date' => $contest->registration_end->toDateString(), 'label' => null];
        }

        if ($contest->exam_date !== null) {
            $rows[] = ['type' => EventType::Exam->value, 'date' => $contest->exam_date->toDateString(), 'label' => null];
        }

        if ($contest->essay_exam_date !== null) {
            $rows[] = ['type' => EventType::Exam->value, 'date' => $contest->essay_exam_date->toDateString(), 'label' => 'prova discursiva'];
        }

        foreach ($extraEvents as $event) {
            $rows[] = [
                'type' => $event['type'],
                'date' => $event['date'],
                'label' => $event['label'] ?? null,
            ];
        }

        // Dedup por (type, date) — colunas core primeiro, extra_events depois.
        $seen = [];

        foreach ($rows as $row) {
            $key = $row['type'].'|'.$row['date'];

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $contest->events()->create([
                'type' => $row['type'],
                'date' => $row['date'],
                'label' => $row['label'],
                'source' => $origin,
            ]);
        }
    }
}
