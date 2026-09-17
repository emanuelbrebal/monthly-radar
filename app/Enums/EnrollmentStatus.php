<?php

namespace App\Enums;

/**
 * Checklist sequencial da minha situação no concurso. `Dismissed` é um valor
 * do mesmo enum mas fica fora da sequência (ver docs/00-spec.md).
 */
enum EnrollmentStatus: string
{
    case NotEnrolled = 'not_enrolled';
    case Interested = 'interested';
    case Enrolled = 'enrolled';
    case FeePaid = 'fee_paid';
    case VenueReleased = 'venue_released';
    case ExamTaken = 'exam_taken';
    case Dismissed = 'dismissed';

    /**
     * "Inscrito" para as regras do sistema (ex. regra das 24h) = enrolled
     * ou qualquer passo depois dele.
     */
    public function countsAsEnrolled(): bool
    {
        return in_array($this, [
            self::Enrolled,
            self::FeePaid,
            self::VenueReleased,
            self::ExamTaken,
        ], true);
    }

    /**
     * @return array<int, string>
     */
    public static function enrolledValues(): array
    {
        return array_map(
            fn (self $s) => $s->value,
            array_filter(self::cases(), fn (self $s) => $s->countsAsEnrolled()),
        );
    }

    /**
     * Posição no checklist sequencial. Null para dismissed (fora da sequência).
     */
    public function order(): ?int
    {
        return match ($this) {
            self::NotEnrolled => 0,
            self::Interested => 1,
            self::Enrolled => 2,
            self::FeePaid => 3,
            self::VenueReleased => 4,
            self::ExamTaken => 5,
            self::Dismissed => null,
        };
    }

    /**
     * É um passo do checklist sequencial? False só para dismissed.
     */
    public function isStep(): bool
    {
        return $this !== self::Dismissed;
    }

    public function label(): string
    {
        return match ($this) {
            self::NotEnrolled => 'não inscrito',
            self::Interested => 'quero fazer',
            self::Enrolled => 'inscrito',
            self::FeePaid => 'taxa paga',
            self::VenueReleased => 'local de prova saiu',
            self::ExamTaken => 'prova feita',
            self::Dismissed => 'descartado',
        };
    }
}
