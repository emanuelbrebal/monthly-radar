<?php

namespace App\Services\Radar;

use App\Enums\EventType;
use App\Models\Contest;
use Illuminate\Support\Carbon;

/**
 * Versão mínima (T1): eventos + regra do closed_at + log de mudança de
 * status bruto. A versão completa (prioridade de fonte por campo em
 * field_sources) é da T2 — ver docs/02-pipeline.md → "2. Normalização e dedup".
 */
class ContestMerger
{
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
