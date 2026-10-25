<?php

namespace App\Application\Staff;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

final class StaffAccessContext
{
    private function __construct(
        public readonly ?int $actorId,
        public readonly string $source,
        public readonly ?int $authVersion,
        #[\SensitiveParameter] private readonly ?string $currentPin,
    ) {}

    public static function console(): self
    {
        return new self(null, 'console', null, null);
    }

    public static function web(User $actor, #[\SensitiveParameter] string $currentPin): self
    {
        return new self($actor->id, 'web', $actor->auth_version, $currentPin);
    }

    public function verifies(User $actor): bool
    {
        return $actor->pin_hash !== null
            && $this->currentPin !== null
            && Hash::check($this->currentPin, $actor->pin_hash);
    }
}
