<?php

use App\Enums\EditalStatus;
use App\Services\Radar\ContestStatusResolver;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->resolver = new ContestStatusResolver;
    $this->today = CarbonImmutable::parse('2026-09-16');
});

it('resolves the effective status from raw status and dates', function (
    EditalStatus $status,
    ?string $registrationStart,
    ?string $registrationEnd,
    ?string $examDate,
    EditalStatus $expected,
) {
    $resolved = $this->resolver->resolve(
        $status,
        $registrationStart !== null ? CarbonImmutable::parse($registrationStart) : null,
        $registrationEnd !== null ? CarbonImmutable::parse($registrationEnd) : null,
        $examDate !== null ? CarbonImmutable::parse($examDate) : null,
        $this->today,
    );

    expect($resolved)->toBe($expected);
})->with([
    'authorized sem datas fica como está' => [EditalStatus::Authorized, null, null, null, EditalStatus::Authorized],
    'published com inscrição futura fica como está' => [EditalStatus::Published, '2026-09-20', '2026-10-01', null, EditalStatus::Published],
    'published com inscrição em andamento abre' => [EditalStatus::Published, '2026-09-01', '2026-09-30', null, EditalStatus::RegistrationOpen],
    'registration_open com inscrição encerrada fecha' => [EditalStatus::RegistrationOpen, '2026-08-01', '2026-09-01', null, EditalStatus::RegistrationClosed],
    'registration_closed com prova passada faz exame' => [EditalStatus::RegistrationClosed, '2026-08-01', '2026-09-01', '2026-09-10', EditalStatus::ExamDone],
    'exam_done não regride' => [EditalStatus::ExamDone, '2026-08-01', '2026-09-01', '2026-09-10', EditalStatus::ExamDone],
    'finished mantém-se mesmo com datas futuras' => [EditalStatus::Finished, '2026-09-20', '2026-10-01', '2026-11-01', EditalStatus::Finished],
    'suspended mantém-se mesmo com inscrição em andamento' => [EditalStatus::Suspended, '2026-09-01', '2026-09-30', null, EditalStatus::Suspended],
    'registration_open retificado com inscrição adiada volta a published' => [EditalStatus::RegistrationOpen, '2026-09-20', '2026-10-01', null, EditalStatus::Published],
    'sem nenhuma data fica como está' => [EditalStatus::InPreparation, null, null, null, EditalStatus::InPreparation],
]);
