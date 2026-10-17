<?php

use App\Enums\ChangeKind;
use App\Models\Contest;
use App\Models\ContestChange;
use App\Models\SearchRun;
use App\Services\Radar\ContestMerger;

beforeEach(function () {
    $this->merger = app(ContestMerger::class);
});

it('logs one row per changed field with old and new values', function () {
    $run = SearchRun::factory()->create();
    $contest = Contest::factory()->create(['board' => null, 'it_vacancies' => 5]);

    $this->merger->merge($contest, ['board' => 'FGV', 'it_vacancies' => 8], origin: 'scrape', sourceUrl: 'https://x.com/a', confidence: 'medium', searchRunId: $run->id);

    $changes = ContestChange::query()->where('contest_id', $contest->id)->get()->keyBy('field');

    expect($changes->keys()->sort()->values()->all())->toBe(['board', 'it_vacancies']);
    expect($changes['board']->kind)->toBe(ChangeKind::Updated);
    expect($changes['board']->old_value)->toBeNull();
    expect($changes['board']->new_value)->toBe('FGV');
    expect($changes['it_vacancies']->old_value)->toBe(5);
    expect($changes['it_vacancies']->new_value)->toBe(8);
    expect($changes['board']->search_run_id)->toBe($run->id);
    expect($changes['board']->origin)->toBe('scrape');
});

it('logs nothing when the merge changes no tracked field', function () {
    $contest = Contest::factory()->create(['board' => 'FGV']);

    $this->merger->merge($contest, ['board' => 'FGV'], origin: 'scrape', sourceUrl: null, confidence: 'medium');

    expect(ContestChange::query()->where('contest_id', $contest->id)->count())->toBe(0);
});

it('does not log a lower priority write that was rejected', function () {
    $contest = Contest::factory()->create(['board' => null]);
    $this->merger->merge($contest, ['board' => 'Cesgranrio'], origin: 'scrape', sourceUrl: 'https://orgao.gov.br/edital', confidence: 'high');

    $this->merger->merge($contest, ['board' => 'Blog'], origin: 'llm', sourceUrl: null, confidence: 'low');

    expect(ContestChange::query()->where('field', 'board')->count())->toBe(1);
});

it('logs a single created row for a new contest', function () {
    $contest = new Contest(Contest::factory()->make()->getAttributes());

    $this->merger->apply($contest, origin: 'seed');

    $rows = ContestChange::query()->where('contest_id', $contest->id)->get();

    expect($rows)->toHaveCount(1);
    expect($rows->first()->kind)->toBe(ChangeKind::Created);
    expect($rows->first()->field)->toBeNull();
});

it('logs dates as plain strings', function () {
    $contest = Contest::factory()->create(['exam_date' => null]);

    $this->merger->merge($contest, ['exam_date' => '2027-01-17'], origin: 'scrape', sourceUrl: null, confidence: 'medium');

    $row = ContestChange::query()->where('field', 'exam_date')->firstOrFail();

    expect($row->new_value)->toBe('2027-01-17');
});
