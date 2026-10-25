<?php

namespace App\Http\Controllers;

use App\Application\Dining\DiningContext;
use App\Application\Reports\ShiftReports;
use App\Domain\Catalog\Money;
use App\Http\Presenters\CashViews;
use App\Models\CashShift;
use App\Models\DiningOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class ShiftReportController extends Controller
{
    public function index(): RedirectResponse
    {
        $shift = CashShift::query()->orderByDesc('id')->first();

        return $shift ? to_route('reports.show', $shift) : to_route('reports.unassigned');
    }

    public function show(Request $request, CashShift $shift, ShiftReports $reports): Response
    {
        return $this->render($request, $shift->id, $reports);
    }

    public function unassigned(Request $request, ShiftReports $reports): Response
    {
        return $this->render($request, null, $reports);
    }

    private function render(Request $request, ?int $id, ShiftReports $reports): Response
    {
        $request->validate(['waiters_page' => 'nullable|integer|min:1', 'shifts_page' => 'nullable|integer|min:1']);
        $record = $reports->read($id, DiningContext::staff($request->user()));
        $data = $record['data'];
        $shift = $record['shift'];
        if ($shift && $shift->closing === null) {
            $shift = clone $shift;
            $shift->forceFill(['closing' => $data['cash_totals']]);
        }
        $page = $request->integer('waiters_page', 1);
        $waiters = new LengthAwarePaginator(array_slice($data['waiters'], ($page - 1) * 20, 20), count($data['waiters']), 20, $page,
            ['path' => $request->url(), 'pageName' => 'waiters_page', 'query' => $request->except('waiters_page')]);
        $history = CashShift::query()->orderByDesc('id')->paginate(12, ['id', 'opened_at', 'closed_at'], 'shifts_page')->withQueryString();

        return Inertia::render('reports/shift', ['shift' => $shift ? CashViews::shift($shift) : null, 'frozen' => $record['frozen'],
            'legacy' => $record['legacy'], 'generatedAt' => $data['generated_at'],
            'modalities' => array_map(fn ($row) => [...$row, 'label' => match ($row['modality']) {
                'pickup' => 'Retirada', 'delivery' => 'Entrega', default => 'Salão',
            }, 'gross' => Money::label($row['gross_cents']), 'discount' => Money::label($row['discount_cents']),
                'fee' => Money::label($row['fee_cents']), 'received' => Money::label($row['received_cents']),
                'refunded' => Money::label($row['refunded_cents']), 'net' => Money::label($row['net_cents']),
                'cancelledValue' => Money::label($row['cancelled_cents'])], $data['modalities']),
            'waiters' => $waiters->items(), 'waiterLinks' => ['previous' => $waiters->previousPageUrl(), 'next' => $waiters->nextPageUrl()],
            'qrOrders' => $data['qr_orders'], 'unknownAuthorship' => $data['unknown_authorship'],
            'unassignedCount' => DiningOrder::query()->whereNull('cash_shift_id')->count(), 'unassignedUrl' => route('reports.unassigned'),
            'shifts' => $history->getCollection()->map(fn ($item) => ['id' => $item->id, 'openedAt' => $item->opened_at->toIso8601String(),
                'closed' => $item->closed_at !== null, 'url' => route('reports.show', $item)]),
            'shiftLinks' => ['previous' => $history->previousPageUrl(), 'next' => $history->nextPageUrl()]]);
    }
}
