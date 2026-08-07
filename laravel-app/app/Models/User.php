<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use Notifiable;
    use HasRoles;
    use TwoFactorAuthenticatable;

    /**
     * Authentication guard used by Spatie roles and permissions.
     */
    protected string $guard_name = 'web';

    /**
     * Only ordinary identity fields may be mass assigned.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * Sensitive values excluded from arrays and JSON responses.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'last_login_ip',
        'failed_login_count',
        'last_failed_login_at',
        'locked_until',
    ];

    /**
     * Normalize email addresses before storage.
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string =>
                mb_strtolower(trim($value)),
        );
    }

    /**
     * Administrator who created the account.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'created_by'
        );
    }

    /**
     * Administrator who last updated the account.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'updated_by'
        );
    }

    /**
     * Determine whether the account is temporarily locked.
     */
    public function isLocked(): bool
    {
        return $this->locked_until !== null
            && $this->locked_until->isFuture();
    }

    /**
     * Determine whether the account may authenticate.
     */
    public function canAuthenticate(): bool
    {
        return (bool) $this->is_active
            && ! $this->isLocked();
    }

    /**
     * Attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'must_change_password' => 'boolean',
            'failed_login_count' => 'integer',
            'last_failed_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}