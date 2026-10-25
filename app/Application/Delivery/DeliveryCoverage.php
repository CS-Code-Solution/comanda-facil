<?php

namespace App\Application\Delivery;

use App\Application\Cash\CashMoney;
use App\Application\Dining\DiningContext;
use App\Application\Orders\OperationsChanges;
use App\Enums\StaffPermission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeliveryCoverage
{
    public static function key(string $value): string
    {
        return Str::lower(Str::ascii(preg_replace('/\s+/u', ' ', trim($value)) ?? ''));
    }

    /** @param array<string, mixed> $input */
    public function save(?int $id, array $input, DiningContext $context): int
    {
        if (is_string($input['state'] ?? null)) {
            $input['state'] = strtoupper(trim($input['state']));
        }
        $data = Validator::make($input, ['name' => 'required|string|max:120', 'city' => 'required|string|max:120',
            'state' => 'required|string|in:'.DeliveryInput::STATES, 'neighborhood' => 'nullable|string|max:120',
            'cep_start' => ['nullable', 'string', 'regex:/^[0-9]{5}-?[0-9]{3}$/'],
            'cep_end' => ['nullable', 'string', 'regex:/^[0-9]{5}-?[0-9]{3}$/'],
            'fee' => 'required|string', 'minimum' => 'required|string', 'is_active' => 'required|boolean',
            'version' => ($id ? 'required' : 'nullable').'|integer|min:1', 'reason' => 'required|string|min:3|max:500'])->validate();
        $start = empty($data['cep_start']) ? null : str_replace('-', '', $data['cep_start']);
        $end = empty($data['cep_end']) ? null : str_replace('-', '', $data['cep_end']);
        $neighborhood = empty($data['neighborhood']) ? null : trim($data['neighborhood']);
        if ((! $neighborhood && ! $start) || (($start === null) !== ($end === null)) || ($start !== null && $start > $end)) {
            throw ValidationException::withMessages(['coverage' => 'Informe bairro e/ou faixa completa de CEP, com início menor ou igual ao fim.']);
        }
        $values = ['name' => trim($data['name']), 'city' => trim($data['city']), 'city_key' => self::key($data['city']),
            'state' => strtoupper($data['state']), 'neighborhood' => $neighborhood, 'neighborhood_key' => $neighborhood ? self::key($neighborhood) : null,
            'cep_start' => $start, 'cep_end' => $end, 'fee_cents' => CashMoney::parse($data['fee'], 'fee'),
            'minimum_cents' => CashMoney::parse($data['minimum'], 'minimum'), 'is_active' => (bool) $data['is_active']];

        return DB::transaction(function () use ($id, $data, $values, $context): int {
            DB::table('delivery_settings')->where('id', 1)->lockForUpdate()->firstOrFail();
            $context->authorize(StaffPermission::ManageDelivery);
            $old = $id ? DB::table('delivery_areas')->where('id', $id)->firstOrFail() : null;
            if ($old && $old->version !== (int) $data['version']) {
                throw ValidationException::withMessages(['coverage' => 'A área mudou. Recarregue antes de salvar.']);
            }
            if ($values['is_active']) {
                $overlap = DB::table('delivery_areas')->where('is_active', true)->where('city_key', $values['city_key'])->where('state', $values['state'])
                    ->when($id, fn ($q) => $q->where('id', '<>', $id))
                    ->when($values['neighborhood_key'] !== null, fn ($q) => $q->where(fn ($q) => $q->whereNull('neighborhood_key')->orWhere('neighborhood_key', $values['neighborhood_key'])))
                    ->when($values['cep_start'] !== null, fn ($q) => $q->where(fn ($q) => $q->whereNull('cep_start')->orWhere(fn ($q) => $q->where('cep_start', '<=', $values['cep_end'])->where('cep_end', '>=', $values['cep_start']))))->exists();
                if ($overlap) {
                    throw ValidationException::withMessages(['coverage' => 'A regra coincide com outra área ativa. Ajuste bairro/faixa ou desative a anterior.']);
                }
            }
            $values['version'] = ($old->version ?? 0) + 1;
            $values['updated_at'] = now();
            if ($id) {
                DB::table('delivery_areas')->where('id', $id)->update($values);
            } else {
                $id = DB::table('delivery_areas')->insertGetId([...$values, 'created_at' => now()]);
            }
            DB::table('delivery_events')->insert(['actor_id' => $context->actorId, 'action' => 'area_saved',
                'details' => json_encode(['area_id' => $id, 'before' => $old, 'after' => $values, 'reason' => $data['reason']], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            OperationsChanges::record();

            return $id;
        });
    }

    public function service(bool $open, string $reason, DiningContext $context): void
    {
        Validator::make(['reason' => $reason], ['reason' => 'required|string|min:3|max:500'])->validate();
        DB::transaction(function () use ($open, $reason, $context): void {
            DB::table('delivery_settings')->where('id', 1)->lockForUpdate()->firstOrFail();
            $context->authorize(StaffPermission::ManageDelivery);
            if ($open && ! DB::table('delivery_areas')->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['delivery' => 'Cadastre e ative ao menos uma área antes de abrir entregas.']);
            }
            DB::table('delivery_settings')->where('id', 1)->update(['accepting_orders' => $open]);
            DB::table('delivery_events')->insert(['actor_id' => $context->actorId, 'action' => 'receiving_changed',
                'details' => json_encode(['open' => $open, 'reason' => $reason], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            OperationsChanges::record();
        });
    }

    /** @param array<string, string> $address
     * @return array<string, mixed>
     */
    public function match(array $address): array
    {
        $settings = DB::table('delivery_settings')->where('id', 1)->sharedLock()->firstOrFail();
        if (! $settings->accepting_orders) {
            throw ValidationException::withMessages(['basket' => 'O recebimento de entregas está pausado. Procure o restaurante.']);
        }
        $areas = DB::table('delivery_areas')->where('is_active', true)->where('city_key', self::key($address['city']))->where('state', $address['state'])
            ->where(fn ($q) => $q->whereNull('neighborhood_key')->orWhere('neighborhood_key', self::key($address['neighborhood'])))
            ->where(fn ($q) => $q->whereNull('cep_start')->orWhere(fn ($q) => $q->where('cep_start', '<=', $address['cep'])->where('cep_end', '>=', $address['cep'])))->limit(2)->get();
        if ($areas->count() !== 1) {
            throw ValidationException::withMessages(['address' => $areas->isEmpty() ? 'Endereço fora da cobertura de entrega.' : 'A cobertura deste endereço precisa de revisão pela equipe.']);
        }
        $area = $areas->first();

        return ['area_id' => $area->id, 'version' => $area->version, 'name' => $area->name, 'fee_cents' => $area->fee_cents, 'minimum_cents' => $area->minimum_cents];
    }

    public static function minimum(int $items, int $minimum): void
    {
        if ($items < $minimum) {
            throw ValidationException::withMessages(['basket' => 'O mínimo desta área é '.CashMoney::label($minimum).' em itens após descontos, antes da taxa.']);
        }
    }
}
