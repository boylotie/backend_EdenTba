<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\Content\Models\Favorite;
use App\Modules\Content\Models\ListeningHistory;
use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Models\UserDevice;
use App\Modules\Notifications\Models\UserNotificationPreference;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_active
 * @property Carbon|null $last_seen_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'is_active', 'last_seen_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('roles.name', $role)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->hasRole(Role::SUPER_ADMIN)
            || $this->roles()->whereHas('permissions', function (Builder $query) use ($permission): void {
                $query->where('permissions.name', $permission);
            })->exists();
    }

    public function assignRole(string $role): void
    {
        $this->roles()->syncWithoutDetaching([$this->resolveRoleId($role)]);
    }

    public function removeRole(string $role): void
    {
        $this->roles()->detach($this->resolveRoleId($role));
    }

    /**
     * Notifications internes de l'utilisateur (MOD-09). Nom distinct de
     * `notifications()` (trait `Notifiable`) pour éviter tout conflit.
     *
     * @return HasMany<Notification, $this>
     */
    public function userNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Appareils de l'utilisateur (tokens push — MOD-09-P2).
     *
     * @return HasMany<UserDevice, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    /**
     * Préférences de notification par type (MOD-09-P4).
     *
     * @return HasMany<UserNotificationPreference, $this>
     */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(UserNotificationPreference::class);
    }

    /**
     * Favoris de l'utilisateur (MOD-07-P5, US-034).
     *
     * @return HasMany<Favorite, $this>
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * Positions d'écoute de l'utilisateur (MOD-07-P5, US-035).
     *
     * @return HasMany<ListeningHistory, $this>
     */
    public function listeningHistory(): HasMany
    {
        return $this->hasMany(ListeningHistory::class);
    }

    /**
     * @param  string[]  $roles
     */
    public function syncRoles(array $roles): void
    {
        $this->roles()->sync(Role::whereIn('name', $roles)->pluck('id')->all());
    }

    private function resolveRoleId(string $role): int
    {
        return Role::where('name', $role)->firstOrFail()->id;
    }
}
