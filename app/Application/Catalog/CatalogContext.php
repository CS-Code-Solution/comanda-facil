<?php

namespace App\Application\Catalog;

use App\Application\Dining\GuestGrant;
use App\Enums\StaffPermission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class CatalogContext
{
    private function __construct(public readonly ?int $actorId, public readonly ?int $authVersion, public readonly string $source, private readonly ?GuestGrant $grant = null) {}

    public static function dining(GuestGrant $grant): self
    {
        return new self(null, null, 'qr', $grant);
    }

    public static function web(User $actor): self
    {
        return new self($actor->id, $actor->auth_version, 'web');
    }

    public static function console(): self
    {
        return new self(null, null, 'console');
    }

    public function authorize(StaffPermission $permission): void
    {
        if ($this->grant) {
            if ($permission !== StaffPermission::ViewCatalog) {
                throw new AuthorizationException;
            }
            $this->grant->authorize(write: true);

            return;
        }
        if ($this->source === 'console') {
            return;
        }

        $actor = User::query()->sharedLock()->find($this->actorId);

        if (! $actor || $actor->auth_version !== $this->authVersion || ! $actor->hasPermission($permission)) {
            throw new AuthorizationException;
        }
    }
}
