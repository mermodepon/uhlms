<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class GuestAccount extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'last_name',
        'first_name',
        'middle_initial',
        'name',
        'email',
        'email_verified_at',
        'password',
        'phone',
        'gender',
        'age',
        'address',
        'last_login_at',
        'disabled_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'age' => 'integer',
            'last_login_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $account) {
            $name = trim(
                ($account->first_name ?? '').' '.
                ($account->middle_initial ?? '').' '.
                ($account->last_name ?? '')
            );

            if ($name !== '') {
                $account->name = $name;
            }
        });
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(ReservationFeedback::class);
    }

    public function supportInquiries(): HasMany
    {
        return $this->hasMany(SupportInquiry::class);
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    public function hasVerifiedEmail(): bool
    {
        return ($this->getAttributes()['email_verified_at'] ?? null) !== null;
    }
}
