<?php

namespace App\Enums;

enum Region: string
{
    case North = 'north';
    case Northeast = 'northeast';
    case Midwest = 'midwest';
    case Southeast = 'southeast';
    case South = 'south';

    public function label(): string
    {
        return match ($this) {
            self::North => 'Norte',
            self::Northeast => 'Nordeste',
            self::Midwest => 'Centro-Oeste',
            self::Southeast => 'Sudeste',
            self::South => 'Sul',
        };
    }

    /**
     * @return array<int, BrazilianState>
     */
    public function states(): array
    {
        return array_values(array_filter(
            BrazilianState::cases(),
            fn (BrazilianState $s) => $s->region() === $this,
        ));
    }
}
