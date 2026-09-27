<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\UserDirectoryController;
use Illuminate\Support\Facades\Route;

/*
| Platform administration (/admin, names "admin.*"). Central database only.
| Loaded with the "web" middleware group from bootstrap/app.php.
*/

Route::middleware('guest:admin')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login');
});

Route::middleware('admin')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

    // Businesses
    Route::get('/businesses', [TenantController::class, 'index'])->name('tenants.index');
    Route::get('/businesses/create', [TenantController::class, 'create'])->name('tenants.create');
    Route::post('/businesses', [TenantController::class, 'store'])->middleware('throttle:20,1')->name('tenants.store');
    Route::get('/businesses/{tenant}', [TenantController::class, 'show'])->withTrashed()->name('tenants.show');
    Route::get('/businesses/{tenant}/edit', [TenantController::class, 'edit'])->name('tenants.edit');
    Route::put('/businesses/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
    Route::delete('/businesses/{tenant}', [TenantController::class, 'destroy'])->name('tenants.destroy');
    Route::post('/businesses/{tenant}/restore', [TenantController::class, 'restore'])->withTrashed()->name('tenants.restore');
    Route::delete('/businesses/{tenant}/purge', [TenantController::class, 'purge'])->withTrashed()->middleware('admin:super')->name('tenants.purge');
    Route::post('/businesses/{tenant}/extend', [TenantController::class, 'extend'])->name('tenants.extend');
    Route::post('/businesses/{tenant}/plan', [TenantController::class, 'changePlan'])->name('tenants.plan');
    Route::post('/businesses/{tenant}/payments', [TenantController::class, 'recordPayment'])->name('tenants.payments.store');
    Route::post('/businesses/{tenant}/suspend', [TenantController::class, 'suspend'])->name('tenants.suspend');
    Route::post('/businesses/{tenant}/unsuspend', [TenantController::class, 'unsuspend'])->name('tenants.unsuspend');
    Route::post('/businesses/{tenant}/impersonate', [TenantController::class, 'impersonate'])->name('tenants.impersonate');
    Route::post('/businesses/{tenant}/users/{user}/password', [TenantController::class, 'resetUserPassword'])->whereNumber('user')->name('tenants.users.password');
    Route::post('/businesses/{tenant}/users/{user}/toggle', [TenantController::class, 'toggleUser'])->whereNumber('user')->name('tenants.users.toggle');

    Route::get('/users', UserDirectoryController::class)->name('users.index');

    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments/{payment}/verify', [PaymentController::class, 'verify'])->middleware('throttle:30,1')->name('payments.verify');
    Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund'])->middleware('admin:super')->name('payments.refund');

    Route::resource('plans', PlanController::class)->except('show');
    Route::resource('announcements', AnnouncementController::class)->except('show');
    Route::get('/activity', ActivityController::class)->name('activity.index');

    Route::middleware('admin:super')->group(function () {
        Route::resource('admins', AdminUserController::class)->except('show');
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    });
});
