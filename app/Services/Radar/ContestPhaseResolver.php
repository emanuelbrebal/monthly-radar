<?php

namespace App\Services\Radar;

use App\Enums\ContestPhase;
use App\Enums\EditalStatus;
use App\Enums\EnrollmentStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Fase atual e flag "novo". PHP puro, sem I/O — a situação pessoal
 * (EnrollmentStatus) vence o estado do edital quando o usuário já agiu.
 */
class ContestPhaseResolver
{
    public function resolve(EditalStatus $effective, EnrollmentStatus $enrollment): ContestPhase
    {
        if ($effective === EditalStatus::Suspended) {
            return ContestPhase::Suspended;
        }

        if ($effective === EditalStatus::Finished) {
            return ContestPhase::Finished;
        }

        // not_enrolled, interested e dismissed seguem o edital.
        $personal = match ($enrollment) {
            EnrollmentStatus::ExamTaken => ContestPhase::AwaitingResult,
            EnrollmentStatus::VenueReleased => ContestPhase::VenueReleased,
            EnrollmentStatus::FeePaid => ContestPhase::FeePaid,
            EnrollmentStatus::Enrolled => ContestPhase::Enrolled,
            default => null,
        };

        return $personal ?? match ($effective) {
            EditalStatus::ExamDone => ContestPhase::ExamDone,
            EditalStatus::RegistrationClosed => ContestPhase::RegistrationClosed,
            EditalStatus::RegistrationOpen => ContestPhase::RegistrationOpen,
            EditalStatus::Published => ContestPhase::EditalPublished,
            EditalStatus::InPreparation => ContestPhase::EditalInPreparation,
            EditalStatus::Authorized => ContestPhase::Authorized,
            default => ContestPhase::Expected,
        };
    }

    /**
     * "Novo": entrou no radar dentro da janela e ainda não houve nenhuma ação
     * (não inscrito/interessado, não fixado, não descartado).
     */
    public function isNew(
        ?CarbonInterface $createdAt,
        EnrollmentStatus $enrollment,
        ?int $pinnedSlot,
        bool $dismissed,
        ?CarbonInterface $now = null,
    ): bool {
        if ($createdAt === null || $dismissed || $pinnedSlot !== null || $enrollment !== EnrollmentStatus::NotEnrolled) {
            return false;
        }

        $now ??= Carbon::now();
        $days = (int) config('radar.new_window_days', 7);

        return $createdAt->greaterThanOrEqualTo($now->copy()->subDays($days));
    }
}
