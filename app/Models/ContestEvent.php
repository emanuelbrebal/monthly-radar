<?php

namespace App\Models;

use App\Enums\EventType;
use Database\Factories\ContestEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $contest_id
 * @property EventType $type
 * @property Carbon $date
 * @property string|null $label
 * @property string $source
 */
class ContestEvent extends Model
{
    /** @use HasFactory<ContestEventFactory> */
    use HasFactory;

    protected $fillable = [
        'contest_id',
        'type',
        'date',
        'label',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'date' => 'date',
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
