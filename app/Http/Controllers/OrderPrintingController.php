<?php

namespace App\Http\Controllers;

use App\Application\Dining\DiningContext;
use App\Application\Orders\OrderPrinting;
use App\Enums\StaffPermission;
use App\Models\DiningOrder;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OrderPrintingController extends Controller
{
    public function index(Request $request): Response
    {
        $targets = [];
        foreach (['kitchen' => StaffPermission::PrintKitchen, 'cashier' => StaffPermission::PrintCashier] as $target => $permission) {
            if ($request->user()->hasPermission($permission)) {
                $targets[] = $target;
            }
        }
        abort_unless($targets !== [], 403);
        $request->validate(['order' => 'nullable|integer|min:1', 'all' => 'nullable|boolean']);
        $jobs = DB::table('print_jobs')->join('dining_orders', 'dining_orders.id', '=', 'print_jobs.order_id')->whereIn('target', $targets)
            ->when($request->input('order'), fn ($q, $id) => $q->where('order_id', $id))
            ->when(! $request->boolean('all'), fn ($q) => $q->whereIn('print_jobs.status', ['pending', 'printing', 'failed']))
            ->orderByDesc('print_jobs.id')->paginate(30, ['print_jobs.*', 'dining_orders.public_id', 'dining_orders.table_number', 'dining_orders.content_revision', 'dining_orders.modality'])->withQueryString();

        return Inertia::render('printing/index', ['jobs' => $jobs->getCollection()->map(fn ($job) => [
            'id' => $job->id, 'reference' => $job->public_id, 'table' => $job->table_number, 'locationLabel' => match ($job->modality) {
                'pickup' => 'Retirada · Pedido #'.$job->order_id, 'delivery' => 'Entrega · Pedido #'.$job->order_id, default => 'Mesa '.$job->table_number
            }, 'revision' => $job->revision, 'copy' => $job->copy,
            'target' => $job->target === 'kitchen' ? 'Cozinha' : 'Caixa', 'kind' => $job->kind, 'status' => $job->status,
            'stale' => $job->revision !== $job->content_revision, 'url' => route('printing.show', $job->id)]),
            'pagination' => ['previous' => $jobs->previousPageUrl(), 'next' => $jobs->nextPageUrl()], 'showAll' => $request->boolean('all')]);
    }

    public function show(Request $request, int $job, OrderPrinting $printing): Response
    {
        $record = $printing->authorizedJob($job, DiningContext::staff($request->user()));
        $order = DiningOrder::query()->where('id', $record->order_id)->firstOrFail();
        $stale = $order->content_revision !== $record->revision || $record->status === 'superseded';
        $latestCopy = DB::table('print_jobs')->where('order_id', $record->order_id)->where('revision', $record->revision)->where('target', $record->target)->max('copy');

        return Inertia::render('printing/document', ['ticket' => json_decode($record->snapshot, true, flags: JSON_THROW_ON_ERROR),
            'job' => ['id' => $job, 'target' => $record->target, 'kind' => $record->kind, 'copy' => $record->copy, 'status' => $record->status, 'stale' => $stale,
                'claimUrl' => ! $stale && $record->status === 'pending' ? route('printing.act', ['job' => $job, 'action' => 'claim']) : null,
                'canPrint' => ! $stale && $record->status === 'printing' && $record->claimed_by_id === $request->user()->id,
                'canReprint' => ! $stale && $record->copy === $latestCopy && (in_array($record->status, ['printed', 'failed'], true) || ($record->status === 'printing' && now()->greaterThanOrEqualTo(CarbonImmutable::parse($record->claimed_at)->addMinutes(2)))),
                'actionUrl' => route('printing.act', ['job' => $job, 'action' => '__action__']), 'operationId' => (string) Str::uuid()],
            'events' => DB::table('print_events')->leftJoin('users', 'users.id', '=', 'print_events.actor_id')->where('job_id', $job)
                ->orderBy('print_events.id')->get(['print_events.action', 'print_events.reason', 'print_events.created_at', 'users.name as actor'])]);
    }

    public function act(Request $request, int $job, string $action, OrderPrinting $printing): RedirectResponse
    {
        $id = $printing->act($job, $action, $request->all(), DiningContext::staff($request->user()));

        return to_route('printing.show', $id);
    }

    public function legacy(Request $request, DiningOrder $order, OrderPrinting $printing): RedirectResponse
    {
        $printing->legacy($order->id, DiningContext::staff($request->user()));

        return to_route('printing.index', ['order' => $order->id]);
    }
}
