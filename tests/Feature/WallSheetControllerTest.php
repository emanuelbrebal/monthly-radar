<?php

use App\Enums\EditalStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EventType;
use App\Http\Controllers\Radar\WallSheetController;
use App\Models\Contest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['radar.llm.api_key' => null]);
    Carbon::setTestNow(Carbon::parse('2026-11-24 12:00', 'America/Maceio'));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('redirects guests to login', function () {
    $this->get('/wall-sheet')->assertRedirect(route('login'));
});

it('lists only tracked contests: pinned, interested or enrolled — never dismissed', function () {
    $this->actingAs(User::factory()->create());

    $pinned = Contest::factory()->pinned(0)->create(['exam_date' => '2026-12-13']);
    $enrolled = Contest::factory()->enrolled()->create(['exam_date' => '2027-01-24']);
    $interested = Contest::factory()->create(['enrollment_status' => 'interested', 'exam_date' => null]);
    Contest::factory()->create(); // só na mesa
    Contest::factory()->dismissed()->create();

    $this->get('/wall-sheet')->assertInertia(fn (Assert $page) => $page
        ->component('radar/wall-sheet')
        ->where('generatedAt', '2026-11-24')
        ->has('contests', 3)
        // Ordem: prova mais próxima primeiro, sem data por último.
        ->where('contests.0.id', $pinned->id)
        ->where('contests.1.id', $enrolled->id)
        ->where('contests.2.id', $interested->id)
    );
});

it('builds an ascending timeline from today on, with days left', function () {
    $this->actingAs(User::factory()->create());

    $a = Contest::factory()->enrolled()->create(['organ_acronym' => 'TJPB', 'year' => 2026]);
    $a->events()->createMany([
        ['type' => EventType::Exam->value, 'date' => '2027-01-24', 'source' => 'seed'],
        ['type' => EventType::RegistrationEnd->value, 'date' => '2026-11-01', 'source' => 'seed'], // passado
    ]);

    $b = Contest::factory()->pinned(1)->create(['organ_acronym' => 'CGU', 'year' => 2026]);
    $b->events()->createMany([
        ['type' => EventType::Exam->value, 'date' => '2026-12-13', 'source' => 'seed'],
        ['type' => EventType::Result->value, 'date' => '2026-11-24', 'label' => 'gabarito', 'source' => 'seed'],
    ]);

    // Não acompanhado: não entra na linha do tempo.
    Contest::factory()->create()->events()->create(['type' => EventType::Exam->value, 'date' => '2026-12-01', 'source' => 'seed']);

    $this->get('/wall-sheet')->assertInertia(fn (Assert $page) => $page
        ->has('timeline', 3)
        ->where('timeline.0.date', '2026-11-24')
        ->where('timeline.0.days_left', 0)
        ->where('timeline.0.event', 'gabarito')
        ->where('timeline.1.date', '2026-12-13')
        ->where('timeline.1.days_left', 19)
        ->where('timeline.1.contest', 'CGU 2026')
        ->where('timeline.2.date', '2027-01-24')
        ->where('timeline.2.event', 'prova')
    );
});

it('flags tracked contests with no upcoming date', function () {
    $this->actingAs(User::factory()->create());

    $undated = Contest::factory()->create(['enrollment_status' => 'interested']);
    $undated->events()->create(['type' => EventType::Exam->value, 'date' => '2026-10-01', 'source' => 'seed']);

    $this->get('/wall-sheet')->assertInertia(fn (Assert $page) => $page
        ->where('contests.0.has_upcoming', false)
        ->whereType('contests.0.phase_done', 'integer')
        ->missing('contests.0.phase_label')
    );
});

it('renders a QR code for the edital link, falling back to the registration link', function () {
    $this->actingAs(User::factory()->create());

    Contest::factory()->pinned(0)->create(['exam_date' => '2026-12-13', 'edital_url' => 'https://example.gov.br/edital.pdf']);
    Contest::factory()->pinned(1)->create(['exam_date' => '2027-01-24', 'registration_url' => 'https://banca.example/inscricao']);
    Contest::factory()->pinned(2)->create(['exam_date' => '2027-04-18']);

    $this->get('/wall-sheet')->assertInertia(fn (Assert $page) => $page
        ->where('contests.0.qr_target', 'edital')
        ->where('contests.0.qr_svg', fn ($svg) => str_starts_with($svg, '<svg'))
        ->where('contests.1.qr_target', 'inscrição')
        ->where('contests.2.qr_svg', null)
        ->where('contests.2.qr_target', null)
    );
});

