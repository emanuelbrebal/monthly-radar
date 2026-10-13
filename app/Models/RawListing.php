<?php

namespace App\Models;

use Database\Factories\RawListingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $source
 * @property string $external_url
 * @property string $title
 * @property array<string, mixed> $payload
 * @property string $content_hash
 * @property int|null $contest_id
 * @property Carbon $fetched_at
 */
class RawListing extends Model
{
    /** @use HasFactory<RawListingFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'source',
        'external_url',
        'title',
        'payload',
        'content_hash',
        'contest_id',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'fetched_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Contest, $this>
     */
    public function contest(): BelongsTo
    {
        return $this->belongsTo(Contest::class);
    }
}
