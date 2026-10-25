<?php

namespace App\Http\Controllers;

use App\Application\Cash\AccountBalance;
use App\Application\Cash\BillingAccounts;
use App\Application\Cash\CashMoney;
use App\Application\Cash\CashPayments;
use App\Application\Cash\CashShifts;
use App\Application\Delivery\DeliveryAccess;
use App\Application\Dining\DiningContext;
use App\Domain\Catalog\Money;
use App\Enums\PaymentMethod;
use App\Enums\StaffPermission;
use App\Http\Presenters\CashViews;
use App\Models\CashPayment;
use App\Models\CashShift;
use App\Models\DeliveryAccount;
use App\Models\DiningSession;
use App\Models\PickupAccount;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CashController extends Controller
{
    public function index(): Response
    {
        $shift = CashShift::query()->whereNull('closed_at')->first();
        $sessions = DiningSession::query()->with('table')->whereNull('closed_at')->orderBy('opened_at')->paginate(20, ['*'], 'accounts_page')->withQueryString();
        $pickup = PickupAccount::query()->whereNull('closed_at')->orderBy('opened_at')->paginate(20, ['*'], 'pickup_page')->withQueryString();
        $deliveries = DeliveryAccount::query()->whereNull('closed_at')->orderBy('opened_at')->paginate(20, ['*'], 'delivery_page')->withQueryString();
        $pending = CashPayment::query()->where('has_pending_refund', true)->orderBy('id')->paginate(10, ['*'], 'pending_page')->withQueryString();
        $history = CashShift::query()->orderByDesc('id')->paginate(10, ['*'], 'shifts_page')->withQueryString();

        return Inertia::render('cash/index', ['shift' => $shift ? CashViews::shift($shift) : null, 'openUrl' => route('cash.open'), 'operationId' => (string) Str::uuid(),
            'accounts' => $sessions->getCollection()->map(fn ($session) => ['id' => $session->id, 'table' => $session->table->number, 'locationLabel' => BillingAccounts::label($session),
                'settled' => $session->settled_at !== null, 'url' => route('cash.account', $session->id)]),
            'pickupAccounts' => $pickup->getCollection()->map(fn ($account) => ['id' => $account->id, 'label' => BillingAccounts::label($account), 'settled' => $account->settled_at !== null, 'url' => BillingAccounts::route($account)]),
            'pickupLinks' => ['previous' => $pickup->previousPageUrl(), 'next' => $pickup->nextPageUrl()],
            'deliveryAccounts' => $deliveries->getCollection()->map(fn ($account) => ['id' => $account->id, 'label' => BillingAccounts::label($account), 'settled' => $account->settled_at !== null, 'url' => BillingAccounts::route($account)]),
            'deliveryLinks' => ['previous' => $deliveries->previousPageUrl(), 'next' => $deliveries->nextPageUrl()],
            'pending' => $pending->getCollection()->map(fn ($payment) => ['reference' => $payment->reference, 'session' => $payment->delivery_account_id ?? $payment->session_id ?? $payment->pickup_account_id, 'label' => BillingAccounts::label(BillingAccounts::forPayment($payment)), 'url' => BillingAccounts::route(BillingAccounts::forPayment($payment))]),
            'shifts' => $history->getCollection()->map(fn ($row) => ['id' => $row->id, 'openedAt' => $row->opened_at->toIso8601String(), 'closed' => $row->closed_at !== null, 'url' => route('cash.shift', $row)]),
            'accountLinks' => ['previous' => $sessions->previousPageUrl(), 'next' => $sessions->nextPageUrl()],
            'pendingLinks' => ['previous' => $pending->previousPageUrl(), 'next' => $pending->nextPageUrl()],
            'shiftLinks' => ['previous' => $history->previousPageUrl(), 'next' => $history->nextPageUrl()]]);
    }

    public function open(Request $request, CashShifts $shifts): RedirectResponse
    {
        $shift = $shifts->open($request->all(), DiningContext::staff($request->user()));

        return to_route('cash.shift', $shift);
    }

    public function shift(CashShift $shift): Response
    {
        $payments = CashPayment::query()->where('shift_id', $shift->id)->orderByDesc('id')->paginate(20, ['*'], 'payments_page')->withQueryString();
        $refunds = DB::table('cash_refunds')->where('shift_id', $shift->id)->orderByDesc('id')->paginate(20, ['*'], 'refunds_page')->withQueryString();

        return Inertia::render('cash/shift', ['shift' => CashViews::shift($shift), 'operationId' => (string) Str::uuid(),
            'closeUrl' => route('cash.close', $shift), 'refreshUrl' => route('cash.shift', $shift),
            'payments' => $payments->getCollection()->map(fn ($payment) => ['reference' => $payment->reference, 'method' => PaymentMethod::from($payment->method)->label(),
                'amount' => CashMoney::label($payment->amount_cents), 'url' => BillingAccounts::route(BillingAccounts::forPayment($payment))]),
            'refunds' => $refunds->getCollection()->map(fn ($refund) => ['reference' => $refund->reference, 'paymentId' => $refund->payment_id,
                'method' => PaymentMethod::from($refund->method)->label(), 'amount' => CashMoney::label($refund->amount_cents), 'reason' => $refund->reason]),
            'paymentLinks' => ['previous' => $payments->previousPageUrl(), 'next' => $payments->nextPageUrl()],
            'refundLinks' => ['previous' => $refunds->previousPageUrl(), 'next' => $refunds->nextPageUrl()]]);
    }

    public function close(Request $request, CashShift $shift, CashShifts $shifts): RedirectResponse
    {
        $shifts->close($shift->id, $request->all(), DiningContext::staff($request->user()));

        return to_route('cash.shift', $shift);
    }

    public function account(Request $request, DiningSession $session, AccountBalance $balances): Response
    {
        return $this->renderAccount($request, $session, $balances);
    }

    public function renderAccount(Request $request, DiningSession|PickupAccount|DeliveryAccount $session, AccountBalance $balances): Response
    {
        $cashier = $request->user()->hasPermission(StaffPermission::Cashier);
        $backUrl = route('cash.index');
        if (! $cashier) {
            if ($session instanceof DeliveryAccount) {
                DeliveryAccess::courier($session->orders()->sole(), DiningContext::staff($request->user()));
                $backUrl = route('delivery.show', $session->orders()->sole());
            } else {
                abort_unless($session instanceof DiningSession, 403);
                $backUrl = route('dining.show', $session->table_id);
            }
        }
        $balance = $balances->forAccount($session);
        $payment = $balance['payment'];
        $shift = CashShift::query()->whereNull('closed_at')->first();
        $orders = $session->orders()->orderBy('id')->get();

        return Inertia::render('cash/account', ['account' => ['id' => $session->id, 'table' => $session instanceof DiningSession ? $session->table->number : null, 'locationLabel' => BillingAccounts::label($session),
            'closed' => $session->closed_at !== null, 'settled' => $session->settled_at !== null, 'legacy' => $balance['legacy'],
            'quote' => CashViews::quote($balance['quote']), 'refundDue' => CashMoney::label($balance['refund_due_cents']),
            'refunded' => CashMoney::label($balance['refunded_cents']), 'hasRefundDue' => $balance['refund_due_cents'] > 0,
            'closeUrl' => $session instanceof DiningSession && $request->user()->hasPermission(StaffPermission::CloseDiningSession) && ! $session->closed_at && $balance['refund_due_cents'] === 0 && ($session->settled_at || $balance['total_cents'] === 0)
                ? route('dining.close', ['table' => $session->table_id, 'sessionId' => $session->id]) : null],
            'orders' => $orders->map(fn ($order) => ['reference' => $order->public_id, 'url' => route($session instanceof DeliveryAccount ? 'delivery.show' : 'operations.show', $order)]),
            'backUrl' => $backUrl, 'backLabel' => $cashier ? 'Voltar ao caixa' : ($session instanceof DeliveryAccount ? 'Voltar à entrega' : 'Voltar à mesa'),
            'realtimeChannel' => $cashier ? 'operations.cashier' : ($session instanceof DeliveryAccount ? 'operations.delivery' : 'operations.dining'),
            'canPay' => $shift !== null && ! $session->closed_at && ! $session->settled_at && ! $payment && $balance['quote']['lines'] !== []
                && (! $session instanceof DeliveryAccount || ($session->courier_id === $request->user()->id && $request->user()->role === 'entregador' && $orders->sole()->status === 'dispatched')),
            'hasShift' => $shift !== null, 'canDiscount' => $request->user()->hasPermission(StaffPermission::AccountDiscount),
            'previewUrl' => BillingAccounts::route($session, 'preview'), 'payment' => $payment ? CashViews::payment($payment) : null,
            'canRefund' => $shift !== null && $balance['refund_due_cents'] > 0 && $request->user()->hasPermission(StaffPermission::RefundPayment),
            'refundDefault' => Money::decimal($balance['refund_due_cents']), 'refundOperation' => (string) Str::uuid(),
            'refundUrl' => $payment ? route('cash.refund', $payment) : null,
            'refunds' => $payment ? DB::table('cash_refunds')->where('payment_id', $payment->id)->orderBy('id')->get()->map(fn ($row) => [
                'reference' => $row->reference, 'shift' => $row->shift_id, 'method' => PaymentMethod::from($row->method)->label(),
                'amount' => CashMoney::label($row->amount_cents), 'reason' => $row->reason, 'createdAt' => CarbonImmutable::parse($row->created_at)->toIso8601String()]) : []]);
    }

    public function preview(Request $request, DiningSession $session, CashPayments $payments): RedirectResponse
    {
        return $this->previewAccount($request, $session, $payments);
    }

    public function previewAccount(Request $request, DiningSession|PickupAccount|DeliveryAccount $session, CashPayments $payments): RedirectResponse
    {
        $intent = $payments->preview($session->id, $request->all(), DiningContext::staff($request->user()), BillingAccounts::kind($session));

        return redirect(BillingAccounts::route($session, 'review', $intent));
    }

    public function review(Request $request, DiningSession $session, string $intent, CashPayments $payments): Response
    {
        return $this->reviewAccount($request, $session, $intent, $payments);
    }

    public function reviewAccount(Request $request, DiningSession|PickupAccount|DeliveryAccount $session, string $intent, CashPayments $payments): Response
    {
        $record = $payments->intent($session->id, $intent, DiningContext::staff($request->user()), BillingAccounts::kind($session));
        $snapshot = json_decode($record->snapshot, true, flags: JSON_THROW_ON_ERROR);

        return Inertia::render('cash/review', ['quote' => CashViews::quote($snapshot), 'accountId' => $session->id, 'table' => $session instanceof DiningSession ? $session->table->number : null, 'locationLabel' => BillingAccounts::label($session),
            'expiresAt' => CarbonImmutable::parse($record->expires_at)->toIso8601String(), 'expired' => now()->greaterThanOrEqualTo($record->expires_at),
            'zero' => $snapshot['total_cents'] === 0, 'receivedDefault' => Money::decimal($snapshot['total_cents']),
            'confirmUrl' => BillingAccounts::route($session, 'confirm', $intent), 'backUrl' => BillingAccounts::route($session)]);
    }

    public function confirm(Request $request, DiningSession $session, string $intent, CashPayments $payments): RedirectResponse
    {
        return $this->confirmAccount($request, $session, $intent, $payments);
    }

    public function confirmAccount(Request $request, DiningSession|PickupAccount|DeliveryAccount $session, string $intent, CashPayments $payments): RedirectResponse
    {
        $payments->confirm($session->id, $intent, $request->all(), DiningContext::staff($request->user()), BillingAccounts::kind($session));

        return redirect(BillingAccounts::route($session));
    }

    public function refund(Request $request, CashPayment $payment, CashPayments $payments): RedirectResponse
    {
        $payments->refund($payment->id, $request->all(), DiningContext::staff($request->user()));

        return redirect(BillingAccounts::route(BillingAccounts::forPayment($payment)));
    }

    public function receipt(Request $request, CashPayment $payment): Response
    {
        abort_if(($payment->pickup_account_id !== null || $payment->delivery_account_id !== null) && ! $request->user()->hasPermission(StaffPermission::Cashier), 403);
        $session = BillingAccounts::forPayment($payment);

        return Inertia::render('cash/receipt', ['payment' => CashViews::payment($payment), 'table' => $session instanceof DiningSession ? $session->table->number : null, 'locationLabel' => BillingAccounts::label($session),
            'accountId' => $session->id, 'backUrl' => BillingAccounts::route($session)]);
    }
}
