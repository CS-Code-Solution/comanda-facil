<?php

use App\Enums\StaffPermission;
use App\Http\Controllers\Auth\StaffSessionController;
use App\Http\Controllers\CashController;
use App\Http\Controllers\CatalogAdminController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CatalogPhotoController;
use App\Http\Controllers\DiningController;
use App\Http\Controllers\OrderOperationsController;
use App\Http\Controllers\OrderPrintingController;
use App\Http\Controllers\PickupCashController;
use App\Http\Controllers\PickupController;
use App\Http\Controllers\PublicDiningController;
use App\Http\Controllers\StaffAccountController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Middleware\ResolveDiningQr;
use App\Http\Middleware\ResolvePickupGuest;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('m/{token}')->where(['token' => '[a-f0-9]{64}'])->name('qr.')->middleware(['throttle:dining-public-read', ResolveDiningQr::class])->group(function () {
    Route::get('/', [PublicDiningController::class, 'menu'])->name('menu');
    Route::get('confirmar/{intent}', [PublicDiningController::class, 'review'])->whereUuid('intent')->name('review');
    Route::get('pedidos/{reference}', [PublicDiningController::class, 'receipt'])->whereUuid('reference')->name('receipt');
    Route::post('pedidos/{reference}/realtime/auth', [PublicDiningController::class, 'realtime'])->whereUuid('reference')->middleware('throttle:dining-public-write')->name('realtime');
    Route::get('fotos/{product}', [PublicDiningController::class, 'photo'])->whereNumber('product')->name('photo');
    Route::middleware('throttle:dining-public-write')->group(function () {
        Route::post('previa', [PublicDiningController::class, 'preview'])->name('preview');
        Route::post('confirmar/{intent}', [PublicDiningController::class, 'confirm'])->whereUuid('intent')->name('confirm');
        Route::post('chamados', [PublicDiningController::class, 'call'])->name('call');
    });
});

Route::prefix('retirada')->name('pickup.public.')->middleware(['throttle:pickup-read', ResolvePickupGuest::class])->group(function () {
    Route::get('/', [PickupController::class, 'menu'])->name('menu');
    Route::get('confirmar/{intent}', [PickupController::class, 'review'])->whereUuid('intent')->name('review');
    Route::get('pedidos/{reference}', [PickupController::class, 'receipt'])->whereUuid('reference')->name('receipt');
    Route::get('fotos/{product}', [PickupController::class, 'photo'])->whereNumber('product')->name('photo');
    Route::middleware('throttle:pickup-write')->group(function () {
        Route::post('previa', [PickupController::class, 'preview'])->name('preview');
        Route::post('confirmar/{intent}', [PickupController::class, 'confirm'])->whereUuid('intent')->name('confirm');
        Route::post('pedidos/{reference}/realtime/auth', [PickupController::class, 'realtime'])->whereUuid('reference')->name('realtime');
    });
});

