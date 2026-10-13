<?php

namespace App\Enums;

/**
 * Esfera do órgão — atributo descritivo de curadoria manual, sem regra de
 * negócio própria ainda (ver docs/01-modelo-de-dados.md).
 */
enum Sphere: string
{
    case Federal = 'federal';
    case State = 'estadual';
    case District = 'distrital';
    case Municipal = 'municipal';

    public function label(): string
    {
        return match ($this) {
            self::Federal => 'federal',
            self::State => 'estadual',
            self::District => 'distrital',
            self::Municipal => 'municipal',
        };
    }
}
