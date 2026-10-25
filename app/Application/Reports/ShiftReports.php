<?php

namespace App\Application\Reports;

use App\Application\Dining\DiningContext;
use App\Enums\StaffPermission;
use App\Models\CashShift;
use Illuminate\Support\Facades\DB;

class ShiftReports
{
    public function __construct(private ShiftReportData $data) {}

    /** @return array{shift: CashShift|null, data: array<string, mixed>, frozen: bool, legacy: bool} */
    public function read(?int $id, DiningContext $context): array
    {
        return DB::transaction(function () use ($id, $context): array {
            $context->authorize(StaffPermission::ViewReports);
            $shift = $id !== null ? CashShift::query()->sharedLock()->findOrFail($id) : null;
            $frozen = $shift?->closed_at !== null && $shift->report_snapshot !== null;

            return ['shift' => $shift, 'data' => $frozen ? $shift->report_snapshot : $this->data->build($id),
                'frozen' => $frozen, 'legacy' => $shift?->closed_at !== null && ! $frozen];
        });
    }
}
