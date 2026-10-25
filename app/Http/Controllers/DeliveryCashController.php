<?php

namespace App\Http\Controllers;

use App\Application\Cash\AccountBalance;
use App\Application\Cash\BillingAccounts;
use App\Application\Cash\CashPayments;
use App\Application\Delivery\DeliveryAccess;
use App\Application\Dining\DiningContext;
use App\Enums\StaffPermission;
use App\Http\Presenters\CashViews;
use App\Models\CashPayment;
use App\Models\DeliveryAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryCashController extends Controller
{
    private function read(Request $request, DeliveryAccount $session): void
    {
        if (! $request->user()->hasPermission(StaffPermission::Cashier)) {
            DeliveryAccess::courier($session->orders()->sole(), DiningContext::staff($request->user()));
        }
    }

    public function account(Request $request, DeliveryAccount $session, AccountBalance $balances, CashController $cash): Response
    {
        $this->read($request, $session);

        return $cash->renderAccount($request, $session, $balances);
    }

    public function preview(Request $request, DeliveryAccount $session, CashPayments $payments, CashController $cash): RedirectResponse
    {
        return $cash->previewAccount($request, $session, $payments);
    }

    public function review(Request $request, DeliveryAccount $session, string $intent, CashPayments $payments, CashController $cash): Response
    {
        return $cash->reviewAccount($request, $session, $intent, $payments);
    }

    public function confirm(Request $request, DeliveryAccount $session, string $intent, CashPayments $payments, CashController $cash): RedirectResponse
    {
        return $cash->confirmAccount($request, $session, $intent, $payments);
    }

    public function receipt(Request $request, DeliveryAccount $session): Response
    {
        $this->read($request, $session);
        $payment = CashPayment::query()->where('delivery_account_id', $session->id)->firstOrFail();

        return Inertia::render('cash/receipt', ['payment' => CashViews::payment($payment), 'table' => null, 'locationLabel' => BillingAccounts::label($session),
            'accountId' => $session->id, 'backUrl' => BillingAccounts::route($session)]);
    }
}
