<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Served = 'served';
    case Cancelled = 'cancelled';
    case Collected = 'collected';

    public function label(string $modality = 'dining_room'): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmado', self::Preparing => 'Em preparo',
            self::Ready => $modality === 'pickup' ? 'Pronto para retirada' : 'Pronto', self::Served => 'Servido', self::Cancelled => 'Cancelado', self::Collected => 'Retirado',
        };
    }

    public function next(string $modality = 'dining_room'): ?self
    {
        return match ($this) {
            self::Confirmed => self::Preparing, self::Preparing => self::Ready,
            self::Ready => $modality === 'pickup' ? self::Collected : self::Served, default => null,
        };
    }
}
