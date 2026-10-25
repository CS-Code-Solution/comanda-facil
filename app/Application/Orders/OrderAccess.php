<?php

namespace App\Application\Orders;

use App\Application\Cash\BillingAccounts;
use App\Models\DiningOrder;

class OrderAccess
{
    public static function lock(int $id): DiningOrder
    {
        $order = DiningOrder::query()->findOrFail($id);
        $account = BillingAccounts::forOrder($order);
        BillingAccounts::lock($account->id, BillingAccounts::kind($account));

        return DiningOrder::query()->lockForUpdate()->findOrFail($id);
    }
}
