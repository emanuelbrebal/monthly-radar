<?php

namespace App\Actions\Radar;

use App\Enums\EnrollmentStatus;
use App\Models\Contest;
use App\Models\RawListing;
use App\Services\Radar\ContestNormalizer;
use Illuminate\Support\Carbon;

/**
 * Descarta concursos que entraram só por scraping e não passam no filtro de
 * TI atual — o ruído da 1ª varredura real (23/10/2026), quando o filtro era
 * por substring ("desenvolv" casava "Fundação de Desenvolvimento").
 *
 * Só toca quem nunca foi mexido: toda field_source com origin=scrape, fora
 * da maleta, situação "não inscrito", sem resultado de prova. Descartar (e
 * não deletar) é reversível pelo "restaurar" da tela de descartados.
 * Idempotente: descartado já não é candidato.
 */
class DismissNonItScrapedContests
{
    public const REASON = 'sem cargo de TI (filtro automático)';

    public function __construct(private ContestNormalizer $normalizer) {}

    /**
     * @return array<int, Contest> candidatos (descartados, se $dryRun = false)
     */
    public function handle(bool $dryRun = false): array
    {
        $dismissed = [];

        Contest::query()
            ->where('enrollment_status', EnrollmentStatus::NotEnrolled->value)
            ->whereNull('pinned_slot')
            ->whereDoesntHave('results')
            ->whereHas('rawListings')
            ->with('rawListings')
            ->each(function (Contest $contest) use ($dryRun, &$dismissed) {
                if (! $this->onlyScraped($contest) || $this->hasItRole($contest)) {
                    return;
                }

                if (! $dryRun) {
                    $contest->enrollment_status = EnrollmentStatus::Dismissed;
                    $contest->dismissed_at = Carbon::now();
                    $contest->dismissed_reason = self::REASON;
                    $contest->save();
                }

                $dismissed[] = $contest;
            });

        return $dismissed;
    }

    private function onlyScraped(Contest $contest): bool
    {
        $origins = array_column($contest->field_sources ?? [], 'origin');

        return $origins !== [] && array_values(array_unique($origins)) === ['scrape'];
    }

    private function hasItRole(Contest $contest): bool
    {
        return $contest->rawListings->contains(function (RawListing $raw) {
            $haystack = $raw->title.' '.($raw->payload['text'] ?? '').' | '.implode(' | ', $raw->payload['roles'] ?? []);

            return $this->normalizer->matchedAreas($haystack) !== [];
        });
    }
}
