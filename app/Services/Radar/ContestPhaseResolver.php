<?php

namespace App\Services\Radar;

use App\Enums\ContestPhase;
use App\Enums\EditalStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EventType;
use App\Models\ContestEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Fase atual e flag "novo". PHP puro, sem I/O — a situação pessoal
 * (EnrollmentStatus) vence o estado do edital quando o usuário já agiu.
 */
class ContestPhaseResolver
{
    /**
     * @param  Collection<int, ContestEvent>  $events
     */
    public function resolve(
        EditalStatus $effective,
        EnrollmentStatus $enrollment,
        Collection $events,
        ?CarbonInterface $now = null,
    ): ContestPhase {
        if ($effective === EditalStatus::Suspended) {
            return ContestPhase::Suspended;
        }

        if ($effective === EditalStatus::Finished) {
            return ContestPhase::Finished;
        }

        switch ($enrollment) {
            case EnrollmentStatus::ExamTaken:
                return $this->hasPublishedResult($events, $now) ? ContestPhase::ResultOut : ContestPhase::AwaitingResult;
            case EnrollmentStatus::VenueReleased:
                return ContestPhase::VenueReleased;
            case EnrollmentStatus::FeePaid:
                return ContestPhase::FeePaid;
            case EnrollmentStatus::Enrolled:
                return ContestPhase::Enrolled;
            default:
                // not_enrolled, interested e dismissed seguem o edital.
        }

        return match ($effective) {
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

    /**
     * Resultado "saiu" = há evento `result` já passado e nenhum futuro.
     * Sem nenhum evento `result`, assume-se que ainda se espera.
     *
     * @param  Collection<int, ContestEvent>  $events
     */
    private function hasPublishedResult(Collection $events, ?CarbonInterface $now): bool
    {
        $today = ($now ?? Carbon::now())->toDateString();
        $results = $events->filter(fn (ContestEvent $e) => $e->type === EventType::Result);

        if ($results->isEmpty()) {
            return false;
        }

        return $results->every(fn (ContestEvent $e) => $e->date->toDateString() < $today);
    }
}
