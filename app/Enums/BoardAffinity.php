<?php

namespace App\Enums;

/**
 * Quanto o estudo em andamento aproveita para a banca deste concurso —
 * julgamento pessoal do dono do radar, não um dado raspado (ver
 * docs/01-modelo-de-dados.md).
 */
enum BoardAffinity: string
{
    case High = 'alta';
    case Medium = 'media';
    case MediumLow = 'media_baixa';
    case Low = 'baixa';
}
