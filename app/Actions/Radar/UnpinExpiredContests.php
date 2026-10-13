<?php

namespace App\Actions\Radar;

use App\Models\Contest;

/**
 * Desafixa (pinned_slot = null) qualquer concurso que tenha expirado
 * (isExpired()) mas ainda esteja na maleta. Roda no início de cada request
 * da tela principal e no fim de cada varredura — sem scheduler. Ver
 * docs/01-modelo-de-dados.md → "Desafixar expirados".
 */
class UnpinExpiredContests
{
    public function handle(): int
    {
        $unpinned = 0;

        Contest::query()
            ->whereNotNull('pinned_slot')
            ->where('enrollment_status', '!=', 'dismissed')
            ->each(function (Contest $contest) use (&$unpinned) {
                if ($contest->isExpired()) {
                    $contest->pinned_slot = null;
                    $contest->save();
                    $unpinned++;
                }
            });

        return $unpinned;
    }
}
