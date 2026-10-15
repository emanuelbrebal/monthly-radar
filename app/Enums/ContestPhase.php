<?php

namespace App\Enums;

/**
 * Fase atual de um concurso, derivada (sem coluna) de EditalStatus efetivo,
 * EnrollmentStatus e eventos por ContestPhaseResolver. É só apresentação:
 * nenhuma regra do sistema (24h, pin, visibilidade) depende dela.
 */
enum ContestPhase: string
{
    case Expected = 'expected';
    case Authorized = 'authorized';
    case EditalInPreparation = 'edital_in_preparation';
    case EditalPublished = 'edital_published';
    case RegistrationOpen = 'registration_open';
    case RegistrationClosed = 'registration_closed';
    case Enrolled = 'enrolled';
    case FeePaid = 'fee_paid';
    case VenueReleased = 'venue_released';
    case ExamDone = 'exam_done';
    case AwaitingResult = 'awaiting_result';
    case Finished = 'finished';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Expected => 'previsto',
            self::Authorized => 'autorizado',
            self::EditalInPreparation => 'edital em formulação',
            self::EditalPublished => 'edital publicado',
            self::RegistrationOpen => 'inscrições abertas',
            self::RegistrationClosed => 'inscrições encerradas',
            self::Enrolled => 'inscrito',
            self::FeePaid => 'taxa paga',
            self::VenueReleased => 'local de prova saiu',
            self::ExamDone => 'prova aplicada',
            self::AwaitingResult => 'aguardando resultado',
            self::Finished => 'encerrado',
            self::Suspended => 'suspenso',
        };
    }

    /**
     * Tom do badge (mesmo vocabulário de BadgeKind no front): pos | neu | neg | info.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Enrolled, self::FeePaid, self::VenueReleased, self::RegistrationOpen => 'pos',
            self::EditalInPreparation, self::EditalPublished, self::ExamDone => 'neu',
            self::Expected, self::Authorized, self::AwaitingResult => 'info',
            self::RegistrationClosed, self::Finished, self::Suspended => 'neg',
        };
    }
}
