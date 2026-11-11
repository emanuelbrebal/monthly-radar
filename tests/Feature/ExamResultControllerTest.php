<?php

use App\Models\Contest;
use App\Models\ExamResult;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->contest = Contest::factory()->create(['enrollment_status' => 'exam_taken', 'exam_date' => '2026-10-11']);
});

function validResult(array $overrides = []): array
{
    return [
        'score' => 62,
        'max_score' => 70,
        'cutoff_score' => 57.5,
        'outcome' => 'pending',
        'source' => 'self_graded',
        ...$overrides,
    ];
}

it('saves the result and returns it in the contest payload', function () {
    $this->putJson("/contests/{$this->contest->id}/result", validResult())
        ->assertOk()
        ->assertJsonPath('contest.result.percent', 88.6)
        ->assertJsonPath('contest.result.margin', 4.5)
        ->assertJsonPath('contest.result.source_label', 'gabarito')
        ->assertJsonPath('contest.result.taken_on', '2026-10-11');

    expect(ExamResult::query()->where('contest_id', $this->contest->id)->count())->toBe(1);
});

it('updates the same row instead of creating another', function () {
    $this->putJson("/contests/{$this->contest->id}/result", validResult())->assertOk();
    $this->putJson("/contests/{$this->contest->id}/result", validResult(['score' => 64, 'source' => 'official', 'outcome' => 'reserve']))
        ->assertOk()
        ->assertJsonPath('contest.result.score', 64)
        ->assertJsonPath('contest.result.outcome_label', 'cadastro reserva');

    expect(ExamResult::query()->count())->toBe(1);
});

it('stores an optional per-subject breakdown that does not need to add up', function () {
    $this->putJson("/contests/{$this->contest->id}/result", validResult([
        'breakdown' => [
            ['subject' => ' Português ', 'score' => 8, 'max' => 10],
            ['subject' => 'Desenvolvimento', 'score' => 30, 'max' => 40],
        ],
    ]))
        ->assertOk()
        ->assertJsonPath('contest.result.breakdown.0.subject', 'Português')
        ->assertJsonCount(2, 'contest.result.breakdown');
});

it('rejects a result when the exam was not taken', function () {
    $contest = Contest::factory()->enrolled()->create();

    $this->putJson("/contests/{$contest->id}/result", validResult())->assertStatus(422);
    expect(ExamResult::query()->count())->toBe(0);
});

it('validates score bounds, position and breakdown rows', function (array $payload, string $field) {
    $this->putJson("/contests/{$this->contest->id}/result", validResult($payload))
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);
})->with([
    'score above max' => [['score' => 71], 'score'],
    'zero max' => [['max_score' => 0], 'max_score'],
    'cutoff above max' => [['cutoff_score' => 80], 'cutoff_score'],
    'position above candidates' => [['position' => 300, 'candidates' => 200], 'position'],
    'bad outcome' => [['outcome' => 'maybe'], 'outcome'],
    'breakdown above max' => [['breakdown' => [['subject' => 'TI', 'score' => 11, 'max' => 10]]], 'breakdown.0.score'],
]);

it('deletes the result', function () {
    ExamResult::factory()->create(['contest_id' => $this->contest->id]);

    $this->deleteJson("/contests/{$this->contest->id}/result")
        ->assertOk()
        ->assertJsonPath('contest.result', null);

    expect(ExamResult::query()->count())->toBe(0);
});

it('lists subjects already used, unique and sorted', function () {
    ExamResult::factory()->create(['breakdown' => [['subject' => 'Português', 'score' => 8, 'max' => 10], ['subject' => 'Banco de dados', 'score' => 5, 'max' => 10]]]);
    ExamResult::factory()->create(['breakdown' => [['subject' => 'Português', 'score' => 7, 'max' => 10]]]);
    ExamResult::factory()->create(['breakdown' => null]);

    $this->getJson('/results/subjects')
        ->assertOk()
        ->assertExactJson(['subjects' => ['Banco de dados', 'Português']]);
});
