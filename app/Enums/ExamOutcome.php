<?php

namespace App\Enums;

/**
 * Como terminou a prova pra mim (docs/01-modelo-de-dados.md → `exam_results`).
 */
enum ExamOutcome: string
{
    case Pending = 'pending';
    case Passed = 'passed';
    case Reserve = 'reserve';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'aguardando',
            self::Passed => 'aprovado',
            self::Reserve => 'cadastro reserva',
            self::Failed => 'eliminado',
        };
    }

    /** Aprovado nas vagas ou no cadastro reserva. */
    public function isApproved(): bool
    {
        return $this === self::Passed || $this === self::Reserve;
    }
}
