<?php

namespace App\Services\Radar\Sources;

/**
 * Lista os adapters disponíveis.
 */
class ContestSourceRegistry
{
    /**
     * @var array<int, class-string<ContestSource>>
     */
    private array $classes = [
        ProvasDeTiSource::class,
        PciConcursosSource::class,
        OfficialPagesSource::class,
    ];

    /**
     * @return array<int, ContestSource>
     */
    public function all(): array
    {
        return array_map(fn (string $class) => app($class), $this->classes);
    }

    /**
     * @return array<int, ContestSource>
     */
    public function enabled(): array
    {
        return array_values(array_filter($this->all(), fn (ContestSource $s) => $s->enabled()));
    }

    public function find(string $key): ?ContestSource
    {
        foreach ($this->all() as $source) {
            if ($source->key() === $key) {
                return $source;
            }
        }

        return null;
    }
}
