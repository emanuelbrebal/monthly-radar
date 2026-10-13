<?php

namespace App\Support\Radar;

use App\Models\Contest;
use Illuminate\Support\Carbon;

/**
 * Serializa Contest para as props Inertia/JSON da Mesa (T4). Centraliza os
 * campos calculados (status efetivo, "some em Xh") usados por
 * RadarPageController, CalendarController e PinController.
 */
class ContestPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(Contest $contest): array
    {
        $now = Carbon::now();
        $effectiveStatus = $contest->effectiveStatus($now);
        $closedAt = $contest->effectiveClosedAt();
        $disappearsAt = $closedAt?->addDay();

        return [
            'id' => $contest->id,
            'slug' => $contest->slug,
            'organ' => $contest->organ,
            'organ_acronym' => $contest->organ_acronym,
            'year' => $contest->year,
            'title' => $contest->title,
            'board' => $contest->board,
            'fee_cents' => $contest->fee_cents,
            'salary_min_cents' => $contest->salary_min_cents,
            'salary_max_cents' => $contest->salary_max_cents,
            'it_vacancies' => $contest->it_vacancies,
            'field_sources' => $contest->field_sources,
            'areas' => $contest->areas,
            'status' => $contest->status->value,
            'effective_status' => $effectiveStatus->value,
            'effective_status_label' => $effectiveStatus->label(),
            'regime' => $contest->regime->value,
            'states' => $contest->states,
            'nationwide' => $contest->nationwide,
            'registration_start' => $contest->registration_start?->toDateString(),
            'registration_end' => $contest->registration_end?->toDateString(),
            'exam_date' => $contest->exam_date?->toDateString(),
            'edital_url' => $contest->edital_url,
            'registration_url' => $contest->registration_url,
            'enrichment_status' => $contest->enrichment_status->value,
            'enrollment_status' => $contest->enrollment_status->value,
            'dismissed_at' => $contest->dismissed_at?->toIso8601String(),
            'dismissed_reason' => $contest->dismissed_reason,
            'pinned_slot' => $contest->pinned_slot,
            'hides_in_hours' => $disappearsAt !== null && $disappearsAt->isFuture()
                ? (int) ceil($now->diffInMinutes($disappearsAt, false) / 60)
                : null,
            'events' => $contest->events
                ->sortBy('date')
                ->values()
                ->map(fn ($event) => [
                    'id' => $event->id,
                    'type' => $event->type->value,
                    'type_label' => $event->type->label(),
                    'date' => $event->date->toDateString(),
                    'label' => $event->label,
                ])
                ->all(),
        ];
    }
}
