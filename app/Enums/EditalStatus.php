<?php

namespace App\Enums;

/**
 * Status do edital, na ordem de progressão descrita em docs/00-spec.md.
 * A ordem dos cases importa: é usada por ContestStatusResolver para decidir
 * "no mínimo X" (maior status entre o armazenado e o sugerido).
 */
enum EditalStatus: string
{
    case Expected = 'expected';
    case Authorized = 'authorized';
    case InPreparation = 'in_preparation';
    case Published = 'published';
    case RegistrationOpen = 'registration_open';
    case RegistrationClosed = 'registration_closed';
    case ExamDone = 'exam_done';
    case Finished = 'finished';
    case Suspended = 'suspended';

    /**
     * "Fechado ou posterior" — usado pela regra das 24h e pelo status efetivo.
     * Suspended é um ramo à parte (mantém-se, não é "closed or later").
     */
    public function isClosedOrLater(): bool
    {
        return in_array($this, [self::RegistrationClosed, self::ExamDone, self::Finished], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Expected => 'previsto',
            self::Authorized => 'autorizado',
            self::InPreparation => 'edital em produção',
            self::Published => 'edital publicado',
            self::RegistrationOpen => 'inscrições abertas',
            self::RegistrationClosed => 'inscrições encerradas',
            self::ExamDone => 'prova aplicada',
            self::Finished => 'encerrado',
            self::Suspended => 'suspenso',
        };
    }

    /**
     * Posição na ordem de progressão (índice do case), usada para
     * calcular "no mínimo X" no ContestStatusResolver.
     */
    public function order(): int
    {
        return match ($this) {
            self::Expected => 0,
            self::Authorized => 1,
            self::InPreparation => 2,
            self::Published => 3,
            self::RegistrationOpen => 4,
            self::RegistrationClosed => 5,
            self::ExamDone => 6,
            self::Finished => 7,
            self::Suspended => 8,
        };
    }
}
