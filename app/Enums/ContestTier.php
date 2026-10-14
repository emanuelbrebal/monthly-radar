<?php

namespace App\Enums;

/**
 * Nível de dificuldade/concorrência do concurso — julgamento pessoal do dono
 * do radar a partir de salário, tipo de órgão, banca e número de vagas, não um
 * dado raspado (ver docs/01-modelo-de-dados.md → "Campos de curadoria manual").
 * Sem regra de negócio: serve para a maleta mostrar o equilíbrio da escada.
 */
enum ContestTier: string
{
    case Elite = 'elite';
    case High = 'alto';
    case Intermediate = 'intermediario';
    case Entry = 'entrada';

    public function label(): string
    {
        return match ($this) {
            self::Elite => 'elite',
            self::High => 'alto',
            self::Intermediate => 'intermediário',
            self::Entry => 'entrada',
        };
    }

    /**
     * Do topo da escada para a base — ordem de exibição dos grupos na maleta.
     */
    public function order(): int
    {
        return match ($this) {
            self::Elite => 0,
            self::High => 1,
            self::Intermediate => 2,
            self::Entry => 3,
        };
    }
}
