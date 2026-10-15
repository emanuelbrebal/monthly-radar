<?php

use App\Enums\ContestPhase;
use App\Enums\EditalStatus;
use App\Enums\EnrollmentStatus;
use App\Services\Radar\ContestPhaseResolver;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->resolver = new ContestPhaseResolver;
    $this->today = CarbonImmutable::parse('2026-10-15');
});

it('derives the current phase', function (
    EditalStatus $effective,
    EnrollmentStatus $enrollment,
    ContestPhase $expected,
) {
    $phase = $this->resolver->resolve($effective, $enrollment, $this->today);

    expect($phase)->toBe($expected);
})->with([
    'suspenso vence inscrito' => [EditalStatus::Suspended, EnrollmentStatus::Enrolled, ContestPhase::Suspended],
    'encerrado vence prova feita' => [EditalStatus::Finished, EnrollmentStatus::ExamTaken, ContestPhase::Finished],
    'prova feita aguarda resultado' => [EditalStatus::ExamDone, EnrollmentStatus::ExamTaken, ContestPhase::AwaitingResult],
    'local liberado' => [EditalStatus::RegistrationClosed, EnrollmentStatus::VenueReleased, ContestPhase::VenueReleased],
    'taxa paga' => [EditalStatus::RegistrationOpen, EnrollmentStatus::FeePaid, ContestPhase::FeePaid],
    'inscrito' => [EditalStatus::RegistrationOpen, EnrollmentStatus::Enrolled, ContestPhase::Enrolled],
    'prova aplicada sem inscrição' => [EditalStatus::ExamDone, EnrollmentStatus::NotEnrolled, ContestPhase::ExamDone],
    'inscrições encerradas' => [EditalStatus::RegistrationClosed, EnrollmentStatus::NotEnrolled, ContestPhase::RegistrationClosed],
    'inscrições abertas' => [EditalStatus::RegistrationOpen, EnrollmentStatus::NotEnrolled, ContestPhase::RegistrationOpen],
    'interessado segue o edital' => [EditalStatus::RegistrationOpen, EnrollmentStatus::Interested, ContestPhase::RegistrationOpen],
    'descartado segue o edital' => [EditalStatus::Published, EnrollmentStatus::Dismissed, ContestPhase::EditalPublished],
    'edital publicado' => [EditalStatus::Published, EnrollmentStatus::NotEnrolled, ContestPhase::EditalPublished],
    'edital em formulação' => [EditalStatus::InPreparation, EnrollmentStatus::NotEnrolled, ContestPhase::EditalInPreparation],
    'autorizado' => [EditalStatus::Authorized, EnrollmentStatus::NotEnrolled, ContestPhase::Authorized],
    'previsto' => [EditalStatus::Expected, EnrollmentStatus::NotEnrolled, ContestPhase::Expected],
]);

it('flags a contest as new only inside the window and before any action', function (
    string $createdAt,
    EnrollmentStatus $enrollment,
    ?int $pinnedSlot,
    bool $dismissed,
    bool $expected,
) {
    $result = $this->resolver->isNew(CarbonImmutable::parse($createdAt), $enrollment, $pinnedSlot, $dismissed, $this->today);

    expect($result)->toBe($expected);
})->with([
    'criado há 1 dia' => ['2026-10-14', EnrollmentStatus::NotEnrolled, null, false, true],
    'criado há exatamente 7 dias' => ['2026-10-08 00:00:00', EnrollmentStatus::NotEnrolled, null, false, true],
    'criado há 8 dias' => ['2026-10-07', EnrollmentStatus::NotEnrolled, null, false, false],
    'interessado deixa de ser novo' => ['2026-10-14', EnrollmentStatus::Interested, null, false, false],
    'inscrito deixa de ser novo' => ['2026-10-14', EnrollmentStatus::Enrolled, null, false, false],
    'fixado deixa de ser novo' => ['2026-10-14', EnrollmentStatus::NotEnrolled, 3, false, false],
    'descartado deixa de ser novo' => ['2026-10-14', EnrollmentStatus::NotEnrolled, null, true, false],
]);

it('is not new without a creation date', function () {
    expect($this->resolver->isNew(null, EnrollmentStatus::NotEnrolled, null, false, $this->today))->toBeFalse();
});
