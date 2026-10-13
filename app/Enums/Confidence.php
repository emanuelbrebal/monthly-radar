<?php

namespace App\Enums;

enum Confidence: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    /** Curadoria manual sem fonte localizada — nunca estimar o dado, só marcar assim. */
    case Unconfirmed = 'unconfirmed';
}
