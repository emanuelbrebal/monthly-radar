<?php

use App\Jobs\Radar\HarvestSourcesJob;
use App\Models\RawListing;
use App\Models\SearchRun;
use App\Services\Radar\Sources\ContestSource;
use App\Services\Radar\Sources\ContestSourceRegistry;

class FakeWorkingSource implements ContestSource
{
    /**
     * @param  array<int, RawListing>  $listings
     */
    public function __construct(private array $listings, private bool $isEnabled = true) {}

    public function key(): string
    {
        return 'fake_working';
    }

    public function enabled(): bool
    {
        return $this->isEnabled;
    }

    public function fetch(): iterable
    {
        return $this->listings;
    }
}

class FakeBrokenSource implements ContestSource
{
    public function key(): string
    {
        return 'fake_broken';
    }

    public function enabled(): bool
    {
        return true;
    }

    public function fetch(): iterable
    {
        throw new RuntimeException('fonte exige navegador');
    }
}

function fakeListing(string $title, string $url, string $text = ''): RawListing
{
    return new RawListing([
        'external_url' => $url,
        'title' => $title,
        'payload' => ['text' => $text, 'links' => []],
    ]);
}

it('stores raw listings from every enabled source', function () {
    $run = SearchRun::factory()->create();

    $registry = Mockery::mock(ContestSourceRegistry::class);
    $registry->shouldReceive('enabled')->andReturn([
        new FakeWorkingSource([fakeListing('Concurso A', 'https://x.com/a'), fakeListing('Concurso B', 'https://x.com/b')]),
    ]);

    app()->instance(ContestSourceRegistry::class, $registry);

    (new HarvestSourcesJob($run->id))->handle($registry);

    expect(RawListing::query()->count())->toBe(2);
    expect($run->refresh()->counts['scraped'])->toBe(2);
});

it('skips a listing whose hash was already fetched less than 6h ago', function () {
    $run = SearchRun::factory()->create();

    $registry = Mockery::mock(ContestSourceRegistry::class);
    $registry->shouldReceive('enabled')->andReturn([
        new FakeWorkingSource([fakeListing('Concurso A', 'https://x.com/a', 'mesmo texto')]),
    ]);

    (new HarvestSourcesJob($run->id))->handle($registry);
    (new HarvestSourcesJob($run->id))->handle($registry);

    expect(RawListing::query()->count())->toBe(1);
    expect($run->refresh()->counts['scraped'])->toBe(1);
});

it('re-fetches a listing whose hash is older than 6h', function () {
    $run = SearchRun::factory()->create();

    $registry = Mockery::mock(ContestSourceRegistry::class);
    $registry->shouldReceive('enabled')->andReturn([
        new FakeWorkingSource([fakeListing('Concurso A', 'https://x.com/a', 'mesmo texto')]),
    ]);

    (new HarvestSourcesJob($run->id))->handle($registry);

    RawListing::query()->update(['fetched_at' => now()->subHours(7)]);

    (new HarvestSourcesJob($run->id))->handle($registry);

    expect(RawListing::query()->count())->toBe(1);
    expect($run->refresh()->counts['scraped'])->toBe(2);
});

it('logs a failing source into meta.errors without stopping the others', function () {
    $run = SearchRun::factory()->create();

    $registry = Mockery::mock(ContestSourceRegistry::class);
    $registry->shouldReceive('enabled')->andReturn([
        new FakeBrokenSource,
        new FakeWorkingSource([fakeListing('Concurso A', 'https://x.com/a')]),
    ]);

    (new HarvestSourcesJob($run->id))->handle($registry);

    expect(RawListing::query()->count())->toBe(1);
    expect($run->refresh()->meta['errors'])->toHaveCount(1)
        ->and($run->meta['errors'][0]['source'])->toBe('fake_broken');
});
