<?php

namespace App\Enums;

enum StaffPermission: string
{
    case ManageStaff = 'staff.manage';
    case Administration = 'access.administration';
    case Cashier = 'access.cashier';
    case DiningRoom = 'access.dining-room';
    case Kitchen = 'access.kitchen';
    case Delivery = 'access.delivery';
    case ViewCatalog = 'catalog.view';
    case ManageCatalog = 'catalog.manage';
    case ManualDiscount = 'catalog.manual-discount';
    case ManageDining = 'dining.manage';
    case PlaceDiningOrder = 'dining.order';
    case AttendDiningCalls = 'dining.calls';
    case CloseDiningSession = 'dining.close';
    case PrepareOrder = 'orders.prepare';
    case ServeOrder = 'orders.serve';
    case AmendOrder = 'orders.amend';
    case CancelOrder = 'orders.cancel';
    case CancelPreparedOrder = 'orders.cancel-prepared';
    case PrintKitchen = 'printing.kitchen';
    case PrintCashier = 'printing.cashier';
    case ManageCashShift = 'cash.shift';
    case ReceivePayment = 'cash.receive';
    case RefundPayment = 'cash.refund';
    case AccountDiscount = 'cash.discount';
    case ViewPickup = 'pickup.view';
    case ManagePickup = 'pickup.manage';
    case PlacePickupOrder = 'pickup.order';
    case CollectPickup = 'pickup.collect';

    public function label(): string
    {
        return match ($this) {
            self::ManageStaff => 'Gerenciar equipe',
            self::Administration => 'Administração',
            self::Cashier => 'Caixa',
            self::DiningRoom => 'Salão',
            self::Kitchen => 'Cozinha',
            self::Delivery => 'Entregas',
            self::ViewCatalog => 'Catálogo',
            self::ManageCatalog => 'Administrar catálogo',
            self::ManualDiscount => 'Desconto manual',
            self::ManageDining => 'Configurar salão e mesas',
            self::PlaceDiningOrder => 'Lançar pedido no salão',
            self::AttendDiningCalls => 'Atender chamados',
            self::CloseDiningSession => 'Encerrar atendimento',
            self::PrepareOrder => 'Preparar pedidos',
            self::ServeOrder => 'Servir pedidos',
            self::AmendOrder => 'Corrigir pedidos',
            self::CancelOrder => 'Cancelar antes do preparo',
            self::CancelPreparedOrder => 'Cancelar após início do preparo',
            self::PrintKitchen => 'Imprimir na cozinha',
            self::PrintCashier => 'Imprimir no caixa',
            self::ManageCashShift => 'Abrir e fechar turno de caixa',
            self::ReceivePayment => 'Registrar pagamento de conta',
            self::RefundPayment => 'Registrar devolução',
            self::AccountDiscount => 'Desconto no fechamento',
            self::ViewPickup => 'Retiradas',
            self::ManagePickup => 'Configurar recebimento de retirada',
            self::PlacePickupOrder => 'Lançar pedido de retirada',
            self::CollectPickup => 'Registrar retirada',
        };
    }
}
