<?php

namespace App\Application\Cash;

use App\Application\Dining\DiningContext;
use App\Application\Orders\OperationsChanges;
use App\Application\Reports\ShiftReportData;
use App\Enums\PaymentMethod;
use App\Enums\StaffPermission;
use App\Models\CashShift;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CashShifts
{
    public function __construct(private CashReconciliation $reconciliation, private ShiftReportData $reports) {}

    public function active(): CashShift
    {
        $shift = CashShift::query()->whereNull('closed_at')->first();
        if (! $shift) {
            throw ValidationException::withMessages(['cash' => 'Abra um turno de caixa antes de registrar recebimentos ou devoluções.']);
        }

        return $shift;
    }

    /** @param array<string, mixed> $input */
    public function open(array $input, DiningContext $context): CashShift
    {
        $data = Validator::make($input, ['operation_id' => 'required|uuid', 'opening' => 'required|string'])->validate();
        $opening = CashMoney::parse($data['opening'], 'opening');

        return DB::transaction(function () use ($data, $opening, $context): CashShift {
            CashOperations::lock();
            $context->authorize(StaffPermission::ManageCashShift);
            $fingerprint = CashOperations::fingerprint('open', ['opening' => $opening]);
            if ($id = CashOperations::replay($data['operation_id'], $fingerprint, $context)) {
                return CashShift::query()->findOrFail($id);
            }
            if (CashShift::query()->whereNull('closed_at')->exists()) {
                throw ValidationException::withMessages(['cash' => 'Já existe um turno aberto para o restaurante.']);
            }
            $shift = new CashShift;
            $shift->forceFill(['opened_by_id' => $context->actorId, 'opening_cents' => $opening, 'opened_at' => now()])->save();
            CashOperations::remember($data['operation_id'], 'open', $fingerprint, $shift->id, $context);
            CashOperations::audit('shift_opened', $shift->id, $context, ['opening_cents' => $opening]);
            OperationsChanges::record();

            return $shift;
        });
    }

    /** @param array<string, mixed> $input */
    public function close(int $id, array $input, DiningContext $context): CashShift
    {
        $data = Validator::make($input, ['operation_id' => 'required|uuid', 'version' => 'required|integer|min:1',
            'counted' => 'required|array:cash,pix,card', 'counted.cash' => 'required|string', 'counted.pix' => 'required|string',
            'counted.card' => 'required|string', 'reason' => 'nullable|string|min:3|max:500'])->validate();
        $counted = [];
        foreach (PaymentMethod::cases() as $method) {
            $counted[$method->value] = CashMoney::parse($data['counted'][$method->value], 'counted.'.$method->value, $method !== PaymentMethod::Cash);
        }

        return DB::transaction(function () use ($id, $data, $counted, $context): CashShift {
            CashOperations::lock();
            $context->authorize(StaffPermission::ManageCashShift);
            $fingerprint = CashOperations::fingerprint('close', ['shift' => $id, 'version' => (int) $data['version'], 'counted' => $counted, 'reason' => $data['reason'] ?? null]);
            if ($result = CashOperations::replay($data['operation_id'], $fingerprint, $context)) {
                return CashShift::query()->findOrFail($result);
            }
            $shift = CashShift::query()->lockForUpdate()->findOrFail($id);
            if ($shift->closed_at || $shift->version !== (int) $data['version']) {
                throw ValidationException::withMessages(['cash' => 'O turno mudou ou já foi fechado. Reabra a conferência antes de confirmar.']);
            }
            $totals = $this->reconciliation->totals($shift);
            $different = false;
            foreach ($totals as $method => &$row) {
                $row['counted'] = $counted[$method];
                $row['difference'] = $counted[$method] - $row['expected'];
                $different = $different || $row['difference'] !== 0;
            }
            unset($row);
            if ($different && empty($data['reason'])) {
                throw ValidationException::withMessages(['reason' => 'Informe o motivo das diferenças na conferência.']);
            }
            $shift->forceFill(['closed_at' => now(), 'closed_by_id' => $context->actorId, 'closing' => $totals,
                'closing_reason' => $data['reason'] ?? null, 'version' => $shift->version + 1,
                'report_snapshot' => $this->reports->build($shift->id)])->save();
            CashOperations::remember($data['operation_id'], 'close', $fingerprint, $shift->id, $context);
            CashOperations::audit('shift_closed', $shift->id, $context, ['closing' => $totals, 'reason' => $data['reason'] ?? null]);
            OperationsChanges::record();

            return $shift;
        });
    }
}
