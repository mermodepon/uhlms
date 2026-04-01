<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Service extends Model
{
    protected $fillable = [
        'name',
        'code',
        'category',
        'description',
        'price',
        'is_active',
        'sort_order',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $service) {
            if (empty($service->code)) {
                $base = Str::slug($service->name);
                $code = $base;
                $i = 1;
                while (static::where('code', $code)->exists()) {
                    $code = $base . '-' . $i++;
                }
                $service->code = $code;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get active add-ons only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort_order then name
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        if ($this->price == 0) {
            return 'Free';
        }
        return '₱' . number_format($this->price, 2);
    }
}
