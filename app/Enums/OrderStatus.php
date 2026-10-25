<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Served = 'served';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmado', self::Preparing => 'Em preparo',
            self::Ready => 'Pronto', self::Served => 'Servido', self::Cancelled => 'Cancelado',
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::Confirmed => self::Preparing, self::Preparing => self::Ready,
            self::Ready => self::Served, default => null,
        };
    }
}
