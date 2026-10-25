<?php

namespace App\Application\Delivery;

use Illuminate\Auth\Access\AuthorizationException;

final readonly class DeliveryGrant
{
    public function __construct(public string $visitor, public int $expiresAt) {}

    public function clientHash(): string
    {
        return hash('sha256', $this->visitor);
    }

    public function authorize(bool $write = false): void
    {
        if ($this->expiresAt <= now()->timestamp) {
            throw new AuthorizationException('O acesso expirou. Reabra o cardápio ou procure o restaurante.');
        }
    }
}
