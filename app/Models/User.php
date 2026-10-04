<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use App\Support\LianBarangays;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        // The signed-in user is cached between requests; any change to a user (role, status,
        // password, profile) must be seen by their very next request.
        $forget = fn (self $user) => \App\Auth\CachedEloquentUserProvider::forget($user->getKey());

        static::saved($forget);
        static::deleted($forget);
    }

    public const ROLE_FARMER = 'Farmer';

    public const ROLE_MAO = 'MAO Personnel';

    public const ROLE_IT_EXPERT = 'IT Expert';

    public const STATUS_ACTIVE = 'Active';

    public const STATUS_INACTIVE = 'Inactive';

    public const ONLINE_WINDOW_MINUTES = 2;

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
        'contact_number',
        'address',
        'barangay',
        'status',
    ];

    public function farmerProfile(): HasOne
    {
        return $this->hasOne(FarmerProfile::class);
    }

    /** Resolve the exact registered Lian barangay without guessing ambiguous values. */
    public function registeredBarangay(): ?string
    {
        $profileValue = $this->farmerProfile?->barangay;
        if (is_string($profileValue) && trim($profileValue) !== '') {
            // A present but ambiguous profile value is a genuine data-quality
            // gap; never fall back to a different account value and silently
            // highlight the wrong barangay.
            return LianBarangays::canonicalName($profileValue);
        }

        return LianBarangays::canonicalName($this->barangay);
    }

    public function plantingSchedules(): HasMany
    {
        return $this->hasMany(FarmerPlantingSchedule::class);
    }

    public function isOnline(): bool
    {
        return (bool) $this->last_active_at?->gt(now()->subMinutes(self::ONLINE_WINDOW_MINUTES));
    }

    public function plantingAdvisories(): HasMany
    {
        return $this->hasMany(PlantingAdvisory::class, 'posted_by');
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class, 'posted_by');
    }

    public function userNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function typhoonSafetyResponses(): HasMany
    {
        return $this->hasMany(TyphoonSafetyResponse::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'generated_by');
    }

    public function systemLogs(): HasMany
    {
        return $this->hasMany(SystemLog::class);
    }

    /** Accounts older than a day that have never logged in nor been used (no login entry, no recorded activity). */
    public function scopeNeverLoggedIn(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNull('users.last_active_at')->where('users.created_at', '<', now()->subDay())
            ->whereRaw("NOT EXISTS (SELECT 1 FROM system_logs sl WHERE sl.user_id = users.id AND LOWER(sl.action) IN ('user login', 'login'))");
    }

    public function roleLabel(): string
    {
        return $this->role;
    }

    public function dashboardRoute(): string
    {
        return match ($this->role) {
            self::ROLE_MAO => 'mao.dashboard',
            self::ROLE_IT_EXPERT => 'admin.dashboard',
            default => 'farmer.dashboard',
        };
    }

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
            'password' => 'hashed',
            'last_active_at' => 'datetime',
        ];
    }
}
