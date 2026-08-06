<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Authentication routes for customers and resellers
Route::prefix('v1')->group(function () {
    // Public routes (no auth required)
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/me', [AuthController::class, 'me']);

    // Customer-specific routes
    Route::prefix('customer')->middleware(['auth:sanctum', 'ability:customer'])->group(function () {
        // Customer profile
        Route::get('/profile', [\App\Http\Controllers\Api\CustomerController::class, 'profile']);
        Route::put('/profile', [\App\Http\Controllers\Api\CustomerController::class, 'updateProfile']);

        // Subscriptions
        Route::get('/subscriptions', [\App\Http\Controllers\Api\CustomerController::class, 'subscriptions']);

        // Invoices
        Route::get('/invoices', [\App\Http\Controllers\Api\CustomerController::class, 'invoices']);
        Route::get('/invoices/{invoice}', [\App\Http\Controllers\Api\CustomerController::class, 'invoice']);

        // Payments
        Route::get('/payments', [\App\Http\Controllers\Api\CustomerController::class, 'payments']);

        // Tickets
        Route::get('/tickets', [\App\Http\Controllers\Api\CustomerController::class, 'tickets']);
        Route::post('/tickets', [\App\Http\Controllers\Api\CustomerController::class, 'createTicket']);
    });

    // Reseller-specific routes
    Route::prefix('reseller')->middleware(['auth:sanctum', 'ability:reseller'])->group(function () {
        // Reseller dashboard
        Route::get('/dashboard', [\App\Http\Controllers\Api\ResellerController::class, 'dashboard']);

        // Customers
        Route::get('/customers', [\App\Http\Controllers\Api\ResellerController::class, 'customers']);
        Route::post('/customers', [\App\Http\Controllers\Api\ResellerController::class, 'createCustomer']);

        // Subscriptions
        Route::get('/subscriptions', [\App\Http\Controllers\Api\ResellerController::class, 'subscriptions']);

        // Earnings
        Route::get('/earnings', [\App\Http\Controllers\Api\ResellerController::class, 'earnings']);
    });
});

// Payment gateway webhooks
Route::prefix('webhooks')->group(function () {
    Route::post('/bkash', [\App\Http\Controllers\Api\Payments\WebhookController::class, 'handleBkash'])->name('api.webhooks.bkash');
    Route::post('/nagad', [\App\Http\Controllers\Api\Payments\WebhookController::class, 'handleNagad'])->name('api.webhooks.nagad');
    Route::post('/sslcommerz', [\App\Http\Controllers\Api\Payments\WebhookController::class, 'handleSSLCommerz'])->name('api.webhooks.sslcommerz');
});

// Manual payment routes for field agents
Route::prefix('payments')->middleware(['auth:sanctum'])->group(function () {
    // Manual/cash payment routes
    Route::prefix('manual')->group(function () {
        Route::get('/outstanding-customers', [\App\Http\Controllers\Api\Payments\ManualPaymentController::class, 'getOutstandingCustomers']);
        Route::post('/record', [\App\Http\Controllers\Api\Payments\ManualPaymentController::class, 'recordPayment']);
        Route::post('/multi-invoice', [\App\Http\Controllers\Api\Payments\ManualPaymentController::class, 'recordMultiInvoicePayment']);
        Route::get('/receipt-number', [\App\Http\Controllers\Api\Payments\ManualPaymentController::class, 'generateReceiptNumber']);
    });

    // Refund routes
    Route::prefix('refunds')->group(function () {
        Route::get('/{payment}/check-eligibility', [\App\Http\Controllers\Api\Payments\RefundController::class, 'checkRefundEligibility']);
        Route::post('/{payment}/process', [\App\Http\Controllers\Api\Payments\RefundController::class, 'processRefund']);
        Route::post('/manual', [\App\Http\Controllers\Api\Payments\RefundController::class, 'processManualRefund']);
        Route::get('/customer/{customer}', [\App\Http\Controllers\Api\Payments\RefundController::class, 'getCustomerRefunds']);
    });

    // Wallet top-up routes
    Route::prefix('wallet')->group(function () {
        Route::get('/balance', [\App\Http\Controllers\Api\Payments\WalletTopUpController::class, 'getMyBalance']);
        Route::get('/balance/{customer}', [\App\Http\Controllers\Api\Payments\WalletTopUpController::class, 'getBalance']);
        Route::post('/topup', [\App\Http\Controllers\Api\Payments\WalletTopUpController::class, 'initiateTopUp']);
        Route::get('/transactions/{customer}', [\App\Http\Controllers\Api\Payments\WalletTopUpController::class, 'getTransactionHistory']);
        Route::get('/minimum-balance', [\App\Http\Controllers\Api\Payments\WalletTopUpController::class, 'getMinimumBalance']);
    });
});

// Test route
Route::get('/', function (Request $request) {
    return response()->json(['message' => 'Fiberloop API', 'version' => '1.0.0']);
});
