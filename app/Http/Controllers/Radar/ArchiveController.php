<?php

namespace App\Http\Controllers\Radar;

use App\Enums\EnrollmentStatus;
use App\Models\Contest;
use App\Support\Radar\ContestPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `GET /archive` — tudo que saiu da mesa (docs/03-ui-inventario.md →
 * "Arquivo"): complemento exato de `Contest::visible()`. Só leitura; mudar a
 * situação ou trazer de volta reusa os endpoints de enrollment/restore.
 */
class ArchiveController
{
    private const REASONS = ['expired', 'dismissed'];

    public function index(Request $request): Response
    {
        $reason = in_array($request->query('reason'), self::REASONS, true)
            ? $request->query('reason')
            : null;

        $contests = Contest::query()
            ->archived()
            ->with(['events', 'results'])
            ->get();

        $counts = [
            'all' => $contests->count(),
            'expired' => $contests->filter(fn (Contest $c) => ! $c->isDismissed())->count(),
            'dismissed' => $contests->filter(fn (Contest $c) => $c->isDismissed())->count(),
        ];

        $listed = $contests
            ->filter(fn (Contest $c) => $reason === null || $c->archiveReason() === $reason)
            ->sortByDesc(fn (Contest $c) => $this->leftAt($c))
            ->values();

        return Inertia::render('radar/archive', [
            'contests' => $listed->map(fn (Contest $c) => [
                ...ContestPresenter::present($c),
                'archive_reason' => $c->archiveReason(),
                'left_at' => $this->leftAt($c),
            ])->all(),
            'reason' => $reason,
            'counts' => $counts,
        ]);
    }

    /**
     * Quando saiu da mesa: data do descarte ou do encerramento efetivo.
     */
    private function leftAt(Contest $contest): ?string
    {
        if ($contest->enrollment_status === EnrollmentStatus::Dismissed && $contest->dismissed_at !== null) {
            return $contest->dismissed_at->toIso8601String();
        }

        return $contest->effectiveClosedAt()?->toIso8601String();
    }
}
