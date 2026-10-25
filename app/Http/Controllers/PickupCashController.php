<?php

namespace App\Http\Controllers;

use App\Application\Cash\AccountBalance;
use App\Application\Cash\CashPayments;
use App\Models\PickupAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class PickupCashController extends Controller
{
    public function account(Request $request, PickupAccount $session, AccountBalance $balances, CashController $cash): Response
    {
        return $cash->renderAccount($request, $session, $balances);
    }

    public function preview(Request $request, PickupAccount $session, CashPayments $payments, CashController $cash): RedirectResponse
    {
        return $cash->previewAccount($request, $session, $payments);
    }

    public function review(Request $request, PickupAccount $session, string $intent, CashPayments $payments, CashController $cash): Response
    {
        return $cash->reviewAccount($request, $session, $intent, $payments);
    }

    public function confirm(Request $request, PickupAccount $session, string $intent, CashPayments $payments, CashController $cash): RedirectResponse
    {
        return $cash->confirmAccount($request, $session, $intent, $payments);
    }
}
