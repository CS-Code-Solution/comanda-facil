<?php

namespace App\Http\Controllers;

use App\Application\Cash\AccountBalance;
use App\Application\Dining\DiningCalls;
use App\Application\Dining\DiningContext;
use App\Application\Dining\DiningOrders;
use App\Application\Dining\ManageDining;
use App\Domain\Catalog\Money;
use App\Enums\StaffPermission;
use App\Http\Presenters\DiningMenu;
use App\Models\DiningAlert;
use App\Models\DiningCall;
use App\Models\DiningOrder;
use App\Models\DiningTable;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DiningController extends Controller
{
    public function index(Request $request): Response
    {
        $tables = DiningTable::query()->with(['currentSession' => fn ($query) => $query->withSum(['orders' => fn ($orders) => $orders->where('status', '!=', 'cancelled')], 'total_cents')])
            ->withCount(['orders as recent_qr_count' => fn ($query) => $query->where('source', 'qr')->where('created_at', '>', now()->subMinutes(5))])
            ->orderBy('number')->paginate(24, ['*'], 'tables_page')->withQueryString();
        $calls = DiningCall::query()->whereIn('status', ['pending', 'claimed'])->with(['table', 'claimant'])->orderBy('id')->paginate(10, ['*'], 'calls_page')->withQueryString();
        $alerts = DiningAlert::query()->whereNull('acknowledged_at')->with('table')->orderBy('id')->paginate(10, ['*'], 'alerts_page')->withQueryString();

        return Inertia::render('dining/index', [
            'tables' => $tables->getCollection()->map(fn (DiningTable $table): array => ['id' => $table->id, 'number' => $table->number, 'label' => $table->label,
                'active' => $table->is_active, 'blocked' => $table->is_blocked, 'open' => $table->currentSession !== null,
                'total' => Money::label((int) $table->currentSession?->getAttribute('orders_sum_total_cents')), 'recentQr' => $table->getAttribute('recent_qr_count'), 'url' => route('dining.show', $table)]),
            'tablesLinks' => ['previous' => $tables->previousPageUrl(), 'next' => $tables->nextPageUrl()],
            'calls' => $calls->getCollection()->map(fn (DiningCall $call): array => ['id' => $call->id, 'table' => $call->table->number, 'status' => $call->status === 'pending' ? 'Aguardando' : 'Em atendimento',
                'claimant' => $call->claimant?->name, 'createdAt' => $call->created_at->toIso8601String(),
                'claimUrl' => $call->status === 'pending' && Gate::allows(StaffPermission::AttendDiningCalls->value) ? route('dining.calls.claim', $call->id) : null,
                'completeUrl' => $call->status === 'claimed' && $call->claimed_by_id === $request->user()->id && Gate::allows(StaffPermission::AttendDiningCalls->value) ? route('dining.calls.complete', $call->id) : null]),
            'callsLinks' => ['previous' => $calls->previousPageUrl(), 'next' => $calls->nextPageUrl()],
            'alerts' => $alerts->getCollection()->map(fn (DiningAlert $alert): array => ['id' => $alert->id, 'table' => $alert->table->number, 'message' => 'Novo atendimento após encerramento', 'ackUrl' => route('dining.alerts.ack', $alert->id)]),
            'alertsLinks' => ['previous' => $alerts->previousPageUrl(), 'next' => $alerts->nextPageUrl()],
            'receiving' => (bool) DB::table('dining_settings')->where('id', 1)->value('accepting_orders'), 'canManage' => Gate::allows(StaffPermission::ManageDining->value),
        ]);
    }

    public function show(Request $request, DiningTable $table): Response
    {
        $table->load('currentSession');
        $orders = $table->orders()->with(['actor', 'items'])->orderByDesc('id')->paginate(20)->withQueryString();
        $session = $table->currentSession;
        $balance = $session ? app(AccountBalance::class)->forSession($session) : null;

        return Inertia::render('dining/table', ['table' => $table->only(['id', 'number', 'label', 'cycle', 'is_active', 'is_blocked']),
            'session' => $session ? ['id' => $session->id, 'total' => Money::label($balance['total_cents']),
                'refundDue' => Money::label($balance['refund_due_cents']), 'hasRefundDue' => $balance['refund_due_cents'] > 0,
                'cashUrl' => $request->user()->hasPermission(StaffPermission::ReceivePayment) ? route('cash.account', $session) : null,
                'settled' => $session->settled_at !== null, 'closeUrl' => Gate::allows(StaffPermission::CloseDiningSession->value) ? route('dining.close', ['table' => $table->id, 'sessionId' => $session->id]) : null] : null,
            'orders' => $orders->getCollection()->map(fn (DiningOrder $order): array => [...DiningMenu::order($order), 'url' => route('operations.show', $order), 'source' => $order->source === 'qr' ? 'QR do cliente' : 'Equipe', 'actor' => $order->actor?->name]),
            'pagination' => ['previous' => $orders->previousPageUrl(), 'next' => $orders->nextPageUrl()],
            'canManage' => Gate::allows(StaffPermission::ManageDining->value), 'canOrder' => Gate::allows(StaffPermission::PlaceDiningOrder->value),
            'orderUrl' => route('dining.menu', $table), 'qrUrl' => route('dining.qr', $table), 'updateUrl' => route('dining.update', $table), 'rotateUrl' => route('dining.rotate', $table),
        ]);
    }

    public function createTable(Request $request, ManageDining $manage): RedirectResponse
    {
        $table = $manage->createTable($request->all(), DiningContext::staff($request->user()));

        return to_route('dining.show', $table);
    }

    public function update(Request $request, DiningTable $table, ManageDining $manage): RedirectResponse
    {
        $manage->changeTable($table->id, $request->all(), DiningContext::staff($request->user()));

        return back();
    }

    public function rotate(Request $request, DiningTable $table, ManageDining $manage): RedirectResponse
    {
        $manage->changeTable($table->id, $request->all(), DiningContext::staff($request->user()), true);

        return back();
    }

    public function service(Request $request, ManageDining $manage): RedirectResponse
    {
        $data = $request->validate(['open' => 'required|boolean', 'reason' => 'required|string|min:3|max:500']);
        $manage->service((bool) $data['open'], $data['reason'], DiningContext::staff($request->user()));

        return back();
    }

    public function close(Request $request, DiningTable $table, int $sessionId, ManageDining $manage): RedirectResponse
    {
        $manage->close($table->id, $sessionId, DiningContext::staff($request->user()));

        return back();
    }

    public function claim(Request $request, int $call, DiningCalls $calls): RedirectResponse
    {
        $calls->respond($call, false, DiningContext::staff($request->user()));

        return back();
    }

    public function complete(Request $request, int $call, DiningCalls $calls): RedirectResponse
    {
        $calls->respond($call, true, DiningContext::staff($request->user()));

        return back();
    }

    public function acknowledge(Request $request, int $alert, DiningCalls $calls): RedirectResponse
    {
        $calls->acknowledge($alert, DiningContext::staff($request->user()));

        return back();
    }

    public function qr(Request $request, DiningTable $table): \Symfony\Component\HttpFoundation\Response
    {
        $response = Inertia::render('dining/qr', ['number' => $table->number, 'url' => $this->qrUrl($table), 'imageUrl' => route('dining.qr.image', $table), 'backUrl' => route('dining.show', $table)])->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    public function qrImage(DiningTable $table): \Illuminate\Http\Response
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(400, 4), new SvgImageBackEnd));

        return response($writer->writeString($this->qrUrl($table)), 200, ['Content-Type' => 'image/svg+xml', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    private function qrUrl(DiningTable $table): string
    {
        return rtrim((string) config('app.url'), '/').'/m/'.$table->qr_token;
    }

    public function menu(DiningTable $table): Response
    {
        return Inertia::render('dining/menu', ['table' => ['number' => $table->number, 'label' => $table->label, 'cycle' => $table->cycle], 'products' => DiningMenu::products(),
            'receiving' => ! $table->is_blocked && $table->is_active && DB::table('dining_settings')->where('id', 1)->value('accepting_orders'), 'previewUrl' => route('dining.preview', $table), 'callUrl' => null, 'staff' => true,
            'canManualDiscount' => Gate::allows(StaffPermission::ManualDiscount->value)]);
    }

    public function preview(Request $request, DiningTable $table, DiningOrders $orders): RedirectResponse
    {
        $intent = $orders->preview($table->id, $request->all(), DiningContext::staff($request->user()));

        return to_route('dining.review', ['table' => $table->id, 'intent' => $intent->id]);
    }

    public function review(Request $request, DiningTable $table, string $intent, DiningOrders $orders): Response
    {
        $record = $orders->intent($intent, $table->id, DiningContext::staff($request->user()));

        return Inertia::render('dining/review', ['tableNumber' => $table->number, 'items' => DiningMenu::items($record->snapshot), 'total' => Money::label($record->total_cents),
            'expiresAt' => $record->expires_at->toIso8601String(), 'expired' => $record->expires_at <= now(), 'confirmUrl' => route('dining.confirm', ['table' => $table->id, 'intent' => $intent]), 'backUrl' => route('dining.menu', $table)]);
    }

    public function confirm(Request $request, DiningTable $table, string $intent, DiningOrders $orders): RedirectResponse
    {
        $orders->confirm($intent, $table->id, DiningContext::staff($request->user()));

        return to_route('dining.show', $table);
    }
}
