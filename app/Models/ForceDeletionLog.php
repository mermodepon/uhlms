<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForceDeletionLog extends Model
{
    protected $fillable = [
        'reference_number',
        'guest_name',
        'status',
        'check_in_date',
        'check_out_date',
        'reason',
        'deleted_by',
        'deleted_by_name',
        'related_counts',
        'reservation_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'related_counts' => 'array',
            'reservation_snapshot' => 'array',
        ];
    }

    public function deletedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
