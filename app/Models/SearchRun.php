<?php

namespace App\Models;

use App\Enums\RunStatus;
use App\Enums\TiArea;
use Database\Factories\SearchRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property TiArea $area
 * @property RunStatus $status
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property array<string, int> $counts
 * @property array<string, mixed> $meta
 */
class SearchRun extends Model
{
    /** @use HasFactory<SearchRunFactory> */
    use HasFactory;

    protected $fillable = [
        'area',
        'status',
        'started_at',
        'finished_at',
        'counts',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'area' => TiArea::class,
            'status' => RunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'counts' => 'array',
            'meta' => 'array',
        ];
    }
}
