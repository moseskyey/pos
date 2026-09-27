<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BranchSwitchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// ---------------------------------------------------------------- Guest ----
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.store');
});

Route::post('/preferences/locale', [ProfileController::class, 'locale'])->name('preferences.locale');
Route::get('/verify/{number}', [ReceiptController::class, 'verify'])->name('receipts.verify');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:10,1')->name('two-factor.store');
});

// -------------------------------------------------------- Authenticated ----
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/search', SearchController::class)->name('search');
    Route::get('/files/{path}', [FileController::class, 'show'])->where('path', '.*')->name('files.show');
    Route::post('/branch/switch', BranchSwitchController::class)->name('branch.switch');

    // Profile & preferences
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::put('/profile/pin', [ProfileController::class, 'pin'])->name('profile.pin');
    Route::post('/profile/two-factor', [ProfileController::class, 'enableTwoFactor'])->name('two-factor.enable');
    Route::post('/profile/two-factor/confirm', [ProfileController::class, 'confirmTwoFactor'])->name('two-factor.confirm');
    Route::delete('/profile/two-factor', [ProfileController::class, 'disableTwoFactor'])->name('two-factor.disable');
    Route::post('/preferences/theme', [ProfileController::class, 'theme'])->name('preferences.theme');
    Route::post('/lock', [ProfileController::class, 'lock'])->name('lock');
    Route::post('/lock/verify', [ProfileController::class, 'verifyPin'])->middleware('throttle:10,1')->name('lock.verify');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    // Branches & registers
    Route::resource('branches', BranchController::class);
    Route::post('/branches/{branch}/registers', [BranchController::class, 'storeRegister'])->name('registers.store');
    Route::put('/branches/{branch}/registers/{register}', [BranchController::class, 'updateRegister'])->name('registers.update');

    // Users, roles, activity, settings
    Route::resource('users', UserController::class);
    Route::post('/users/{user}/impersonate', [UserController::class, 'impersonate'])->name('users.impersonate');
    Route::post('/impersonate/leave', [UserController::class, 'leaveImpersonation'])->name('impersonate.leave');
    Route::resource('roles', RoleController::class)->except('show');
    Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');
    Route::get('/settings/{group?}', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings/{group}', [SettingsController::class, 'update'])->name('settings.update');

    // Backups
    Route::middleware('can:backups.manage')->controller(BackupController::class)->group(function () {
        Route::get('/backups', 'index')->name('backups.index');
        Route::post('/backups', 'store')->middleware('throttle:5,1')->name('backups.store');
        Route::get('/backups/{file}', 'download')->name('backups.download');
        Route::delete('/backups/{file}', 'destroy')->name('backups.destroy');
    });

    require __DIR__.'/modules.php';
});
