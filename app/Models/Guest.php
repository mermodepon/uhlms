<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guest extends Model
{
    protected $fillable = [
        'reservation_id',
        'full_name',
        'first_name',
        'last_name',
        'middle_initial',
        'relationship_to_primary',
        'age',
        'gender',
        'contact_number',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'age' => 'integer',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
