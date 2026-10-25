<?php

namespace App\Http\Presenters;

use App\Enums\StaffRole;
use App\Models\StaffAccessEvent;

class StaffAudit
{
    /** @return array<string, mixed> */
    public static function present(StaffAccessEvent $event): array
    {
        $changes = [];

        foreach (['name' => 'Nome', 'username' => 'Usuário', 'role' => 'Papel', 'is_active' => 'Acesso'] as $field => $label) {
            $before = $event->before[$field] ?? null;
            $after = $event->after[$field] ?? null;

            if ($before !== $after) {
                $changes[] = ['field' => $label, 'before' => self::value($field, $before), 'after' => self::value($field, $after)];
            }
        }

        return [
            'id' => $event->id,
            'action' => match ($event->action) {
                'created' => 'Conta criada',
                'updated' => 'Cadastro atualizado',
                'role_changed' => 'Papel alterado',
                'activated' => 'Conta ativada',
                'disabled' => 'Conta desativada',
                'pin_reset' => 'PIN redefinido',
                'pin_changed' => 'PIN alterado pelo titular',
                'sessions_revoked' => 'Sessões revogadas',
                default => 'Alteração de acesso',
            },
            'actor' => $event->actor->name ?? 'Operador do servidor',
            'source' => $event->source,
            'occurred_at' => $event->occurred_at->toIso8601String(),
            'changes' => $changes,
        ];
    }

    private static function value(string $field, mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        if ($field === 'is_active') {
            return $value ? 'Ativo' : 'Inativo';
        }

        if (! is_string($value)) {
            return '—';
        }

        return $field === 'role' ? (StaffRole::tryFrom($value)?->label() ?? 'Sem papel') : $value;
    }
}
