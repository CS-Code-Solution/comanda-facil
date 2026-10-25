<?php

namespace App\Models;

use App\Enums\StaffPermission;
use App\Enums\StaffRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $username
 * @property string|null $pin_hash
 * @property int $auth_version
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $role
 * @property bool $is_active
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'pin_hash', 'auth_version', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function isActiveStaff(): bool
    {
        return $this->is_active
            && $this->username !== null
            && $this->pin_hash !== null
            && StaffRole::tryFrom($this->role ?? '') !== null;
    }

    public function hasPermission(StaffPermission $permission): bool
    {
        return $this->isActiveStaff()
            && in_array($permission, StaffRole::tryFrom($this->role ?? '')?->permissions() ?? [], true);
    }

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
            'pin_hash' => 'hashed',
            'auth_version' => 'integer',
            'is_active' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
