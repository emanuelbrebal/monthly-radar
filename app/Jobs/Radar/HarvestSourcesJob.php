<?php

namespace App\Jobs\Radar;

use App\Models\RawListing;
use App\Models\SearchRun;
use App\Services\Radar\Sources\ContestSourceRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Passo 1 do pipeline (docs/02-pipeline.md): raspa cada fonte ativa,
 * grava/atualiza raw_listings por content_hash. Falha num adapter não
 * derruba a run — vai pra meta.errors e a run segue com as outras fontes.
 */
class HarvestSourcesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $searchRunId) {}

    public function handle(ContestSourceRegistry $registry): void
    {
        $run = SearchRun::query()->findOrFail($this->searchRunId);
        $errors = $run->meta['errors'] ?? [];
        $scraped = 0;

        foreach ($registry->enabled() as $source) {
            try {
                foreach ($source->fetch() as $listing) {
                    if ($this->store($source->key(), $listing)) {
                        $scraped++;
                    }
                }
            } catch (Throwable $e) {
                $errors[] = ['source' => $source->key(), 'message' => $e->getMessage()];
            }
        }

        $run->meta = [...$run->meta, 'errors' => $errors];
        $run->counts = [...$run->counts, 'scraped' => ($run->counts['scraped'] ?? 0) + $scraped];
        $run->save();
    }

    /**
     * Grava/atualiza por content_hash. Hash já existente com fetched_at
     * < 6h → pula (não conta como raspado de novo nesta run).
     */
    private function store(string $sourceKey, RawListing $listing): bool
    {
        $normalizedText = Str::squish(Str::lower((string) ($listing->payload['text'] ?? '')));
        $hash = hash('sha256', "{$sourceKey}|{$listing->external_url}|{$normalizedText}");

        $existing = RawListing::query()->where('content_hash', $hash)->first();

        if ($existing !== null && $existing->fetched_at->gt(Carbon::now()->subHours(6))) {
            return false;
        }

        RawListing::query()->updateOrCreate(
            ['content_hash' => $hash],
            [
                'source' => $sourceKey,
                'external_url' => $listing->external_url,
                'title' => $listing->title,
                'payload' => $listing->payload,
                'fetched_at' => Carbon::now(),
            ],
        );

        return true;
    }
}
