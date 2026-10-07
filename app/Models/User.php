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

    public const AI_LANGUAGE_EN = 'en';

    public const AI_LANGUAGE_TL = 'tl';

    public const STATUS_ACTIVE = 'Active';

    public const STATUS_INACTIVE = 'Inactive';

    // Manuscript – Naicel (2026-10-08): a farmer sign-up waits for MAO approval (config iclimate.farmer_approval).
    public const STATUS_PENDING = 'Pending';

    public const STATUS_REJECTED = 'Rejected';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_PENDING, self::STATUS_REJECTED];

    /** What a person who cannot sign in is told, by account status. */
    public static function signInRefusal(?string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => 'Your account is waiting for approval by the Municipal Agriculture Office. You can log in once it is approved.',
            self::STATUS_REJECTED => 'Your registration was not approved by the Municipal Agriculture Office. Please visit or contact the office.',
            default => 'This account is inactive. Please contact the administrator.',
        };
    }
    // End Manuscript – Naicel

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
        'ai_language',
    ];

    public function farmerProfile(): HasOne
    {
        return $this->hasOne(FarmerProfile::class);
    }

    /** Every farm this farmer owns or manages (archived ones too; use ->active()). */
    public function farms(): HasMany
    {
        return $this->hasMany(Farm::class);
    }

    /**
     * The farmer's Lian barangay without guessing ambiguous values. For the signed-in farmer it is the barangay of the
     * farm they are viewing (App\Support\ActiveFarm), so the Home, Heat Map, advisories, forecast and Climora all
     * follow the farm picked in the My Farm selector; anyone else reading it (MAO lists, notices) gets the primary
     * farm's barangay, which the profile mirrors.
     */
    public function registeredBarangay(): ?string
    {
        if ($this->role === self::ROLE_FARMER && (int) auth()->id() === (int) $this->getKey()
            && ($farm = \App\Support\ActiveFarm::current($this)) !== null && ($farmBarangay = LianBarangays::canonicalName($farm->barangay)) !== null) {
            return $farmBarangay;
        }

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

    /** The role as people read it. The stored value of the IT role stays "IT Expert"; every screen says "IT Personnel". */
    public static function labelForRole(?string $role): string
    {
        return $role === self::ROLE_IT_EXPERT ? 'IT Personnel' : (string) $role;
    }

    public function roleLabel(): string
    {
        return self::labelForRole($this->role);
    }

    public function aiLanguageCode(): ?string
    {
        return match ($this->ai_language) {
            self::AI_LANGUAGE_EN, 'English' => self::AI_LANGUAGE_EN,
            self::AI_LANGUAGE_TL, 'Tagalog' => self::AI_LANGUAGE_TL,
            default => null,
        };
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
            // Manuscript – Naicel
            'last_login_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }
}
