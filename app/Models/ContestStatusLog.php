<?php

namespace App\Models;

use Database\Factories\ContestStatusLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $contest_id
 * @property string|null $from
 * @property string $to
 * @property string $origin
 * @property string|null $note
 * @property Carbon $created_at
 */
class ContestStatusLog extends Model
{
    /** @use HasFactory<ContestStatusLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'contest_id',
        'from',
        'to',
        'origin',
        'note',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
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
