<?php

namespace App\Models;

use App\Enums\ChangeKind;
use Database\Factories\ContestChangeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $contest_id
 * @property int|null $search_run_id
 * @property ChangeKind $kind
 * @property string|null $field
 * @property mixed $old_value
 * @property mixed $new_value
 * @property string $origin
 * @property string|null $note
 * @property Carbon $created_at
 */
class ContestChange extends Model
{
    /** @use HasFactory<ContestChangeFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'contest_id',
        'search_run_id',
        'kind',
        'field',
        'old_value',
        'new_value',
        'origin',
        'note',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'kind' => ChangeKind::class,
            'old_value' => 'array',
            'new_value' => 'array',
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

    /**
     * @return BelongsTo<SearchRun, $this>
     */
    public function searchRun(): BelongsTo
    {
        return $this->belongsTo(SearchRun::class);
    }
}
