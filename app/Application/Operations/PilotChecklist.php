<?php

namespace App\Application\Operations;

use App\Application\Dining\DiningContext;
use App\Enums\StaffPermission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PilotChecklist
{
    public const LABELS = [
        'catalog_review' => 'Cardápio e preços conferidos pelo responsável',
        'thermal_print' => 'Impressão térmica real na cozinha e no caixa',
        'devices_network' => 'Aparelhos e rede do restaurante testados',
        'external_backup' => 'Cópia externa e chave de recuperação protegidas',
        'staff_training' => 'Equipe treinada nos procedimentos e falhas',
        'restaurant_acceptance' => 'Aceite do responsável para o piloto local',
    ];

    public function __construct(private OperationalStatus $status) {}

    /** @param array<string, mixed> $input */
    public function record(string $name, array $input, DiningContext $context): void
    {
        abort_unless(isset(self::LABELS[$name]), 404);
        $data = Validator::make($input, ['passed' => 'required|boolean', 'version' => 'required|integer|min:1',
            'evidence' => 'required|string|min:10|max:1500', 'performed' => 'required|accepted'])->validate();
        DB::transaction(function () use ($name, $data, $context): void {
            $context->authorize(StaffPermission::Administration);
            // Serialize all checklist updates, including final acceptance invalidation.
            $checks = DB::table('pilot_checks')->orderBy('name')->lockForUpdate()->get()->keyBy('name');
            $row = $checks->get($name);
            if (! $row || $row->version !== (int) $data['version']) {
                throw ValidationException::withMessages(['pilot' => 'A conferência mudou. Recarregue antes de registrar.']);
            }
            if ($name === 'restaurant_acceptance' && $data['passed']
                && ($checks->except('restaurant_acceptance')->contains(fn ($item) => ! $item->passed) || ! $this->status->read()['ready'])) {
                throw ValidationException::withMessages(['pilot' => 'Conclua as verificações técnicas e as demais evidências antes do aceite.']);
            }
            DB::table('pilot_checks')->where('name', $name)->update(['passed' => (bool) $data['passed'], 'evidence' => $data['evidence'],
                'actor_id' => $context->actorId, 'checked_at' => now(), 'version' => $row->version + 1]);
            if ($name !== 'restaurant_acceptance' && $checks->get('restaurant_acceptance')->passed) {
                DB::table('pilot_checks')->where('name', 'restaurant_acceptance')->update(['passed' => false, 'evidence' => 'Revalidar aceite após alteração da conferência.',
                    'actor_id' => $context->actorId, 'checked_at' => now(), 'version' => $checks->get('restaurant_acceptance')->version + 1]);
            }
            DB::table('operations_events')->insert(['actor_id' => $context->actorId, 'action' => 'pilot_check_recorded',
                'details' => json_encode(['name' => $name, 'passed' => (bool) $data['passed'], 'evidence' => $data['evidence']], JSON_THROW_ON_ERROR), 'created_at' => now()]);
        });
    }
}
