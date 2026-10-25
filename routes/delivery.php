<?php

use App\Enums\StaffPermission;
use App\Http\Controllers\DeliveryCashController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DeliveryCoverageController;
use App\Http\Middleware\ResolveDeliveryGuest;
use Illuminate\Support\Facades\Route;

Route::prefix('entrega')->name('delivery.public.')->middleware(['throttle:delivery-read', ResolveDeliveryGuest::class])->group(function () {
    Route::get('/', [DeliveryController::class, 'menu'])->name('menu');
    Route::get('confirmar/{intent}', [DeliveryController::class, 'review'])->whereUuid('intent')->name('review');
    Route::get('pedidos/{reference}', [DeliveryController::class, 'receipt'])->whereUuid('reference')->name('receipt');
    Route::get('fotos/{product}', [DeliveryController::class, 'photo'])->whereNumber('product')->name('photo');
    Route::middleware('throttle:delivery-write')->group(function () {
        Route::post('previa', [DeliveryController::class, 'preview'])->name('preview');
        Route::post('confirmar/{intent}', [DeliveryController::class, 'confirm'])->whereUuid('intent')->name('confirm');
        Route::post('pedidos/{reference}/realtime/auth', [DeliveryController::class, 'realtime'])->whereUuid('reference')->name('realtime');
    });
});

Route::middleware(['auth', 'can:'.StaffPermission::Delivery->value])->group(function () {
    Route::get('areas/entregas', [DeliveryController::class, 'index'])->name('workspace.delivery');
    Route::prefix('entregas')->name('delivery.')->group(function () {
        Route::get('/', [DeliveryController::class, 'index'])->name('index');
        Route::middleware('can:'.StaffPermission::ManageDelivery->value)->group(function () {
            Route::get('cobertura', [DeliveryCoverageController::class, 'index'])->name('areas.index');
            Route::post('cobertura', [DeliveryCoverageController::class, 'save'])->name('areas.store');
            Route::put('cobertura/{area}', [DeliveryCoverageController::class, 'save'])->whereNumber('area')->name('areas.update');
            Route::post('servico', [DeliveryCoverageController::class, 'service'])->name('service');
        });
        Route::middleware('can:'.StaffPermission::PlaceDeliveryOrder->value)->group(function () {
            Route::get('pedido', [DeliveryController::class, 'menu'])->name('menu');
            Route::post('previa', [DeliveryController::class, 'preview'])->name('preview');
            Route::get('confirmar/{intent}', [DeliveryController::class, 'review'])->whereUuid('intent')->name('review');
            Route::post('confirmar/{intent}', [DeliveryController::class, 'confirm'])->whereUuid('intent')->name('confirm');
        });
        Route::get('pedidos/{order}', [DeliveryController::class, 'show'])->whereNumber('order')->name('show');
        Route::post('pedidos/{order}/entregador', [DeliveryController::class, 'assign'])->whereNumber('order')->middleware('can:'.StaffPermission::DispatchDelivery->value)->name('assign');
    });
    Route::prefix('caixa/entregas/{session}')->whereNumber('session')->name('cash.delivery.')->group(function () {
        Route::get('/', [DeliveryCashController::class, 'account'])->name('account');
        Route::get('comprovante', [DeliveryCashController::class, 'receipt'])->name('receipt');
        Route::middleware('can:'.StaffPermission::ReceiveDeliveryPayment->value)->group(function () {
            Route::post('previa', [DeliveryCashController::class, 'preview'])->name('preview');
            Route::get('revisar/{intent}', [DeliveryCashController::class, 'review'])->whereUuid('intent')->name('review');
            Route::post('confirmar/{intent}', [DeliveryCashController::class, 'confirm'])->whereUuid('intent')->name('confirm');
        });
    });
});
