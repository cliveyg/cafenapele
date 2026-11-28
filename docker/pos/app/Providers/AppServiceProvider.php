<?php

namespace App\Providers;

use App\Http\Controllers\AdminLoginController;
use App\Http\Controllers\PosController;
use App\Http\Requests\StaffRequest;
use Igniter\Cart\Http\Middleware\CartMiddleware;
use Igniter\Flame\Exception\ApplicationException;
use Igniter\Local\Http\Middleware\CheckLocation;
use Igniter\User\Http\Controllers\Login;
use Igniter\User\Http\Requests\UserRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Login::class, AdminLoginController::class);
        $this->app->bind(UserRequest::class, StaffRequest::class);
    }

    public function boot(): void
    {
        Route::middleware(['web', 'igniter', CheckLocation::class, CartMiddleware::class])
            ->prefix('pos')
            ->name('pos.')
            ->group(function (): void {
                Route::get('/orders', [PosController::class, 'index'])->name('orders');
                Route::get('/orders/{order}', [PosController::class, 'show'])->name('orders.show');
                Route::post('/orders', [PosController::class, 'save'])->name('save');
                Route::post('/orders/{order}/edit', [PosController::class, 'edit'])->name('edit');
                Route::post('/orders/{order}/paid', [PosController::class, 'paid'])->name('paid');
                Route::post('/orders/{order}/items/{orderMenu}/remove', [PosController::class, 'removeItem'])->name('items.remove');
                Route::post('/new', [PosController::class, 'newOrder'])->name('new');
            });

        Event::listen('igniter.orange.checkCheckoutSecurity', function (): void {
            throw new ApplicationException('Customer checkout is disabled. Use the POS to place orders.');
        });
    }
}
