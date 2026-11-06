<?php

namespace App\Models;

use App\Support\OrgRoles;
use App\Support\TenantContext;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements PasskeyUser
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use HasRoles;
    use HasTeams {
        HasTeams::teams insteadof HasRoles;
        HasRoles::teams as spatieTeamsScope;
    }
    use Notifiable;
    use PasskeyAuthenticatable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'consent_records',
        'erased_at',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
        'profile_photo_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected $casts = [
        'consent_records' => 'array',
        'erased_at' => 'datetime',
    ];

    /** @return BelongsToMany<AgentDeployment, $this> */
    public function favouriteAgents(): BelongsToMany
    {
        return $this->belongsToMany(AgentDeployment::class, 'agent_favourites')->withTimestamps();
    }

    public function organizations()
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot(['role', 'department', 'job_title', 'is_primary', 'joined_at'])
            ->withTimestamps();
    }

    public function currentOrganization(): ?Organization
    {
        return $this->organizations()->wherePivot('is_primary', true)->first()
            ?? $this->organizations()->first();
    }

    /** The role held in a business, in the seven-role vocabulary. Null when the person is not a member. */
    public function roleInOrganization(?int $organizationId = null): ?string
    {
        $organizationId ??= TenantContext::id() ?? $this->currentOrganization()?->id;

        return OrgRoles::normalise($this->organizations()->where('organizations.id', $organizationId)->first()?->pivot->role);
    }

    public function canInOrganization(string $capability, ?int $organizationId = null): bool
    {
        return OrgRoles::allows($this->roleInOrganization($organizationId), $capability);
    }

    public function platformNotifications()
    {
        return $this->hasMany(PlatformNotification::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function unreadNotificationsCount(): int
    {
        return $this->platformNotifications()->whereNull('read_at')->count();
    }

    public function pendingApprovals()
    {
        return AgentApproval::where('requested_from', $this->id)
            ->where('status', 'pending')
            ->get();
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'profile_photo_url',
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
        ];
    }
}
