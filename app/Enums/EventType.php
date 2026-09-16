<?php

namespace App\Enums;

enum EventType: string
{
    case RegistrationStart = 'registration_start';
    case RegistrationEnd = 'registration_end';
    case FeeDeadline = 'fee_deadline';
    case Exam = 'exam';
    case Result = 'result';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::RegistrationStart => 'abertura de inscrição',
            self::RegistrationEnd => 'fim de inscrição',
            self::FeeDeadline => 'prazo da taxa',
            self::Exam => 'prova',
            self::Result => 'resultado',
            self::Other => 'outro',
        };
    }
}
