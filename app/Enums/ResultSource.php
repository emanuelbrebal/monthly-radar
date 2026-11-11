<?php

namespace App\Enums;

/**
 * De onde veio a nota: conta minha pelo gabarito, resultado preliminar ou
 * oficial (docs/01-modelo-de-dados.md → `exam_results`).
 */
enum ResultSource: string
{
    case SelfGraded = 'self_graded';
    case Preliminary = 'preliminary';
    case Official = 'official';

    public function label(): string
    {
        return match ($this) {
            self::SelfGraded => 'gabarito',
            self::Preliminary => 'preliminar',
            self::Official => 'oficial',
        };
    }
}
