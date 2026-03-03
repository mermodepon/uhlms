<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'permissions',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'permissions'       => 'array',
        ];
    }

    /**
     * Role-based default permissions (applied when no custom permissions are set).
     */
    private static array $roleDefaults = [
        'staff' => [
            'reservations_view'    => true,
            'reservations_create'  => true,
            'reservations_edit'    => true,
            'reservations_delete'  => false,
            'rooms_view'           => true,
            'rooms_create'         => false,
            'rooms_edit'           => false,
            'rooms_delete'         => false,
            'room_types_view'      => true,
            'room_types_create'    => false,
            'room_types_edit'      => false,
            'room_types_delete'    => false,
            'floors_view'          => true,
            'floors_create'        => false,
            'floors_edit'          => false,
            'floors_delete'        => false,
            'amenities_view'       => true,
            'amenities_create'     => false,
            'amenities_edit'       => false,
            'amenities_delete'     => false,
            'services_view'        => true,
            'services_create'      => false,
            'services_edit'        => false,
            'services_delete'      => false,
            'users_view'           => false,
            'users_create'         => false,
            'users_edit'           => false,
            'users_delete'         => false,
            'settings_view'        => false,
            'settings_edit'        => false,
            'stay_logs_view'       => true,
        ],
        'admin' => [
            'reservations_view'    => true,
            'reservations_create'  => true,
            'reservations_edit'    => true,
            'reservations_delete'  => true,
            'rooms_view'           => true,
            'rooms_create'         => true,
            'rooms_edit'           => true,
            'rooms_delete'         => true,
            'room_types_view'      => true,
            'room_types_create'    => true,
            'room_types_edit'      => true,
            'room_types_delete'    => true,
            'floors_view'          => true,
            'floors_create'        => true,
            'floors_edit'          => true,
            'floors_delete'        => true,
            'amenities_view'       => true,
            'amenities_create'     => true,
            'amenities_edit'       => true,
            'amenities_delete'     => true,
            'services_view'        => true,
            'services_create'      => true,
            'services_edit'        => true,
            'services_delete'      => true,
            'users_view'           => true,
            'users_create'         => true,
            'users_edit'           => true,
            'users_delete'         => true,
            'settings_view'        => true,
            'settings_edit'        => true,
            'stay_logs_view'       => true,
        ],
    ];

    /**
     * Check if this user has a given permission.
     * Super admins always return true.
     * If custom permissions are stored, they take precedence over the role.
     * Otherwise, role-based defaults are used.
     */
    public function hasPermission(string $key): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Custom permissions override role defaults
        if ($this->permissions !== null) {
            return (bool) ($this->permissions[$key] ?? false);
        }

        // Fall back to role-based defaults
        $defaults = static::$roleDefaults[$this->role] ?? [];
        return (bool) ($defaults[$key] ?? false);
    }

    /**
     * Return the default permission set for a given role (for seeding the UI).
     */
    public static function defaultPermissionsForRole(string $role): array
    {
        return static::$roleDefaults[$role] ?? static::$roleDefaults['staff'];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, ['super_admin', 'admin', 'staff']);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        // super_admin inherits all admin privileges
        return in_array($this->role, ['super_admin', 'admin']);
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['super_admin', 'admin', 'staff']);
    }

    public function reviewedReservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'reviewed_by');
    }

    public function roomAssignments(): HasMany
    {
        return $this->hasMany(RoomAssignment::class, 'assigned_by');
    }

    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable')->orderByDesc('created_at');
    }

    public function unreadNotifications()
    {
        return $this->notifications()->where('is_read', false);
    }

    public function getUnreadNotificationCountAttribute(): int
    {
        return $this->unreadNotifications()->count();
    }
}
