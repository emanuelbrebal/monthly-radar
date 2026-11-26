<?php

use App\Enums\EventType;
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
        ->has('contests.0.phase_label')
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
