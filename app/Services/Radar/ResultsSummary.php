<?php

namespace App\Services\Radar;

use App\Enums\EnrollmentStatus;
use App\Models\Contest;
use App\Models\ExamResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Agregados do painel de resultados (docs/03-ui-inventario.md → "Painel").
 * PHP puro sobre concursos já carregados com `results` — sem LLM, sem query
 * própria. Toda comparação entre concursos é em % do máximo, porque cada
 * banca usa uma escala.
 */
class ResultsSummary
{
    /** @var array<int, string> contest_id => rótulo sem colisão */
    private array $labels = [];

    /**
     * @param  Collection<int, Contest>  $contests  todos os concursos, com `results` carregado
     * @return array<string, mixed>
     */
    public function build(Collection $contests): array
    {
        $entries = $contests
            ->map(fn (Contest $c) => ['contest' => $c, 'result' => $c->objectiveResult()])
            ->filter(fn (array $e) => $e['result'] !== null)
            ->sortBy(fn (array $e) => $this->dateOf($e['contest'], $e['result']) ?? '9999-12-31')
            ->values();

        $this->labels = $this->labels($entries->pluck('contest'));

        return [
            'kpis' => $this->kpis($contests, $entries),
            'timeline' => $entries->map(fn (array $e) => [
                ...$this->identity($e['contest']),
                'date' => $this->dateOf($e['contest'], $e['result']),
                'percent' => $e['result']->percent(),
                'cutoff_percent' => $e['result']->cutoffPercent(),
                'outcome' => $e['result']->outcome->value,
                'outcome_label' => $e['result']->outcome->label(),
                'source_label' => $e['result']->source->label(),
            ])->all(),
            'margins' => $entries
                ->filter(fn (array $e) => $e['result']->cutoffPercent() !== null)
                ->map(fn (array $e) => [
                    ...$this->identity($e['contest']),
                    'margin_points' => round($e['result']->percent() - (float) $e['result']->cutoffPercent(), 1),
                    'margin' => $e['result']->margin(),
                ])
                ->values()
                ->all(),
            'subjects' => $this->subjects($entries),
            'funnel' => $this->funnel($contests, $entries),
        ];
    }

    /**
     * @param  Collection<int, Contest>  $contests
     * @param  Collection<int, array{contest: Contest, result: ExamResult}>  $entries
     * @return array<string, mixed>
     */
    private function kpis(Collection $contests, Collection $entries): array
    {
        $best = $entries->sortByDesc(fn (array $e) => $e['result']->percent())->first();

        return [
            'exams_taken' => $contests->filter(fn (Contest $c) => $c->enrollment_status === EnrollmentStatus::ExamTaken)->count(),
            'with_result' => $entries->count(),
            'approved' => $entries->filter(fn (array $e) => $e['result']->outcome->isApproved())->count(),
            'average_percent' => $entries->isEmpty() ? null : round($entries->avg(fn (array $e) => $e['result']->percent()), 1),
            'best' => $best === null ? null : [
                ...$this->identity($best['contest']),
                'percent' => $best['result']->percent(),
            ],
        ];
    }

    /**
     * Soma pontos e máximos por matéria (nome sem diferença de caixa/espaço)
     * e devolve a % agregada, da pior pra melhor. `$entries` vem em ordem de
     * data, então o rótulo fica com a grafia mais recente.
     *
     * @param  Collection<int, array{contest: Contest, result: ExamResult}>  $entries
     * @return array<int, array{subject: string, percent: float, exams: int}>
     */
    private function subjects(Collection $entries): array
    {
        $groups = [];

        foreach ($entries as $entry) {
            foreach ($entry['result']->breakdown ?? [] as $row) {
                $key = mb_strtolower(trim($row['subject']));

                if ($key === '' || (float) $row['max'] <= 0) {
                    continue;
                }

                $groups[$key] ??= ['subject' => '', 'score' => 0.0, 'max' => 0.0, 'exams' => []];
                $groups[$key]['subject'] = trim($row['subject']); // vale a grafia mais recente
                $groups[$key]['score'] += (float) $row['score'];
                $groups[$key]['max'] += (float) $row['max'];
                $groups[$key]['exams'][$entry['contest']->id] = true;
            }
        }

        return collect($groups)
            ->map(fn (array $g) => [
                'subject' => $g['subject'],
                'percent' => round($g['score'] / $g['max'] * 100, 1),
                'exams' => count($g['exams']),
            ])
            ->sortBy('percent')
            ->values()
            ->all();
    }

    /**
     * Quantos concursos chegaram a cada passo (descartados fora).
     *
     * @param  Collection<int, Contest>  $contests
     * @param  Collection<int, array{contest: Contest, result: ExamResult}>  $entries
     * @return array<int, array{key: string, label: string, count: int}>
     */
    private function funnel(Collection $contests, Collection $entries): array
    {
        $reached = fn (EnrollmentStatus $step) => $contests
            ->filter(fn (Contest $c) => ($c->enrollment_status->order() ?? -1) >= $step->order())
            ->count();

        return [
            ['key' => 'interested', 'label' => 'quero fazer', 'count' => $reached(EnrollmentStatus::Interested)],
            ['key' => 'enrolled', 'label' => 'inscrito', 'count' => $reached(EnrollmentStatus::Enrolled)],
            ['key' => 'exam_taken', 'label' => 'prova feita', 'count' => $reached(EnrollmentStatus::ExamTaken)],
            ['key' => 'with_result', 'label' => 'com resultado', 'count' => $entries->count()],
            ['key' => 'approved', 'label' => 'aprovado/CR', 'count' => $entries->filter(fn (array $e) => $e['result']->outcome->isApproved())->count()],
        ];
    }

    /**
     * @return array{contest_id: int, label: string}
     */
    private function identity(Contest $contest): array
    {
        return [
            'contest_id' => $contest->id,
            'label' => $this->labels[$contest->id] ?? $this->baseLabel($contest),
        ];
    }

    /**
     * "SIGLA ano"; quando dois concursos com nota colidem (ex. dois cargos da
     * Transpetro 2026), acrescenta o trecho final do título pra separar.
     *
     * @param  Collection<int, Contest>  $contests
     * @return array<int, string>
     */
    private function labels(Collection $contests): array
    {
        $counts = $contests->countBy(fn (Contest $c) => $this->baseLabel($c));

        return $contests->mapWithKeys(function (Contest $c) use ($counts) {
            $base = $this->baseLabel($c);

            if ($counts[$base] < 2) {
                return [$c->id => $base];
            }

            $tail = trim((string) Str::afterLast(Str::afterLast($c->title, '—'), ','));

            return [$c->id => $base.' · '.Str::limit($tail, 22, '…')];
        })->all();
    }

    private function baseLabel(Contest $contest): string
    {
        return ($contest->organ_acronym ?? $contest->organ).' '.$contest->year;
    }

    private function dateOf(Contest $contest, ExamResult $result): ?string
    {
        return $result->taken_on?->toDateString() ?? $contest->exam_date?->toDateString();
    }
}
