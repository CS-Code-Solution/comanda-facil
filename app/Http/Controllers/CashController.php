<?php

namespace App\Http\Controllers;

use App\Application\Cash\AccountBalance;
use App\Application\Cash\CashMoney;
use App\Application\Cash\CashPayments;
use App\Application\Cash\CashShifts;
use App\Application\Dining\DiningContext;
use App\Domain\Catalog\Money;
use App\Enums\PaymentMethod;
use App\Enums\StaffPermission;
use App\Http\Presenters\CashViews;
use App\Models\CashPayment;
use App\Models\CashShift;
use App\Models\DiningSession;
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
        $pending = CashPayment::query()->where('has_pending_refund', true)->orderBy('id')->paginate(10, ['*'], 'pending_page')->withQueryString();
        $history = CashShift::query()->orderByDesc('id')->paginate(10, ['*'], 'shifts_page')->withQueryString();

        return Inertia::render('cash/index', ['shift' => $shift ? CashViews::shift($shift) : null, 'openUrl' => route('cash.open'), 'operationId' => (string) Str::uuid(),
            'accounts' => $sessions->getCollection()->map(fn ($session) => ['id' => $session->id, 'table' => $session->table->number,
                'settled' => $session->settled_at !== null, 'url' => route('cash.account', $session->id)]),
            'pending' => $pending->getCollection()->map(fn ($payment) => ['reference' => $payment->reference, 'session' => $payment->session_id, 'url' => route('cash.account', $payment->session_id)]),
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
                'amount' => CashMoney::label($payment->amount_cents), 'url' => route('cash.account', $payment->session_id)]),
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
        $balance = $balances->forSession($session);
        $payment = $balance['payment'];
        $shift = CashShift::query()->whereNull('closed_at')->first();
        $orders = $session->orders()->orderBy('id')->get();

        return Inertia::render('cash/account', ['account' => ['id' => $session->id, 'table' => $session->table->number,
            'closed' => $session->closed_at !== null, 'settled' => $session->settled_at !== null, 'legacy' => $balance['legacy'],
            'quote' => CashViews::quote($balance['quote']), 'refundDue' => CashMoney::label($balance['refund_due_cents']),
            'refunded' => CashMoney::label($balance['refunded_cents']), 'hasRefundDue' => $balance['refund_due_cents'] > 0,
            'closeUrl' => ! $session->closed_at && $balance['refund_due_cents'] === 0 && ($session->settled_at || $balance['total_cents'] === 0)
                ? route('dining.close', ['table' => $session->table_id, 'sessionId' => $session->id]) : null],
            'orders' => $orders->map(fn ($order) => ['reference' => $order->public_id, 'url' => route('operations.show', $order)]),
            'canPay' => $shift !== null && ! $session->closed_at && ! $session->settled_at && ! $payment && $balance['quote']['lines'] !== [],
            'hasShift' => $shift !== null, 'canDiscount' => $request->user()->hasPermission(StaffPermission::AccountDiscount),
            'previewUrl' => route('cash.preview', $session), 'payment' => $payment ? CashViews::payment($payment) : null,
            'canRefund' => $shift !== null && $balance['refund_due_cents'] > 0 && $request->user()->hasPermission(StaffPermission::RefundPayment),
            'refundDefault' => Money::decimal($balance['refund_due_cents']), 'refundOperation' => (string) Str::uuid(),
            'refundUrl' => $payment ? route('cash.refund', $payment) : null,
            'refunds' => $payment ? DB::table('cash_refunds')->where('payment_id', $payment->id)->orderBy('id')->get()->map(fn ($row) => [
                'reference' => $row->reference, 'shift' => $row->shift_id, 'method' => PaymentMethod::from($row->method)->label(),
                'amount' => CashMoney::label($row->amount_cents), 'reason' => $row->reason, 'createdAt' => CarbonImmutable::parse($row->created_at)->toIso8601String()]) : []]);
    }

    public function preview(Request $request, DiningSession $session, CashPayments $payments): RedirectResponse
    {
        $intent = $payments->preview($session->id, $request->all(), DiningContext::staff($request->user()));

        return to_route('cash.review', ['session' => $session->id, 'intent' => $intent]);
    }

    public function review(Request $request, DiningSession $session, string $intent, CashPayments $payments): Response
    {
        $record = $payments->intent($session->id, $intent, DiningContext::staff($request->user()));
        $snapshot = json_decode($record->snapshot, true, flags: JSON_THROW_ON_ERROR);

        return Inertia::render('cash/review', ['quote' => CashViews::quote($snapshot), 'accountId' => $session->id, 'table' => $session->table->number,
            'expiresAt' => CarbonImmutable::parse($record->expires_at)->toIso8601String(), 'expired' => now()->greaterThanOrEqualTo($record->expires_at),
            'zero' => $snapshot['total_cents'] === 0, 'receivedDefault' => Money::decimal($snapshot['total_cents']),
            'confirmUrl' => route('cash.confirm', ['session' => $session->id, 'intent' => $intent]), 'backUrl' => route('cash.account', $session)]);
    }

    public function confirm(Request $request, DiningSession $session, string $intent, CashPayments $payments): RedirectResponse
    {
        $payments->confirm($session->id, $intent, $request->all(), DiningContext::staff($request->user()));

        return to_route('cash.account', $session);
    }

    public function refund(Request $request, CashPayment $payment, CashPayments $payments): RedirectResponse
    {
        $payments->refund($payment->id, $request->all(), DiningContext::staff($request->user()));

        return to_route('cash.account', $payment->session_id);
    }

    public function receipt(CashPayment $payment): Response
    {
        $session = DiningSession::query()->findOrFail($payment->session_id);

        return Inertia::render('cash/receipt', ['payment' => CashViews::payment($payment), 'table' => $session->table->number,
            'accountId' => $session->id, 'backUrl' => route('cash.account', $session)]);
    }
}
