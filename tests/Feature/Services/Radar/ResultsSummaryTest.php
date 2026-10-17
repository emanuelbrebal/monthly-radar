<?php

use App\Models\Contest;
use App\Models\ExamResult;
use App\Services\Radar\ResultsSummary;

function summary(): array
{
    return app(ResultsSummary::class)->build(Contest::query()->with('results')->get());
}

it('returns an empty but well-formed summary without results', function () {
    Contest::factory()->create(['enrollment_status' => 'interested']);

    $s = summary();

    expect($s['kpis'])->toBe(['exams_taken' => 0, 'with_result' => 0, 'approved' => 0, 'average_percent' => null, 'best' => null]);
    expect($s['timeline'])->toBe([]);
    expect($s['margins'])->toBe([]);
    expect($s['subjects'])->toBe([]);
    expect(collect($s['funnel'])->pluck('count', 'key')->all())
        ->toBe(['interested' => 1, 'enrolled' => 0, 'exam_taken' => 0, 'with_result' => 0, 'approved' => 0]);
});

it('aggregates kpis, timeline, margins, subjects and funnel over a fixed dataset', function () {
    $a = Contest::factory()->create(['organ_acronym' => 'AAA', 'year' => 2026, 'enrollment_status' => 'exam_taken']);
    $b = Contest::factory()->create(['organ_acronym' => 'BBB', 'year' => 2026, 'enrollment_status' => 'exam_taken']);
    Contest::factory()->create(['enrollment_status' => 'exam_taken']); // prova feita, sem nota
    Contest::factory()->enrolled()->create();
    Contest::factory()->create(['enrollment_status' => 'interested']);
    Contest::factory()->dismissed()->create();

    ExamResult::factory()->create([
        'contest_id' => $a->id, 'score' => 62, 'max_score' => 70, 'cutoff_score' => 57.5,
        'outcome' => 'reserve', 'taken_on' => '2026-10-11',
        'breakdown' => [['subject' => 'Português', 'score' => 8, 'max' => 10], ['subject' => 'TI', 'score' => 30, 'max' => 40]],
    ]);
    ExamResult::factory()->create([
        'contest_id' => $b->id, 'score' => 40, 'max_score' => 100, 'cutoff_score' => 50,
        'outcome' => 'failed', 'taken_on' => '2026-06-01',
        'breakdown' => [['subject' => ' português ', 'score' => 4, 'max' => 10]],
    ]);

    $s = summary();

    expect($s['kpis']['exams_taken'])->toBe(3);
    expect($s['kpis']['with_result'])->toBe(2);
    expect($s['kpis']['approved'])->toBe(1);
    expect($s['kpis']['average_percent'])->toBe(64.3); // (88.6 + 40) / 2
    expect($s['kpis']['best'])->toBe(['contest_id' => $a->id, 'label' => 'AAA 2026', 'percent' => 88.6]);

    expect(collect($s['timeline'])->pluck('label')->all())->toBe(['BBB 2026', 'AAA 2026']); // por data
    expect($s['timeline'][1]['cutoff_percent'])->toBe(82.1);

    expect(collect($s['margins'])->pluck('margin_points', 'label')->all())->toBe(['BBB 2026' => -10.0, 'AAA 2026' => 6.5]);

    expect($s['subjects'])->toBe([
        ['subject' => 'Português', 'percent' => 60.0, 'exams' => 2], // (8+4)/(10+10), caixa ignorada
        ['subject' => 'TI', 'percent' => 75.0, 'exams' => 1],
    ]);

    expect(collect($s['funnel'])->pluck('count', 'key')->all())
        ->toBe(['interested' => 5, 'enrolled' => 4, 'exam_taken' => 3, 'with_result' => 2, 'approved' => 1]);
});

it('disambiguates contests that share acronym and year', function () {
    $a = Contest::factory()->create(['organ_acronym' => 'TRANSPETRO', 'year' => 2026, 'title' => 'Transpetro 2026 — Análise de Sistemas, Ênfase Infraestrutura']);
    $b = Contest::factory()->create(['organ_acronym' => 'TRANSPETRO', 'year' => 2026, 'title' => 'Transpetro 2026 — Análise de Sistemas, Ênfase 5: Processos']);
    ExamResult::factory()->create(['contest_id' => $a->id]);
    ExamResult::factory()->create(['contest_id' => $b->id]);

    $labels = collect(summary()['timeline'])->pluck('label', 'contest_id');

    expect($labels[$a->id])->toBe('TRANSPETRO 2026 · Ênfase Infraestrutura');
    expect($labels[$b->id])->toBe('TRANSPETRO 2026 · Ênfase 5: Processos');
});
