<?php

namespace App\Application\Cash;

use App\Application\Dining\DiningContext;
use App\Application\Orders\OperationsChanges;
use App\Enums\StaffPermission;
use App\Models\CashPayment;
use App\Models\DiningSession;
use App\Models\PickupAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use stdClass;

class CashPayments
{
    public function __construct(private AccountPricing $pricing, private CashShifts $shifts, private AccountBalance $balance) {}

    /** @param array<string, mixed> $input */
    public function preview(int $sessionId, array $input, DiningContext $context, string $kind = 'dining_room'): string
    {
        $data = Validator::make($input, ['discount' => 'nullable|array:type,value,reason', 'discount.type' => 'required_with:discount|in:percentage,fixed',
            'discount.value' => 'required_with:discount|string', 'discount.reason' => 'required_with:discount|string|min:3|max:500'])->validate();

        return DB::transaction(function () use ($sessionId, $data, $context, $kind): string {
            CashOperations::lock();
            $this->authorizeReception($context, $kind);
            $session = BillingAccounts::lock($sessionId, $kind);
            $this->unpaid($session);
            $shift = $this->shifts->active();
            $policy = null;
            if (! empty($data['discount'])) {
                $context->authorize(StaffPermission::AccountDiscount);
                $discount = $data['discount'];
                $value = CashMoney::parse($discount['value'], 'discount.value');
                if ($value < 1 || ($discount['type'] === 'percentage' && $value > 10000)) {
                    throw ValidationException::withMessages(['discount.value' => 'Informe desconto positivo; percentual máximo de 100%.']);
                }
                $policy = ['type' => $discount['type'], 'value' => $value, 'reason' => $discount['reason']];
            }
            $quote = $this->pricing->quote($session, $policy);
            if ($quote['lines'] === [] || $quote['total_cents'] > 100000000) {
                throw ValidationException::withMessages(['cash' => 'A conta deve ter itens ativos e valor dentro do limite de registro. Conta cancelada de valor zero pode ser encerrada no salão.']);
            }
            if ($policy && $policy['type'] === 'fixed' && $policy['value'] > $quote['gross_cents']) {
                throw ValidationException::withMessages(['discount.value' => 'O desconto fixo não pode ultrapassar o valor bruto da conta.']);
            }
            $id = (string) Str::uuid();
            DB::table('cash_payment_intents')->insert(['id' => $id, BillingAccounts::column($kind) => $sessionId, 'shift_id' => $shift->id,
                'actor_id' => $context->actorId, 'auth_version' => $context->authVersion, 'fingerprint' => $this->pricing->fingerprint($quote),
                'snapshot' => json_encode($quote, JSON_THROW_ON_ERROR), 'created_at' => now(), 'expires_at' => now()->addMinutes(10)]);

            return $id;
        });
    }

    public function intent(int $sessionId, string $id, DiningContext $context, string $kind = 'dining_room'): stdClass
    {
        $this->authorizeReception($context, $kind);
        $intent = DB::table('cash_payment_intents')->where('id', $id)->where(BillingAccounts::column($kind), $sessionId)->where('actor_id', $context->actorId)->where('auth_version', $context->authVersion)->first();
        abort_unless($intent !== null, 404);

        return $intent;
    }

