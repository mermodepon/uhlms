<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements FilamentUser
{
    public const REPORT_MONTHLY_VIEW = 'reports_monthly_view';

    public const REPORT_RESERVATION_SUMMARY_VIEW = 'reports_reservation_summary_view';

    public const REPORT_RESERVATION_LIST_VIEW = 'reports_reservation_list_view';

    public const REPORT_OCCUPANCY_VIEW = 'reports_occupancy_view';

    public const REPORT_ROOM_UTILIZATION_VIEW = 'reports_room_utilization_view';

    public const REPORT_GENDER_STATISTICS_VIEW = 'reports_gender_statistics_view';

    public const REPORT_FEEDBACK_ANALYTICS_VIEW = 'reports_feedback_analytics_view';

    public const REPORT_STAY_LOGS_VIEW = 'reports_stay_logs_view';

    public const REPORT_MONTHLY_EXPORT = 'reports_monthly_export';

    public const ROOM_HOLDS_VIEW = 'room_holds_view';

    public const ROOM_HOLDS_RELEASE = 'room_holds_release';

    public const VIRTUAL_TOUR_VIEW = 'virtual_tour_view';

    public const VIRTUAL_TOUR_MANAGE = 'virtual_tour_manage';

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

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
        'two_factor_secret',
        'two_factor_recovery_codes',
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
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
        ];
    }

    /**
     * Role-based default permissions (applied when no custom permissions are set).
     */
    private static array $roleDefaults = [
        'staff' => [
            'reservations_view' => true,
            'reservations_create' => true,
            'reservations_edit' => true,
            'reservations_delete' => false,
            'reservation_discount_settings_view' => false,
            'reservation_discount_settings_edit' => false,
            'online_payment_settings_view' => false,
            'online_payment_settings_edit' => false,
            'guest_site_settings_view' => false,
            'guest_site_settings_edit' => false,
            'rooms_view' => true,
            'rooms_create' => false,
            'rooms_edit' => false,
            'rooms_delete' => false,
            'room_types_view' => true,
            'room_types_create' => false,
            'room_types_edit' => false,
            'room_types_delete' => false,
            'floors_view' => true,
            'floors_create' => false,
            'floors_edit' => false,
            'floors_delete' => false,
            'amenities_view' => true,
            'amenities_create' => false,
            'amenities_edit' => false,
            'amenities_delete' => false,
            'addons_view' => true,
            'addons_create' => false,
            'addons_edit' => false,
            'addons_delete' => false,
            'users_view' => false,
            'users_create' => false,
            'users_edit' => false,
            'users_delete' => false,
            'guest_accounts_view' => true,
            'guest_accounts_edit' => false,
            'guest_accounts_disable' => false,
            'guest_feedback_view' => true,
            'guest_feedback_edit' => false,
            'support_inquiries_view' => true,
            'support_inquiries_edit' => true,
            'stay_logs_view' => false,
            self::REPORT_MONTHLY_VIEW => false,
            self::REPORT_RESERVATION_SUMMARY_VIEW => true,
            self::REPORT_RESERVATION_LIST_VIEW => true,
            self::REPORT_OCCUPANCY_VIEW => true,
            self::REPORT_ROOM_UTILIZATION_VIEW => true,
            self::REPORT_GENDER_STATISTICS_VIEW => false,
            self::REPORT_FEEDBACK_ANALYTICS_VIEW => false,
            self::REPORT_STAY_LOGS_VIEW => false,
            self::REPORT_MONTHLY_EXPORT => false,
            self::ROOM_HOLDS_VIEW => true,
            self::ROOM_HOLDS_RELEASE => false,
            self::VIRTUAL_TOUR_VIEW => false,
            self::VIRTUAL_TOUR_MANAGE => false,
        ],
        'admin' => [
            'reservations_view' => true,
            'reservations_create' => true,
            'reservations_edit' => true,
            'reservations_delete' => true,
            'reservation_discount_settings_view' => true,
            'reservation_discount_settings_edit' => true,
            'online_payment_settings_view' => true,
            'online_payment_settings_edit' => true,
            'guest_site_settings_view' => true,
            'guest_site_settings_edit' => true,
            'rooms_view' => true,
            'rooms_create' => true,
            'rooms_edit' => true,
            'rooms_delete' => true,
            'room_types_view' => true,
            'room_types_create' => true,
            'room_types_edit' => true,
            'room_types_delete' => true,
            'floors_view' => true,
            'floors_create' => true,
            'floors_edit' => true,
            'floors_delete' => true,
            'amenities_view' => true,
            'amenities_create' => true,
            'amenities_edit' => true,
            'amenities_delete' => true,
            'addons_view' => true,
            'addons_create' => true,
            'addons_edit' => true,
            'addons_delete' => true,
            'users_view' => true,
            'users_create' => true,
            'users_edit' => true,
            'users_delete' => true,
            'guest_accounts_view' => true,
            'guest_accounts_edit' => true,
            'guest_accounts_disable' => true,
            'guest_feedback_view' => true,
            'guest_feedback_edit' => true,
            'support_inquiries_view' => true,
            'support_inquiries_edit' => true,
            'stay_logs_view' => true,
            self::REPORT_MONTHLY_VIEW => true,
            self::REPORT_RESERVATION_SUMMARY_VIEW => true,
            self::REPORT_RESERVATION_LIST_VIEW => true,
            self::REPORT_OCCUPANCY_VIEW => true,
            self::REPORT_ROOM_UTILIZATION_VIEW => true,
            self::REPORT_GENDER_STATISTICS_VIEW => true,
            self::REPORT_FEEDBACK_ANALYTICS_VIEW => true,
            self::REPORT_STAY_LOGS_VIEW => true,
            self::REPORT_MONTHLY_EXPORT => true,
            self::ROOM_HOLDS_VIEW => true,
            self::ROOM_HOLDS_RELEASE => true,
            self::VIRTUAL_TOUR_VIEW => true,
            self::VIRTUAL_TOUR_MANAGE => true,
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

    /**
     * Central permission definitions shared by the user editor and reference matrix.
     *
     * @return array<string, array<int, array{key: string, label: string}>>
     */
    public static function permissionGroups(): array
    {
        return [
            'Reservations' => [
                ['key' => 'reservations_view', 'label' => 'View'],
                ['key' => 'reservations_create', 'label' => 'Create'],
                ['key' => 'reservations_edit', 'label' => 'Edit'],
                ['key' => 'reservations_delete', 'label' => 'Delete'],
                ['key' => 'reservation_discount_settings_view', 'label' => 'View Discount Config'],
                ['key' => 'reservation_discount_settings_edit', 'label' => 'Edit Discount Config'],
                ['key' => 'online_payment_settings_view', 'label' => 'View Payment Config'],
                ['key' => 'online_payment_settings_edit', 'label' => 'Edit Payment Config'],
                ['key' => 'guest_site_settings_view', 'label' => 'View Guest Site Settings'],
                ['key' => 'guest_site_settings_edit', 'label' => 'Edit Guest Site Settings'],
            ],
            'Rooms' => [
                ['key' => 'rooms_view', 'label' => 'View'],
                ['key' => 'rooms_create', 'label' => 'Create'],
                ['key' => 'rooms_edit', 'label' => 'Edit'],
                ['key' => 'rooms_delete', 'label' => 'Delete'],
            ],
            'Room Types' => [
                ['key' => 'room_types_view', 'label' => 'View'],
                ['key' => 'room_types_create', 'label' => 'Create'],
                ['key' => 'room_types_edit', 'label' => 'Edit'],
                ['key' => 'room_types_delete', 'label' => 'Delete'],
            ],
            'Floors' => [
                ['key' => 'floors_view', 'label' => 'View'],
                ['key' => 'floors_create', 'label' => 'Create'],
                ['key' => 'floors_edit', 'label' => 'Edit'],
                ['key' => 'floors_delete', 'label' => 'Delete'],
            ],
            'Amenities' => [
                ['key' => 'amenities_view', 'label' => 'View'],
                ['key' => 'amenities_create', 'label' => 'Create'],
                ['key' => 'amenities_edit', 'label' => 'Edit'],
                ['key' => 'amenities_delete', 'label' => 'Delete'],
            ],
            'Add-Ons' => [
                ['key' => 'addons_view', 'label' => 'View'],
                ['key' => 'addons_create', 'label' => 'Create'],
                ['key' => 'addons_edit', 'label' => 'Edit'],
                ['key' => 'addons_delete', 'label' => 'Delete'],
            ],
            'Users' => [
                ['key' => 'users_view', 'label' => 'View'],
                ['key' => 'users_create', 'label' => 'Create'],
                ['key' => 'users_edit', 'label' => 'Edit'],
                ['key' => 'users_delete', 'label' => 'Delete'],
            ],
            'Guest Accounts' => [
                ['key' => 'guest_accounts_view', 'label' => 'View'],
                ['key' => 'guest_accounts_edit', 'label' => 'Edit'],
                ['key' => 'guest_accounts_disable', 'label' => 'Disable / Enable'],
            ],
            'Guest Feedback' => [
                ['key' => 'guest_feedback_view', 'label' => 'View'],
                ['key' => 'guest_feedback_edit', 'label' => 'Review / Notes'],
            ],
            'Support Inquiries' => [
                ['key' => 'support_inquiries_view', 'label' => 'View'],
                ['key' => 'support_inquiries_edit', 'label' => 'Triage / Notes / Reply'],
            ],
            'Stay Logs' => [
                ['key' => 'stay_logs_view', 'label' => 'View Stay Logs'],
            ],
            'Reports' => [
                ['key' => self::REPORT_MONTHLY_VIEW, 'label' => 'Monthly Report'],
                ['key' => self::REPORT_RESERVATION_SUMMARY_VIEW, 'label' => 'Reservation Summary'],
                ['key' => self::REPORT_RESERVATION_LIST_VIEW, 'label' => 'Reservation List'],
                ['key' => self::REPORT_OCCUPANCY_VIEW, 'label' => 'Occupancy'],
                ['key' => self::REPORT_ROOM_UTILIZATION_VIEW, 'label' => 'Room Utilization'],
                ['key' => self::REPORT_GENDER_STATISTICS_VIEW, 'label' => 'Gender Statistics'],
                ['key' => self::REPORT_FEEDBACK_ANALYTICS_VIEW, 'label' => 'Feedback Analytics'],
                ['key' => self::REPORT_STAY_LOGS_VIEW, 'label' => 'Stay Logs'],
                ['key' => self::REPORT_MONTHLY_EXPORT, 'label' => 'Export Monthly Report'],
            ],
            'Room Holds' => [
                ['key' => self::ROOM_HOLDS_VIEW, 'label' => 'View'],
                ['key' => self::ROOM_HOLDS_RELEASE, 'label' => 'Release Holds'],
            ],
            'Virtual Tour' => [
                ['key' => self::VIRTUAL_TOUR_VIEW, 'label' => 'View'],
                ['key' => self::VIRTUAL_TOUR_MANAGE, 'label' => 'Manage'],
            ],
        ];
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
}
