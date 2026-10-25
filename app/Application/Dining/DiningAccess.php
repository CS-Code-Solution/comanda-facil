<?php

namespace App\Application\Dining;

use App\Models\DiningTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiningAccess
{
    public static function lockTable(int $id): DiningTable
    {
        DB::table('dining_settings')->where('id', 1)->sharedLock()->firstOrFail();

        return DiningTable::query()->lockForUpdate()->findOrFail($id);
    }

    public static function receiving(DiningTable $table): void
    {
        if (! DB::table('dining_settings')->where('id', 1)->value('accepting_orders') || ! $table->is_active || $table->is_blocked) {
            throw ValidationException::withMessages(['basket' => 'A mesa não está recebendo pedidos ou chamados. Procure a equipe.']);
        }
    }
}
