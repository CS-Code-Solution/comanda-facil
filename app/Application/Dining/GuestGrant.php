<?php

namespace App\Application\Dining;

use App\Models\DiningTable;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class GuestGrant
{
    public function __construct(public int $tableId, public int $version, public int $cycle, public string $visitor, public int $expiresAt) {}

    public function clientHash(): string
    {
        return hash('sha256', $this->visitor);
    }

    public function authorize(?DiningTable $table = null, bool $write = false): DiningTable
    {
        $table ??= DiningTable::query()->sharedLock()->find($this->tableId);
        if (! $table || $table->id !== $this->tableId || $table->qr_version !== $this->version || $this->expiresAt <= now()->timestamp || ! $table->is_active) {
            throw new AuthorizationException('Leia o QR da mesa novamente para continuar.');
        }
        if ($write && ($table->is_blocked || $table->cycle !== $this->cycle)) {
            throw new AuthorizationException('A mesa foi pausada ou mudou de atendimento. Leia o QR novamente.');
        }

        return $table;
    }
}
