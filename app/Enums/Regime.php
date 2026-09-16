<?php

namespace App\Enums;

enum Regime: string
{
    case Clt = 'clt';
    case Statutory = 'statutory';
    case Temporary = 'temporary';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Clt => 'CLT',
            self::Statutory => 'estatutário',
            self::Temporary => 'temporário',
            self::Unknown => 'desconhecido',
        };
    }
}
