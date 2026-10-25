<?php

namespace App\Application\Dining;

use App\Application\Catalog\CatalogContext;
use App\Enums\StaffPermission;
use App\Models\DiningTable;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class DiningContext
{
    private function __construct(public ?int $actorId, public ?int $authVersion, public ?GuestGrant $grant) {}

    public static function staff(User $user): self
    {
        return new self($user->id, $user->auth_version, null);
    }

    public static function guest(GuestGrant $grant): self
    {
        return new self(null, null, $grant);
    }

    public function source(): string
    {
        return $this->grant ? 'qr' : 'staff';
    }

    public function authorize(StaffPermission $permission, ?DiningTable $table = null, bool $write = false): void
    {
        if ($this->grant) {
            if ($permission !== StaffPermission::PlaceDiningOrder) {
                throw new AuthorizationException;
            }
            $this->grant->authorize($table, $write);

            return;
        }
        $user = User::query()->sharedLock()->find($this->actorId);
        if (! $user || $user->auth_version !== $this->authVersion || ! $user->hasPermission($permission)) {
            throw new AuthorizationException;
        }
    }

    public function catalog(): CatalogContext
    {
        if ($this->grant) {
            return CatalogContext::dining($this->grant);
        }
        $user = User::query()->findOrFail($this->actorId);
        if ($user->auth_version !== $this->authVersion) {
            throw new AuthorizationException;
        }

        return CatalogContext::web($user);
    }
}