Route::middleware('guest')->group(function () {
    Route::get('login', [StaffSessionController::class, 'create'])->name('login');
    Route::post('login', [StaffSessionController::class, 'store'])
        ->middleware('throttle:staff-login')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::post('logout', [StaffSessionController::class, 'destroy'])->name('logout');

    Route::prefix('retiradas')->name('pickup.')->middleware('can:'.StaffPermission::ViewPickup->value)->group(function () {
        Route::get('/', [PickupController::class, 'index'])->name('index');
        Route::post('servico', [PickupController::class, 'service'])->middleware('can:'.StaffPermission::ManagePickup->value)->name('service');
        Route::middleware('can:'.StaffPermission::PlacePickupOrder->value)->group(function () {
            Route::get('pedido', [PickupController::class, 'menu'])->name('menu');
            Route::post('previa', [PickupController::class, 'preview'])->name('preview');
            Route::get('confirmar/{intent}', [PickupController::class, 'review'])->whereUuid('intent')->name('review');
            Route::post('confirmar/{intent}', [PickupController::class, 'confirm'])->whereUuid('intent')->name('confirm');
        });
    });

    Route::prefix('caixa')->name('cash.')->middleware('can:'.StaffPermission::ReceivePayment->value)->group(function () {
        Route::get('contas/{session}', [CashController::class, 'account'])->whereNumber('session')->name('account');
        Route::post('contas/{session}/previa', [CashController::class, 'preview'])->whereNumber('session')->name('preview');
        Route::get('contas/{session}/revisar/{intent}', [CashController::class, 'review'])->whereNumber('session')->whereUuid('intent')->name('review');
        Route::post('contas/{session}/confirmar/{intent}', [CashController::class, 'confirm'])->whereNumber('session')->whereUuid('intent')->name('confirm');
        Route::get('pagamentos/{payment}/comprovante', [CashController::class, 'receipt'])->whereNumber('payment')->name('receipt');
    });

    Route::get('areas/caixa', [CashController::class, 'index'])->middleware('can:'.StaffPermission::Cashier->value)->name('workspace.cashier');
    Route::prefix('caixa')->name('cash.')->middleware('can:'.StaffPermission::Cashier->value)->group(function () {
        Route::get('/', [CashController::class, 'index'])->name('index');
        Route::post('turnos', [CashController::class, 'open'])->name('open');
        Route::get('turnos/{shift}', [CashController::class, 'shift'])->whereNumber('shift')->name('shift');
        Route::post('turnos/{shift}/fechar', [CashController::class, 'close'])->whereNumber('shift')->name('close');
        Route::prefix('retiradas/{session}')->whereNumber('session')->name('pickup.')->middleware('can:'.StaffPermission::ViewPickup->value)->group(function () {
            Route::get('/', [PickupCashController::class, 'account'])->name('account');
            Route::post('previa', [PickupCashController::class, 'preview'])->name('preview');
            Route::get('revisar/{intent}', [PickupCashController::class, 'review'])->whereUuid('intent')->name('review');
            Route::post('confirmar/{intent}', [PickupCashController::class, 'confirm'])->whereUuid('intent')->name('confirm');
        });
        Route::post('pagamentos/{payment}/devolucao', [CashController::class, 'refund'])->whereNumber('payment')->middleware('can:'.StaffPermission::RefundPayment->value)->name('refund');
    });

    Route::get('areas/cozinha', [OrderOperationsController::class, 'kitchen'])->middleware('can:'.StaffPermission::Kitchen->value)->name('workspace.kitchen');
    Route::prefix('operacao/pedidos/{order}')->whereNumber('order')->name('operations.')->group(function () {
        Route::get('/', [OrderOperationsController::class, 'show'])->name('show');
        Route::post('estado', [OrderOperationsController::class, 'transition'])->name('transition');
        Route::middleware('can:'.StaffPermission::AmendOrder->value)->group(function () {
            Route::get('corrigir', [OrderOperationsController::class, 'edit'])->name('edit');
            Route::post('previa', [OrderOperationsController::class, 'preview'])->name('preview');
            Route::get('revisar/{intent}', [OrderOperationsController::class, 'review'])->whereUuid('intent')->name('review');
            Route::post('confirmar/{intent}', [OrderOperationsController::class, 'confirm'])->whereUuid('intent')->name('confirm');
        });
    });
    Route::get('impressoes', [OrderPrintingController::class, 'index'])->name('printing.index');
    Route::get('impressoes/{job}', [OrderPrintingController::class, 'show'])->whereNumber('job')->name('printing.show');
    Route::post('impressoes/{job}/{action}', [OrderPrintingController::class, 'act'])->whereNumber('job')->whereIn('action', ['claim', 'printed', 'failed', 'reprint'])->name('printing.act');
    Route::post('operacao/pedidos/{order}/comprovantes', [OrderPrintingController::class, 'legacy'])->whereNumber('order')->name('printing.legacy');

    Route::get('areas/salao', [DiningController::class, 'index'])->middleware('can:'.StaffPermission::DiningRoom->value)->name('workspace.dining-room');
    Route::prefix('salao')->name('dining.')->middleware('can:'.StaffPermission::DiningRoom->value)->group(function () {
        Route::get('/', [DiningController::class, 'index'])->name('index');
        Route::get('mesas/{table}', [DiningController::class, 'show'])->whereNumber('table')->name('show');
        Route::post('alertas/{alert}/ciente', [DiningController::class, 'acknowledge'])->whereNumber('alert')->name('alerts.ack');
        Route::middleware('can:'.StaffPermission::ManageDining->value)->group(function () {
            Route::post('mesas', [DiningController::class, 'createTable'])->name('store');
            Route::post('servico', [DiningController::class, 'service'])->name('service');
            Route::put('mesas/{table}', [DiningController::class, 'update'])->whereNumber('table')->name('update');
            Route::post('mesas/{table}/rotacionar', [DiningController::class, 'rotate'])->whereNumber('table')->name('rotate');
            Route::get('mesas/{table}/qr', [DiningController::class, 'qr'])->whereNumber('table')->name('qr');
            Route::get('mesas/{table}/qr.svg', [DiningController::class, 'qrImage'])->whereNumber('table')->name('qr.image');
        });
        Route::middleware('can:'.StaffPermission::PlaceDiningOrder->value)->group(function () {
            Route::get('mesas/{table}/pedido', [DiningController::class, 'menu'])->whereNumber('table')->name('menu');
            Route::post('mesas/{table}/previa', [DiningController::class, 'preview'])->whereNumber('table')->name('preview');
            Route::get('mesas/{table}/confirmar/{intent}', [DiningController::class, 'review'])->whereNumber('table')->whereUuid('intent')->name('review');
            Route::post('mesas/{table}/confirmar/{intent}', [DiningController::class, 'confirm'])->whereNumber('table')->whereUuid('intent')->name('confirm');
        });
        Route::post('mesas/{table}/sessoes/{sessionId}/encerrar', [DiningController::class, 'close'])->whereNumber(['table', 'sessionId'])->middleware('can:'.StaffPermission::CloseDiningSession->value)->name('close');
        Route::middleware('can:'.StaffPermission::AttendDiningCalls->value)->group(function () {
            Route::post('chamados/{call}/assumir', [DiningController::class, 'claim'])->whereNumber('call')->name('calls.claim');
            Route::post('chamados/{call}/concluir', [DiningController::class, 'complete'])->whereNumber('call')->name('calls.complete');
        });
    });

    Route::middleware('can:'.StaffPermission::ViewCatalog->value)->group(function () {
        Route::get('catalogo', [CatalogController::class, 'index'])->name('catalog.index');
        Route::get('catalogo/{product}', [CatalogController::class, 'show'])->whereNumber('product')->name('catalog.show');
        Route::post('catalogo/{product}/cotacao', [CatalogController::class, 'quote'])->whereNumber('product')->name('catalog.quote');
        Route::get('catalogo/{product}/foto', [CatalogPhotoController::class, 'show'])->whereNumber('product')->name('catalog.photo');
    });
    Route::prefix('admin/catalogo')->name('catalog.admin.')->middleware('can:'.StaffPermission::ManageCatalog->value)->group(function () {
        Route::redirect('/', '/admin/catalogo/produtos')->name('home');
        Route::get('{resource}', [CatalogAdminController::class, 'index'])->name('index');
        Route::get('{resource}/criar', [CatalogAdminController::class, 'create'])->name('create');
        Route::get('{resource}/{id}/editar', [CatalogAdminController::class, 'edit'])->whereNumber('id')->name('edit');
        Route::post('{resource}', [CatalogAdminController::class, 'store'])->name('store');
        Route::put('{resource}/{id}', [CatalogAdminController::class, 'update'])->whereNumber('id')->name('update');
    });
    Route::middleware('can:'.StaffPermission::ManageCatalog->value)->group(function () {
        Route::post('admin/produtos/{product}/foto', [CatalogPhotoController::class, 'store'])->whereNumber('product')->name('catalog.photo.store');
        Route::delete('admin/produtos/{product}/foto', [CatalogPhotoController::class, 'destroy'])->whereNumber('product')->name('catalog.photo.destroy');
    });

    foreach ([
        'administration' => ['administracao', StaffPermission::Administration],
        'delivery' => ['entregas', StaffPermission::Delivery],
    ] as $name => [$path, $permission]) {
        Route::get('areas/'.$path, WorkspaceController::class)
            ->defaults('permission', $permission->value)
            ->middleware('can:'.$permission->value)->name('workspace.'.$name);
    }

    Route::prefix('admin/equipe')->name('staff.')->middleware('can:'.StaffPermission::ManageStaff->value)->group(function () {
        Route::get('/', [StaffAccountController::class, 'index'])->name('index');
        Route::get('criar', [StaffAccountController::class, 'create'])->name('create');
        Route::get('{user}/editar', [StaffAccountController::class, 'edit'])->name('edit');
        Route::middleware('throttle:staff-sensitive')->group(function () {
            Route::post('/', [StaffAccountController::class, 'store'])->name('store');
            Route::put('{user}', [StaffAccountController::class, 'update'])->name('update');
            Route::post('{user}/pin', [StaffAccountController::class, 'resetPin'])->name('reset-pin');
            Route::post('{user}/ativar', [StaffAccountController::class, 'activate'])->name('activate');
            Route::post('{user}/desativar', [StaffAccountController::class, 'disable'])->name('disable');
            Route::post('{user}/sessoes', [StaffAccountController::class, 'revokeSessions'])->name('revoke-sessions');
        });
    });
});

require __DIR__.'/settings.php';
