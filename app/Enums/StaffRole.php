<?php

namespace App\Enums;

enum StaffRole: string
{
    case Manager = 'gestor';
    case Cashier = 'caixa';
    case Waiter = 'garcom';
    case Kitchen = 'cozinha';
    case Courier = 'entregador';

    /** @return list<StaffPermission> */
    public function permissions(): array
    {
        return match ($this) {
            self::Manager => StaffPermission::cases(),
            self::Cashier => [StaffPermission::Cashier, StaffPermission::ViewCatalog, StaffPermission::DiningRoom, StaffPermission::CloseDiningSession, StaffPermission::AmendOrder, StaffPermission::CancelOrder, StaffPermission::ServeOrder, StaffPermission::PrintCashier, StaffPermission::ManageCashShift, StaffPermission::ReceivePayment, StaffPermission::ViewPickup, StaffPermission::PlacePickupOrder, StaffPermission::CollectPickup],
            self::Waiter => [StaffPermission::ReceivePayment, StaffPermission::DiningRoom, StaffPermission::ViewCatalog, StaffPermission::PlaceDiningOrder, StaffPermission::AttendDiningCalls, StaffPermission::CloseDiningSession, StaffPermission::AmendOrder, StaffPermission::CancelOrder, StaffPermission::ServeOrder, StaffPermission::ViewPickup],
            self::Kitchen => [StaffPermission::Kitchen, StaffPermission::ViewCatalog, StaffPermission::PrepareOrder, StaffPermission::PrintKitchen],
            self::Courier => [StaffPermission::Delivery, StaffPermission::ViewCatalog],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Gestor',
            self::Cashier => 'Caixa',
            self::Waiter => 'Garçom',
            self::Kitchen => 'Cozinha',
            self::Courier => 'Entregador',
        };
    }
}
