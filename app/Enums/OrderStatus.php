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
    case WaitingCourier = 'waiting_courier';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';

    public function label(string $modality = 'dining_room'): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmado', self::Preparing => 'Em preparo',
            self::Ready => $modality === 'pickup' ? 'Pronto para retirada' : 'Pronto', self::Served => 'Servido', self::Cancelled => 'Cancelado', self::Collected => 'Retirado',
            self::WaitingCourier => 'Aguardando entregador', self::Dispatched => 'Saiu para entrega', self::Delivered => 'Entregue',
        };
    }

    public function next(string $modality = 'dining_room'): ?self
    {
        return match ($this) {
            self::Confirmed => self::Preparing, self::Preparing => self::Ready,
            self::Ready => match ($modality) {
                'pickup' => self::Collected, 'delivery' => self::WaitingCourier, default => self::Served
            },
            self::WaitingCourier => self::Dispatched, self::Dispatched => self::Delivered, default => null,
        };
    }
}
