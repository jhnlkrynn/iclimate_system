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
        // Panel revision 4.4: a changed MAO account refreshes the cached coverage and "Your MAO personnel" cards.
        static::saved(function (self $user): void {
            if ($user->role === self::ROLE_MAO || $user->getOriginal('role') === self::ROLE_MAO) {
                self::forgetMaoPersonnel();
            }
        });
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
        // Panel revision 4.4: MAO personnel details.
        'position',
        'station_type',
        'area_covered_ha',
        'is_mao_head',
        'must_change_password',
    ];

    // ------------------------------------------------------------ Panel revision 4.4: MAO personnel

    public const STATION_MUNICIPAL = 'Municipal';

    public const STATION_PROVINCIAL_PAID = 'Provincial Paid';

    public const STATION_TYPES = [self::STATION_MUNICIPAL, self::STATION_PROVINCIAL_PAID];

    /** The barangays (or sitios) this MAO user covers. */
    public function maoBarangays(): HasMany
    {
        return $this->hasMany(MaoUserBarangay::class)->orderBy('barangay');
    }

    public function isMao(): bool
    {
        return $this->role === self::ROLE_MAO;
    }

    /** The Municipal Agriculturist: municipality-wide data, MAO account management, every MAO user's activity log. */
    public function isMaoHead(): bool
    {
        return $this->isMao() && (bool) $this->is_mao_head;
    }

    /**
     * Official barangay names this MAO user is assigned to. Empty for the head (who covers all of Lian) and for an
     * MAO account that has no assignment yet.
     *
     * @return array<int, string>
     */
    public function coveredBarangays(): array
    {
        return array_values(array_unique(array_column($this->maoAssignments(), 'barangay')));
    }

    /** "Kapito, Malaruhatan, Bungahan, San Diego", or "All barangays of Lian" for the head. */
    public function coverageLabel(): string
    {
        if ($this->isMaoHead()) {
            return 'All barangays of Lian (municipal-wide)';
        }
        $labels = array_map(fn (array $a): string => $a['sitio'] ? $a['sitio'].' ('.$a['barangay'].')' : $a['barangay'], $this->maoAssignments());

        return $labels === [] ? 'No barangay assigned yet' : implode(', ', $labels);
    }

    /** @var array<string, array<int, array{barangay: string, sitio: ?string}>> this process's copies, by cache key */
    private static array $maoAssignmentsMemo = [];

    /**
     * The barangays (and sitios) this MAO user covers, read once and kept in the cache (every MAO page shows them), so a
     * page costs no query for it. Dropped whenever an assignment changes (MaoUserBarangay events).
     *
     * @return array<int, array{barangay: string, sitio: ?string}>
     */
    public function maoAssignments(): array
    {
        if (! $this->isMao() || ! \App\Support\SchemaCache::hasTable('mao_user_barangays')) {
            return [];
        }
        if ($this->relationLoaded('maoBarangays')) {
            return $this->maoBarangays->map(fn (MaoUserBarangay $b): array => ['barangay' => $b->barangay, 'sitio' => $b->sitio])->values()->all();
        }

        $key = self::maoAssignmentsKey((int) $this->getKey());

        return self::$maoAssignmentsMemo[$key] ??= \Illuminate\Support\Facades\Cache::remember(
            $key,
            now()->addMinutes(30),
            fn (): array => MaoUserBarangay::query()->where('user_id', $this->getKey())->orderBy('barangay')
                ->get(['barangay', 'sitio'])->map(fn (MaoUserBarangay $b): array => ['barangay' => $b->barangay, 'sitio' => $b->sitio])->all(),
        );
    }

    public static function maoAssignmentsKey(int $userId): string
    {
        return 'mao-assignments:'.$userId.':v'.self::maoPersonnelVersion();
    }

    /** Bumped whenever an MAO account or an assignment changes: every cached coverage and contact list is then rebuilt. */
    public static function maoPersonnelVersion(): int
    {
        return (int) \Illuminate\Support\Facades\Cache::get('mao-personnel-version', 1);
    }

    public static function forgetMaoPersonnel(): void
    {
        \Illuminate\Support\Facades\Cache::forever('mao-personnel-version', self::maoPersonnelVersion() + 1);
    }

    /**
     * The farmer's "Your MAO personnel" card: names, positions and numbers of the staff covering the barangay (and the
     * Municipal Agriculturist), cached so the farmer's pages cost no query for it.
     *
     * @return array{people: array<int, array{name: string, position: string, contact: ?string}>, head: ?array{name: string, position: string}}
     */
    public static function maoContactsFor(?string $barangay, ?string $sitio = null): array
    {
        $name = LianBarangays::canonicalName($barangay);
        if ($name === null) {
            return ['people' => [], 'head' => null];
        }

        return \Illuminate\Support\Facades\Cache::remember('mao-contacts:'.md5($name.'|'.$sitio).':v'.self::maoPersonnelVersion(), now()->addMinutes(30), function () use ($name, $sitio): array {
            $head = self::maoHead();

            return [
                'people' => self::maoPersonnelFor($name, $sitio)->map(fn (self $u): array => ['name' => $u->name, 'position' => $u->positionLabel(), 'contact' => $u->contact_number])->all(),
                'head' => $head ? ['name' => $head->name, 'position' => $head->positionLabel()] : null,
            ];
        });
    }

    /** The position, or the role when an account has none ("MAO Personnel"). */
    public function positionLabel(): string
    {
        return filled($this->position) ? (string) $this->position : $this->roleLabel();
    }

    /** "Jean L. Laviste – Agricultural Technologist". */
    public function maoTitle(): string
    {
        return $this->name.' – '.$this->positionLabel();
    }

    /**
     * The MAO personnel assigned to a barangay (and sitio, when given), field staff first. The Municipal Agriculturist
     * is not listed here; the farmer page names them separately.
     *
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function maoPersonnelFor(?string $barangay, ?string $sitio = null): \Illuminate\Support\Collection
    {
        $name = LianBarangays::canonicalName($barangay);
        if ($name === null || ! \App\Support\SchemaCache::hasTable('mao_user_barangays')) {
            return collect();
        }

        $assignments = MaoUserBarangay::query()->with('user')->where('barangay', $name)->get()
            ->filter(fn (MaoUserBarangay $a): bool => $a->user && $a->user->isMao() && $a->user->status === self::STATUS_ACTIVE);

        // A sitio-specific assignment wins for a farmer in that sitio; otherwise the whole-barangay assignment.
        $matching = $sitio ? $assignments->filter(fn (MaoUserBarangay $a): bool => $a->sitio && strcasecmp($a->sitio, $sitio) === 0) : collect();
        if ($matching->isEmpty()) {
            $matching = $assignments->filter(fn (MaoUserBarangay $a): bool => $a->sitio === null);
        }

        return $matching->map(fn (MaoUserBarangay $a): self => $a->user)->unique('id')->values();
    }

    /** "Admin" for the Municipal Agriculturist, "MAO" for the other MAO personnel. */
    public function maoRoleLabel(): string
    {
        return $this->isMaoHead() ? 'Admin' : 'MAO';
    }

    /**
     * A new MAO account's e-mail: first and last name, lower case, letters only, at the configured domain
     * ("Juan Dela Cruz" → juandelacruz@gmail.com). When it is taken, 2, 3 … is added (juandelacruz2@gmail.com).
     * A middle initial ("Jean L. Laviste") is left out.
     */
    public static function suggestMaoEmail(string $fullName, ?string $domain = null, ?int $ignoreId = null): string
    {
        $parts = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $parts = array_values(array_filter($parts, fn (string $p): bool => ! preg_match('/^[A-Za-z]\.?$/', $p)));   // drop initials
        $first = $parts[0] ?? 'mao';
        $last = count($parts) > 1 ? implode('', array_slice($parts, 1)) : '';
        $clean = fn (string $v): string => preg_replace('/[^a-z]/', '', strtolower(\Illuminate\Support\Str::ascii($v)));
        $local = $clean($first).$clean($last) ?: 'mao';
        $domain = $domain ?? (string) config('iclimate.mao_email_domain', 'gmail.com');

        $email = $local.'@'.$domain;
        for ($n = 2; self::query()->where('email', $email)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $n++) {
            $email = $local.$n.'@'.$domain;
        }

        return $email;
    }

    /** The Municipal Agriculturist's account, when one is set. */
    public static function maoHead(): ?self
    {
        return \App\Support\SchemaCache::hasColumn('users', 'is_mao_head')
            ? self::query()->where('role', self::ROLE_MAO)->where('is_mao_head', true)->where('status', self::STATUS_ACTIVE)->first()
            : null;
    }

    // ------------------------------------------------------------ end panel revision 4.4

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

    /** Given names only, for greetings: a three-part name drops the last word (John Luke Reynon becomes John Luke). */
    public function greetingName(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // A family name can be two words ("Dela Cruz", "De Leon", "San Jose"): both are left out of the greeting.
        if (count($parts) >= 3 && in_array(strtolower($parts[count($parts) - 2]), ['de', 'del', 'dela', 'delos', 'la', 'los', 'san', 'santa', 'santo', 'sta.', 'sto.'], true)) {
            array_pop($parts);
            if (count($parts) >= 3 && in_array(strtolower($parts[count($parts) - 2]), ['de'], true)) {
                array_pop($parts);                                      // "De La Cruz", "De Los Santos"
            }
        }

        return match (true) {
            count($parts) >= 3 => implode(' ', array_slice($parts, 0, -1)),
            count($parts) === 2 => $parts[0],
            default => $parts[0] ?? __('farmer.farmer'),
        };
    }

    /** Public address of the profile photo, or null when the account has none. */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->avatar_path) : null;
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
            // Panel revision 4.4
            'area_covered_ha' => 'decimal:2',
            'is_mao_head' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }
}
