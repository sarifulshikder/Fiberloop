<?php

use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PackageChangeRequestController;
use App\Http\Controllers\Api\UsageController;
use App\Http\Controllers\Customer\AuthController;
use App\Http\Controllers\Customer\ChatController as WebChatController;
use App\Http\Controllers\Customer\InvoiceController;
use App\Http\Controllers\Customer\PaymentController;
use App\Http\Controllers\Customer\PortalController;
use App\Http\Controllers\Customer\ProfileController;
use App\Http\Controllers\Customer\TicketController;
use Illuminate\Support\Facades\Route;

// Customer Portal Routes
Route::prefix('customer')->name('customer.')->group(function () {
    // Authentication
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->name('login.post');
        Route::get('/register', [AuthController::class, 'showRegistrationForm'])->name('register');
        Route::post('/register', [AuthController::class, 'register'])->name('register.post');
        Route::get('/password/reset', [AuthController::class, 'showPasswordResetForm'])->name('password.reset');
        Route::post('/password/reset', [AuthController::class, 'sendResetLink'])->name('password.reset.post');
        Route::get('/password/reset/{token}', [AuthController::class, 'showResetForm'])->name('password.reset.token');
        Route::post('/password/reset/{token}', [AuthController::class, 'resetPassword'])->name('password.reset.update');
    });

    // Authenticated routes
    Route::middleware(['auth:sanctum,web', 'ability:customer'])->group(function () {
        // Dashboard
        Route::get('/dashboard', [PortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/', fn () => redirect()->route('customer.dashboard'))->name('home');

        // Profile
        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/edit', [ProfileController::class, 'edit'])->name('edit');
            Route::put('/update', [ProfileController::class, 'update'])->name('update');
            Route::get('/password', [ProfileController::class, 'showPasswordForm'])->name('password');
            Route::put('/password', [ProfileController::class, 'updatePassword'])->name('password.update');
        });

        // Invoices
        Route::prefix('invoices')->name('invoices.')->group(function () {
            Route::get('/', [InvoiceController::class, 'index'])->name('index');
            Route::get('/{invoice}', [InvoiceController::class, 'show'])->name('show');
            Route::get('/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])->name('pdf');
        });

        // Payments
        Route::prefix('payments')->name('payments.')->group(function () {
            Route::get('/', [PaymentController::class, 'index'])->name('index');
            Route::get('/create', [PaymentController::class, 'create'])->name('create');
            Route::post('/store', [PaymentController::class, 'store'])->name('store');
            Route::get('/{payment}', [PaymentController::class, 'show'])->name('show');
            Route::get('/success', [PaymentController::class, 'paymentSuccess'])->name('success');
            Route::get('/fail', [PaymentController::class, 'paymentFail'])->name('fail');
        });

        // Tickets
        Route::prefix('tickets')->name('tickets.')->group(function () {
            Route::get('/', [TicketController::class, 'index'])->name('index');
            Route::get('/create', [TicketController::class, 'create'])->name('create');
            Route::post('/store', [TicketController::class, 'store'])->name('store');
            Route::get('/{ticket}', [TicketController::class, 'show'])->name('show');
            Route::post('/{ticket}/reply', [TicketController::class, 'reply'])->name('reply');
            Route::post('/{ticket}/close', [TicketController::class, 'close'])->name('close');
        });

        // Usage
        Route::prefix('usage')->name('usage.')->group(function () {
            Route::get('/', [UsageController::class, 'webIndex'])->name('index');
            Route::get('/sessions', [UsageController::class, 'webSessions'])->name('sessions');
        });

        // Package Change Requests
        Route::prefix('package-change')->name('package-change.')->group(function () {
            Route::get('/', [PackageChangeRequestController::class, 'webIndex'])->name('index');
            Route::get('/create', [PackageChangeRequestController::class, 'webCreate'])->name('create');
            Route::post('/store', [PackageChangeRequestController::class, 'webStore'])->name('store');
            Route::get('/{request}', [PackageChangeRequestController::class, 'webShow'])->name('show');
            Route::post('/{request}/cancel', [PackageChangeRequestController::class, 'webCancel'])->name('cancel');
        });

        // Chat
        Route::prefix('chat')->name('chat.')->group(function () {
            Route::get('/', [WebChatController::class, 'webIndex'])->name('index');
            Route::get('/{conversation}', [WebChatController::class, 'webShow'])->name('show');
            Route::post('/{conversation}/messages', [WebChatController::class, 'webStore'])->name('messages');
        });

        // Notifications
        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [NotificationController::class, 'webIndex'])->name('index');
            Route::post('/fcm', [NotificationController::class, 'webRegisterFcm'])->name('fcm');
        });

        // Logout
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    });
});
