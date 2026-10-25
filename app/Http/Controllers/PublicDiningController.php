<?php

namespace App\Http\Controllers;

use App\Application\Dining\DiningCalls;
use App\Application\Dining\DiningContext;
use App\Application\Dining\DiningOrders;
use App\Application\Dining\GuestGrant;
use App\Domain\Catalog\Money;
use App\Http\Presenters\DiningMenu;
use App\Models\CatalogProduct;
use App\Models\DiningOrder;
use App\Models\DiningTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicDiningController extends Controller
{
    private function grant(Request $request): GuestGrant
    {
        $grant = $request->attributes->get('dining.grant');
        abort_unless($grant instanceof GuestGrant, 403);

        return $grant;
    }

    private function table(Request $request): DiningTable
    {
        $table = $request->attributes->get('dining.table');
        abort_unless($table instanceof DiningTable, 404);

        return $table;
    }

    public function menu(Request $request, string $token): Response
    {
        $table = $this->table($request);

        return Inertia::render('public/dining/menu', ['table' => ['number' => $table->number, 'label' => $table->label, 'cycle' => $table->cycle],
            'products' => DiningMenu::products($token), 'receiving' => $table->is_active && ! $table->is_blocked && DB::table('dining_settings')->where('id', 1)->value('accepting_orders'),
            'previewUrl' => route('qr.preview', $token), 'callUrl' => route('qr.call', $token), 'staff' => false]);
    }

    public function preview(Request $request, string $token, DiningOrders $orders): RedirectResponse
    {
        $intent = $orders->preview($this->table($request)->id, $request->all(), DiningContext::guest($this->grant($request)));

        return to_route('qr.review', ['token' => $token, 'intent' => $intent->id]);
    }

    public function review(Request $request, string $token, string $intent, DiningOrders $orders): Response
    {
        $record = $orders->intent($intent, $this->table($request)->id, DiningContext::guest($this->grant($request)));

        return Inertia::render('public/dining/review', ['tableNumber' => $this->table($request)->number, 'items' => DiningMenu::items($record->snapshot), 'total' => Money::label($record->total_cents),
            'expiresAt' => $record->expires_at->toIso8601String(), 'expired' => $record->expires_at <= now(), 'confirmUrl' => route('qr.confirm', ['token' => $token, 'intent' => $intent]), 'backUrl' => route('qr.menu', $token)]);
    }

    public function confirm(Request $request, string $token, string $intent, DiningOrders $orders): RedirectResponse
    {
        $order = $orders->confirm($intent, $this->table($request)->id, DiningContext::guest($this->grant($request)));

        return to_route('qr.receipt', ['token' => $token, 'reference' => $order->public_id]);
    }

    public function receipt(Request $request, string $token, string $reference): Response
    {
        $order = DiningOrder::query()->where('table_id', $this->table($request)->id)->where('source', 'qr')
            ->where('client_hash', $this->grant($request)->clientHash())->where('public_id', $reference)->firstOrFail();

        return Inertia::render('public/dining/receipt', ['order' => DiningMenu::order($order), 'backUrl' => route('qr.menu', $token),
            'realtimeAuthUrl' => route('qr.realtime', ['token' => $token, 'reference' => $reference])]);
    }

    public function realtime(Request $request, string $token, string $reference): mixed
    {
        $request->validate(['channel_name' => 'required|string', 'socket_id' => ['required', 'string', 'regex:/^\d+\.\d+$/']]);
        $this->grant($request)->authorize();
        $order = DiningOrder::query()->where('table_id', $this->table($request)->id)->where('source', 'qr')
            ->where('client_hash', $this->grant($request)->clientHash())->where('public_id', $reference)->firstOrFail();
        abort_unless($request->input('channel_name') === 'private-dining.order.'.$order->public_id, 403);

        return Broadcast::connection('reverb')->validAuthenticationResponse($request, true);
    }

    public function call(Request $request, DiningCalls $calls): RedirectResponse
    {
        $calls->request($this->grant($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'A equipe recebeu o chamado desta mesa.']);

        return back();
    }

    public function photo(Request $request, string $token, CatalogProduct $product): StreamedResponse
    {
        $this->grant($request)->authorize();
        $product->load(['category', 'optionGroups.options']);
        abort_unless($product->is_active && $product->is_reviewed && $product->category->is_active && $product->photo_path && Storage::disk('local')->exists($product->photo_path), 404);

        return Storage::disk('local')->response($product->photo_path, headers: ['X-Content-Type-Options' => 'nosniff']);
    }
}
