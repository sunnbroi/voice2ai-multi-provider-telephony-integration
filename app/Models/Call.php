<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Call
 *
 * @property int $id
 * @property int $integration_id
 * @property Carbon $call_time
 * @property string $direction
 * @property string $status
 * @property bool $is_conflict
 * @property int|null $duration
 * @property string|null $operator_name
 * @property string $from_phone
 * @property string $to_phone
 * @property string|null $recording_url
 * @property string|null $recording_status
 * @property bool $listened
 * @property bool $starred
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $external_call_id
 * @property bool $lead
 * @property bool $new_client
 * @property string|null $tags
 */
class Call extends Model
{
    use HasFactory;

    protected $fillable = [
        'integration_id',
        'external_call_id',
        'call_time',
        'direction',
        'status',
        'duration',
        'operator_name',
        'from_phone',
        'to_phone',
        'recording_url',
        'recording_status',
        'listened',
        'starred'
    ];

    protected $casts = [
        'is_conflict' => 'boolean',
        'listened' => 'boolean',
        'starred' => 'boolean',
        'lead' => 'boolean',
        'new_client' => 'boolean'
    ];

    public function getFormattedCallTimeAttribute(): string
    {
        $dt = $this->call_time; // уже Carbon, благодаря аксессору

        return $dt->isToday()
            ? $dt->format('H:i:s')
            : $dt->format('d.m.Y');
    }

    public function getFullCallTimeAttribute(): string
    {
        return $this->call_time->format('d.m.Y H:i:s');
    }

    /**
     * accessor для установки таймзоны
     * @return Attribute
     */
    protected function callTime(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => \Illuminate\Support\Carbon::parse($value)->timezone('Europe/Moscow'),
        );
    }

    /**
     * @return BelongsTo
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
