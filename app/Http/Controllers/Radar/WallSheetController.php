<?php

namespace App\Http\Controllers\Radar;

use App\Enums\EnrollmentStatus;
use App\Models\Contest;
use App\Models\ContestEvent;
use App\Services\Radar\ContestPhaseResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `GET /wall-sheet` — folha A4 para imprimir e colar na parede. Só leitura,
 * sem LLM. "Acompanhado" = visível e (na maleta ou com "quero fazer"/inscrito
 * ou além); ignora o filtro de estados, porque a folha é sobre o que eu já
 * escolhi acompanhar, não sobre a varredura.
 */
class WallSheetController
{
    public function index(ContestPhaseResolver $phases): Response
    {
        $today = CarbonImmutable::now(config('radar.timezone'))->startOfDay();

        $contests = self::tracked()
            ->with('events')
            ->orderByRaw('exam_date IS NULL, exam_date asc')
            ->orderBy('organ')
            ->get();

        return Inertia::render('radar/wall-sheet', [
            'generatedAt' => $today->toDateString(),
            'timeline' => $this->timeline($contests, $today),
            'contests' => $contests
                ->map(fn (Contest $c) => $this->row($c, $phases, $today))
                ->values()
                ->all(),
        ]);
    }

    /**
     * @return Builder<Contest>
     */
    public static function tracked(): Builder
    {
        $statuses = [EnrollmentStatus::Interested->value, ...EnrollmentStatus::enrolledValues()];

        return Contest::query()
            ->visible()
            ->where(fn (Builder $q) => $q
                ->whereNotNull('pinned_slot')
                ->orWhereIn('enrollment_status', $statuses));
    }

    /**
     * Datas de hoje em diante, em ordem crescente (empate: concurso, depois tipo).
     *
     * @param  Collection<int, Contest>  $contests
     * @return array<int, array<string, mixed>>
     */
    private function timeline(Collection $contests, CarbonImmutable $today): array
    {
        return $contests
            ->flatMap(fn (Contest $c) => $c->events
                ->filter(fn (ContestEvent $e) => $e->date->toDateString() >= $today->toDateString())
                ->map(fn (ContestEvent $e) => [
                    'date' => $e->date->toDateString(),
                    'days_left' => (int) $today->diffInDays(CarbonImmutable::parse($e->date->toDateString(), config('radar.timezone')), false),
                    'contest' => self::shortName($c),
                    'event' => $e->label ?? $e->type->label(),
                    'type' => $e->type->value,
                ]))
            ->sortBy([['date', 'asc'], ['contest', 'asc']])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Contest $contest, ContestPhaseResolver $phases, CarbonImmutable $today): array
    {
        $phase = $phases->resolve($contest->effectiveStatus(), $contest->enrollment_status);
        $hasUpcoming = $contest->events->contains(
            fn (ContestEvent $e) => $e->date->toDateString() >= $today->toDateString(),
        );

        return [
            'id' => $contest->id,
            'name' => self::shortName($contest),
            'title' => $contest->title,
            'board' => $contest->board,
            'exam_date' => $contest->exam_date?->toDateString(),
            'exam_city' => $contest->exam_city,
            'phase_label' => $phase->label(),
            'status_label' => $contest->effectiveStatus()->label(),
            'has_upcoming' => $hasUpcoming,
        ];
    }

    private static function shortName(Contest $contest): string
    {
        return trim(($contest->organ_acronym ?: $contest->organ).' '.$contest->year);
    }
}