it('orders phases by upcoming exam, then by next upcoming event, then contests with nothing ahead', function () {
    $this->actingAs(User::factory()->create());

    $nothing = Contest::factory()->pinned(0)->create(['organ' => 'A', 'exam_date' => null]);
    $pastExam = Contest::factory()->pinned(1)->create(['organ' => 'B', 'exam_date' => '2026-10-11']);
    $pastExam->events()->create(['type' => EventType::Result->value, 'date' => '2026-12-06', 'source' => 'seed']);
    $farExam = Contest::factory()->pinned(2)->create(['organ' => 'C', 'exam_date' => '2027-01-24']);
    $nearExam = Contest::factory()->pinned(3)->create(['organ' => 'D', 'exam_date' => '2026-12-13']);

    $this->get('/wall-sheet')->assertInertia(fn (Assert $page) => $page
        ->where('contests.0.id', $nearExam->id)
        ->where('contests.1.id', $farExam->id)
        ->where('contests.2.id', $pastExam->id)
        ->where('contests.3.id', $nothing->id)
    );
});

it('counts the phase columns the app already knows are done', function (string $edital, string $enrollment, int $expected) {
    expect(WallSheetController::phaseDone(EditalStatus::from($edital), EnrollmentStatus::from($enrollment)))
        ->toBe($expected);
})->with([
    'previsto, só interesse' => ['expected', 'interested', 0],
    'autorizado' => ['authorized', 'interested', 1],
    'edital em produção' => ['in_preparation', 'not_enrolled', 1],
    'edital publicado' => ['published', 'interested', 2],
    'inscrições abertas, inscrito' => ['registration_open', 'enrolled', 3],
    'taxa paga' => ['registration_closed', 'fee_paid', 4],
    'local de prova saiu' => ['registration_closed', 'venue_released', 5],
    'prova feita' => ['exam_done', 'exam_taken', 6],
    'prova aplicada sem eu fazer' => ['exam_done', 'interested', 2],
    'suspenso, só interesse' => ['suspended', 'interested', 0],
    'suspenso, taxa paga' => ['suspended', 'fee_paid', 4],
]);

it('falls back to a saved source for the QR when there is no edital or registration link', function () {
    $this->actingAs(User::factory()->create());

    Contest::factory()->pinned(0)->create([
        'exam_date' => '2026-12-13',
        'field_sources' => [
            'status' => ['url' => 'https://folha.qconcursos.com/n/concurso-x', 'origin' => 'seed', 'fetched_at' => '2026-11-20T10:00:00+00:00'],
            'organ' => ['url' => 'https://www.gov.br/orgao/concursos', 'origin' => 'seed', 'fetched_at' => '2026-10-01T10:00:00+00:00'],
            'year' => ['origin' => 'llm'],
        ],
    ]);
    Contest::factory()->pinned(1)->create([
        'exam_date' => '2027-01-24',
        'field_sources' => [
            'status' => ['url' => 'https://blog.example.com/antiga', 'origin' => 'seed', 'fetched_at' => '2026-09-01T10:00:00+00:00'],
            'organ' => ['url' => 'https://blog.example.com/nova', 'origin' => 'seed', 'fetched_at' => '2026-11-01T10:00:00+00:00'],
        ],
    ]);

    $this->get('/wall-sheet')->assertInertia(fn (Assert $page) => $page
        // Oficial vence agregador mesmo sendo mais antiga.
        ->where('contests.0.qr_target', 'fonte')
        ->where('contests.0.qr_host', 'gov.br')
        ->where('contests.0.qr_svg', fn ($svg) => str_starts_with($svg, '<svg'))
        // Entre não oficiais, a mais recente.
        ->where('contests.1.qr_target', 'fonte')
        ->where('contests.1.qr_host', 'blog.example.com')
    );

    expect(WallSheetController::qrLink(Contest::find(Contest::max('id')))[0])->toBe('https://blog.example.com/nova');
});
