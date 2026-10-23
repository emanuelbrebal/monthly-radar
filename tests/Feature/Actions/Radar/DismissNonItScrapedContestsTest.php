<?php

use App\Actions\Radar\DismissNonItScrapedContests;
use App\Enums\EnrollmentStatus;
use App\Models\Contest;
use App\Models\ExamResult;
use App\Models\RawListing;

/**
 * @param  array<string, mixed>  $attributes
 * @param  array<int, string>  $origins
 */
function scrapedContest(string $rawTitle, array $attributes = [], array $origins = ['scrape']): Contest
{
    $sources = [];

    foreach ($origins as $i => $origin) {
        $sources["field_{$i}"] = ['url' => 'https://x.com', 'origin' => $origin, 'confidence' => 'medium'];
    }

    $contest = Contest::factory()->create([
        'enrollment_status' => EnrollmentStatus::NotEnrolled->value,
        'pinned_slot' => null,
        'field_sources' => $sources,
        ...$attributes,
    ]);

    RawListing::factory()->create([
        'source' => 'pci_concursos',
        'title' => $rawTitle,
        'payload' => ['text' => $rawTitle, 'links' => []],
        'contest_id' => $contest->id,
    ]);

    return $contest;
}

it('dismisses a scraped-only contest without an IT role', function () {
    $contest = scrapedContest('FUNCAMP - Fundação de Desenvolvimento da Unicamp');

    $dismissed = app(DismissNonItScrapedContests::class)->handle();

    expect($dismissed)->toHaveCount(1);
    $contest->refresh();
    expect($contest->isDismissed())->toBeTrue();
    expect($contest->dismissed_reason)->toBe(DismissNonItScrapedContests::REASON);
});

it('keeps scraped contests that do have an IT role, in the card or in the roles', function () {
    scrapedContest('HOB | Fonoaudiólogo e Analista de Sistemas');
    $fromRoles = scrapedContest('Câmara de Cristalina | Vários Cargos');
    $fromRoles->rawListings()->first()->update([
        'payload' => ['text' => 'Vários Cargos', 'links' => [], 'roles' => ['Analista de Tecnologia da Informação']],
    ]);

    expect(app(DismissNonItScrapedContests::class)->handle())->toBe([]);
});

it('spares anything the owner touched or curated', function () {
    scrapedContest('Prefeitura A - Auxiliar de Desenvolvimento Infantil', ['pinned_slot' => 0]);
    scrapedContest('Prefeitura B - Auxiliar de Desenvolvimento Infantil', ['enrollment_status' => EnrollmentStatus::Interested->value]);
    scrapedContest('SEMA - Desenvolvimento Econômico', [], ['seed', 'scrape']);
    $withResult = scrapedContest('Prefeitura C - Auxiliar de Desenvolvimento Infantil');
    ExamResult::factory()->create(['contest_id' => $withResult->id]);
    Contest::factory()->create(['field_sources' => [], 'enrollment_status' => EnrollmentStatus::NotEnrolled->value]);

    expect(app(DismissNonItScrapedContests::class)->handle())->toBe([]);
});

it('only lists candidates on a dry run and is idempotent', function () {
    $contest = scrapedContest('COMDERP - Companhia Municipal de Desenvolvimento');
    $action = app(DismissNonItScrapedContests::class);

    expect($action->handle(dryRun: true))->toHaveCount(1);
    expect($contest->refresh()->isDismissed())->toBeFalse();

    expect($action->handle())->toHaveCount(1);
    expect($action->handle())->toBe([]);
});

it('runs from the command', function () {
    scrapedContest('ABDI - Agência Brasileira de Desenvolvimento Industrial');

    $this->artisan('radar:prune-noise', ['--dry' => true])
        ->expectsOutputToContain('1 concurso(s) seriam descartado(s).')
        ->assertSuccessful();
});