    /** @param array<string, mixed> $input */
    public function confirm(int $sessionId, string $id, array $input, DiningContext $context, string $kind = 'dining_room'): CashPayment
    {
        $data = Validator::make($input, ['method' => 'required|in:cash,pix,card',
            'received' => 'required_if:method,cash|prohibited_unless:method,cash|nullable|string',
            'external_reference' => 'nullable|string|max:100'])->validate();
        $received = $data['method'] === 'cash' ? CashMoney::parse($data['received'], 'received') : null;
        $external = $data['external_reference'] ?? null;

        return DB::transaction(function () use ($sessionId, $id, $data, $received, $external, $context, $kind): CashPayment {
            CashOperations::lock();
            $this->authorizeReception($context, $kind);
            $session = BillingAccounts::lock($sessionId, $kind);
            $intent = $this->intent($sessionId, $id, $context, $kind);
            $snapshot = json_decode($intent->snapshot, true, flags: JSON_THROW_ON_ERROR);
            if ($snapshot['policy']) {
                $context->authorize(StaffPermission::AccountDiscount);
            }
            $fingerprint = CashOperations::fingerprint('pay', ['intent' => $id, 'method' => $data['method'], 'received' => $received, 'reference' => $external]);
            if ($result = CashOperations::replay($id, $fingerprint, $context)) {
                return CashPayment::query()->findOrFail($result);
            }
            $this->unpaid($session);
            $shift = $this->shifts->active();
            if ($shift->id !== $intent->shift_id || now()->greaterThanOrEqualTo($intent->expires_at)) {
                throw ValidationException::withMessages(['cash' => 'A prévia expirou ou o turno mudou. Confira uma nova prévia.']);
            }
            $current = $this->pricing->quote($session, $snapshot['policy']);
            if (! hash_equals($intent->fingerprint, $this->pricing->fingerprint($current))) {
                throw ValidationException::withMessages(['cash' => 'A conta mudou. Faça uma nova prévia antes de registrar o pagamento.']);
            }
            $amount = $current['total_cents'];
            if ($data['method'] === 'cash' && $received < $amount) {
                throw ValidationException::withMessages(['received' => 'O dinheiro recebido precisa cobrir a conta integral.']);
            }
            if ($amount === 0 && ($data['method'] !== 'cash' || $received !== 0)) {
                throw ValidationException::withMessages(['method' => 'Conta zerada: registre quitação sem valor, com recebido zero.']);
            }
            $payment = new CashPayment;
            $payment->forceFill(['reference' => (string) Str::uuid(), 'intent_id' => $id, BillingAccounts::column($kind) => $sessionId,
                'shift_id' => $shift->id, 'actor_id' => $context->actorId, 'method' => $data['method'], 'amount_cents' => $amount,
                'received_cents' => $received, 'change_cents' => $received !== null ? $received - $amount : 0,
                'external_reference' => $external, 'snapshot' => $current, 'created_at' => now()])->save();
            $session->forceFill(['settled_at' => now()])->save();
            $shift->increment('version');
            CashOperations::remember($id, 'pay', $fingerprint, $payment->id, $context);
            CashOperations::audit('payment_recorded', $shift->id, $context, ['payment_id' => $payment->id, BillingAccounts::column($kind) => $sessionId,
                'method' => $data['method'], 'amount_cents' => $amount, 'change_cents' => $payment->change_cents,
                'discount_policy' => $current['policy'], 'discount_cents' => $current['discount_cents']]);
            OperationsChanges::record();

            return $payment;
        });
    }

    /** @param array<string, mixed> $input */
    public function refund(int $paymentId, array $input, DiningContext $context): int
    {
        $data = Validator::make($input, ['operation_id' => 'required|uuid', 'method' => 'required|in:cash,pix,card',
            'amount' => 'required|string', 'reason' => 'required|string|min:3|max:500', 'performed' => 'required|accepted',
            'external_reference' => 'nullable|string|max:100'])->validate();
        $amount = CashMoney::parse($data['amount'], 'amount');
        if ($amount < 1) {
            throw ValidationException::withMessages(['amount' => 'A devolução precisa ter valor positivo.']);
        }

        return DB::transaction(function () use ($paymentId, $data, $amount, $context): int {
            CashOperations::lock();
            $context->authorize(StaffPermission::RefundPayment);
            $fingerprint = CashOperations::fingerprint('refund', ['payment' => $paymentId, 'method' => $data['method'], 'amount' => $amount,
                'reason' => $data['reason'], 'reference' => $data['external_reference'] ?? null]);
            if ($result = CashOperations::replay($data['operation_id'], $fingerprint, $context)) {
                return $result;
            }
            $payment = CashPayment::query()->findOrFail($paymentId);
            $account = BillingAccounts::forPayment($payment);
            $session = BillingAccounts::lock($account->id, BillingAccounts::kind($account));
            $shift = $this->shifts->active();
            $balance = $this->balance->forAccount($session);
            if ($amount > $balance['refund_due_cents']) {
                throw ValidationException::withMessages(['amount' => 'A devolução ultrapassa o saldo pendente desta conta. Recarregue os valores.']);
            }
            $id = DB::table('cash_refunds')->insertGetId(['reference' => (string) Str::uuid(), 'payment_id' => $paymentId,
                'shift_id' => $shift->id, 'actor_id' => $context->actorId, 'method' => $data['method'], 'amount_cents' => $amount,
                'reason' => $data['reason'], 'external_reference' => $data['external_reference'] ?? null, 'created_at' => now()]);
            $shift->increment('version');
            CashOperations::remember($data['operation_id'], 'refund', $fingerprint, $id, $context);
            $this->balance->refreshRefundFlag($session);
            CashOperations::audit('refund_recorded', $shift->id, $context, ['refund_id' => $id, 'payment_id' => $paymentId,
                'method' => $data['method'], 'amount_cents' => $amount, 'reason' => $data['reason'], 'refund_due_before' => $balance['refund_due_cents']]);
            OperationsChanges::record();

            return $id;
        });
    }

    private function authorizeReception(DiningContext $context, string $kind): void
    {
        $context->authorize(StaffPermission::ReceivePayment);
        if ($kind !== 'dining_room') {
            $context->authorize(StaffPermission::Cashier);
        }
    }

    private function unpaid(DiningSession|PickupAccount $session): void
    {
        if ($session->closed_at || $session->settled_at || CashPayment::query()->where(BillingAccounts::column(BillingAccounts::kind($session)), $session->id)->exists()) {
            throw ValidationException::withMessages(['cash' => 'A conta já foi quitada ou encerrada. Consulte o registro existente.']);
        }
    }
}
