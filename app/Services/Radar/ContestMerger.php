<?php

namespace App\Services\Radar;

use App\Enums\EventType;
use App\Models\Contest;
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
    /**
     * Prioridade, da mais alta pra mais baixa: oficial > agregador (scrape
     * ou seed sem URL oficial, mesma prioridade — docs/05-seed-inicial.md)
     * > llm sem fonte oficial. Campo vazio sempre aceita a escrita, não
     * importa a prioridade.
     *
     * @param  array<string, mixed>  $fields  coluna => valor (novos dados)
     * @param  array<int, array{type: string, date: string, label?: string|null}>  $extraEvents
     */
    public function merge(Contest $contest, array $fields, string $origin, ?string $sourceUrl, string $confidence, array $extraEvents = []): Contest
    {
        foreach ($fields as $field => $value) {
            if ($value === null) {
                continue;
            }

            if ($field === 'states') {
                $this->mergeStates($contest, $value, $origin, $sourceUrl, $confidence);

                continue;
            }

            $this->mergeField($contest, $field, $value, $origin, $sourceUrl, $confidence);
        }

        return $this->apply($contest, $extraEvents, $origin);
    }

    private function mergeField(Contest $contest, string $field, mixed $value, string $origin, ?string $sourceUrl, string $confidence): void
    {
        $current = $contest->{$field};
        $isEmpty = $current === null || $current === [] || $current === '';

        if (! $isEmpty && $this->priority($origin, $sourceUrl) < $this->currentPriority($contest, $field)) {
            return;
        }

        $contest->{$field} = $value;
        $this->recordFieldSource($contest, $field, $origin, $sourceUrl, $confidence);
    }

    /**
     * `states` é união com o que já existia, nunca substituição — a não ser
     * que a fonte nova seja oficial (docs/06-filtro-estados.md).
     *
     * @param  array<int, string>  $incoming
     */
    private function mergeStates(Contest $contest, array $incoming, string $origin, ?string $sourceUrl, string $confidence): void
    {
        $isOfficial = $this->priority($origin, $sourceUrl) === 3;

        $contest->states = $isOfficial
            ? array_values(array_unique($incoming))
            : array_values(array_unique([...$contest->states, ...$incoming]));

        $this->recordFieldSource($contest, 'states', $origin, $sourceUrl, $confidence);
    }

    private function recordFieldSource(Contest $contest, string $field, string $origin, ?string $sourceUrl, string $confidence): void
    {
        $sources = $contest->field_sources;
        $sources[$field] = [
            'url' => $sourceUrl,
            'fetched_at' => Carbon::now()->toIso8601String(),
            'origin' => $origin,
            'confidence' => $confidence,
        ];
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
     * 3 = oficial (URL .gov.br), 2 = agregador ou seed sem URL oficial,
     * 1 = llm sem fonte oficial.
     */
    private function priority(string $origin, ?string $sourceUrl): int
    {
        if ($sourceUrl !== null && Str::contains($sourceUrl, '.gov.br')) {
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
    public function apply(Contest $contest, array $extraEvents = [], string $origin = 'scrape'): Contest
    {
        $statusChanged = $contest->isDirty('status');
        $from = $statusChanged ? $contest->getOriginal('status') : null;
        $to = $contest->status->value;

        $contest->save();

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
