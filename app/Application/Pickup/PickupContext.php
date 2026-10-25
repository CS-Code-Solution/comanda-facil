<?php

namespace App\Application\Pickup;

use App\Application\Catalog\CatalogContext;
use App\Application\Dining\DiningContext;
use App\Enums\StaffPermission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class PickupContext
{
    private function __construct(public ?int $actorId, public ?int $authVersion, public ?PickupGrant $grant, private ?DiningContext $staff) {}

    public static function staff(User $user): self
    {
        return new self($user->id, $user->auth_version, null, DiningContext::staff($user));
    }

    public static function guest(PickupGrant $grant): self
    {
        return new self(null, null, $grant, null);
    }

    public function authorize(StaffPermission $permission): void
    {
        if ($this->staff) {
            $this->staff->authorize($permission);

            return;
        }
        if ($permission !== StaffPermission::PlacePickupOrder || ! $this->grant) {
            throw new AuthorizationException;
        }
        $this->grant->authorize(true);
    }

    public function catalog(): CatalogContext
    {
        return $this->staff ? $this->staff->catalog() : CatalogContext::pickup($this->grant ?? throw new AuthorizationException);
    }

    public function source(): string
    {
        return $this->grant ? 'web' : 'staff';
    }
}
