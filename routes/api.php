<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PackageChangeRequestController;
use App\Http\Controllers\Api\PayNowController;
use App\Http\Controllers\Api\TicketApiController;
use App\Http\Controllers\Api\UsageController;
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
    Route::prefix('customer')->middleware(['auth:sanctum'])->group(function () {
        // Customer profile
        Route::get('/profile', [CustomerController::class, 'profile']);
        Route::put('/profile', [CustomerController::class, 'updateProfile']);

        // Subscriptions
        Route::get('/subscriptions', [CustomerController::class, 'subscriptions']);
        Route::get('/subscriptions/active', [CustomerController::class, 'activeSubscription']);

        // Invoices
        Route::get('/invoices', [CustomerController::class, 'invoices']);
        Route::get('/invoices/{invoice}', [CustomerController::class, 'invoice']);

        // Payments
        Route::get('/payments', [CustomerController::class, 'payments']);
        Route::get('/payments/history', [PayNowController::class, 'history']);

        // Tickets
        Route::get('/tickets', [TicketApiController::class, 'index']);
        Route::get('/tickets/{ticket}', [TicketApiController::class, 'show']);
        Route::post('/tickets', [TicketApiController::class, 'store']);

        // Usage data
        Route::prefix('usage')->group(function () {
            Route::get('/current', [UsageController::class, 'current']);
            Route::get('/realtime', [UsageController::class, 'realtime']);
            Route::get('/sessions', [UsageController::class, 'sessions']);
        });

        // Package change requests
        Route::prefix('package-change-requests')->group(function () {
            Route::get('/', [PackageChangeRequestController::class, 'index']);
            Route::get('/{package_change_request}', [PackageChangeRequestController::class, 'show']);
            Route::post('/', [PackageChangeRequestController::class, 'store']);
            Route::post('/{package_change_request}/cancel', [PackageChangeRequestController::class, 'cancel']);
            Route::get('/available-packages', [PackageChangeRequestController::class, 'availablePackages']);
        });

        // Pay Now / Payment endpoints
        Route::prefix('payments')->group(function () {
            Route::get('/options', [PayNowController::class, 'getPaymentOptions']);
            Route::post('/initiate', [PayNowController::class, 'initiatePayment']);
            Route::get('/status/{payment}', [PayNowController::class, 'getPaymentStatus']);
        });

        // Notifications (FCM)
        Route::prefix('notifications')->group(function () {
            Route::post('/fcm/register', [NotificationController::class, 'registerFcmToken']);
            Route::get('/preferences', [NotificationController::class, 'preferences']);
            Route::put('/preferences', [NotificationController::class, 'updatePreferences']);
        });

        // Live Chat
        Route::prefix('chat')->group(function () {
            Route::get('/conversations', [ChatController::class, 'conversations']);
            Route::get('/conversations/{conversation}', [ChatController::class, 'getConversation']);
            Route::get('/conversations/{conversation}/messages', [ChatController::class, 'messages']);
            Route::post('/conversations', [ChatController::class, 'startConversation']);
            Route::post('/conversations/{conversation}/messages', [ChatController::class, 'sendMessage']);
            Route::post('/conversations/{conversation}/close', [ChatController::class, 'closeConversation']);
            Route::get('/unread-count', [ChatController::class, 'unreadCount']);
            Route::get('/online-agents', [ChatController::class, 'onlineAgents']);
        });

        // Data export and deletion (GDPR)
        Route::prefix('data')->group(function () {
            Route::post('/export/request', [\App\Http\Controllers\Api\CustomerDataController::class, 'requestExport']);
            Route::get('/export/status/{requestId}', [\App\Http\Controllers\Api\CustomerDataController::class, 'exportStatus']);
            Route::get('/export/download/{requestId}', [\App\Http\Controllers\Api\CustomerDataController::class, 'downloadExport']);
            Route::post('/deletion/request', [\App\Http\Controllers\Api\CustomerDataController::class, 'requestDeletion']);
            Route::post('/deletion/confirm/{requestId}/{confirmationToken}', [\App\Http\Controllers\Api\CustomerDataController::class, 'confirmDeletion']);
            Route::get('/deletion/status/{requestId}', [\App\Http\Controllers\Api\CustomerDataController::class, 'deletionStatus']);
        });
    });

    // Reseller-specific routes
    Route::prefix('reseller')->middleware(['auth:sanctum'])->group(function () {
        // Reseller dashboard
        Route::get('/dashboard', [\App\Http\Controllers\Api\ResellerController::class, 'dashboard']);

        // Customers
        Route::get('/customers', [\App\Http\Controllers\Api\ResellerController::class, 'customers']);
        Route::post('/customers', [\App\Http\Controllers\Api\ResellerController::class, 'createCustomer']);

        // Subscriptions
        Route::get('/subscriptions', [\App\Http\Controllers\Api\ResellerController::class, 'subscriptions']);

        // Earnings
        Route::get('/earnings', [\App\Http\Controllers\Api\ResellerController::class, 'earnings']);

        // Customer inventory access
        Route::prefix('inventory')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\InventoryController::class, 'customerInventory']);
            Route::get('/{uuid}', [\App\Http\Controllers\Api\InventoryController::class, 'customerInventoryItem']);
            Route::get('/{uuid}/history', [\App\Http\Controllers\Api\InventoryController::class, 'customerInventoryHistory']);
        });
    });

    // Staff inventory management routes
    Route::prefix('inventory')->middleware(['auth:sanctum'])->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\InventoryController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\InventoryController::class, 'store']);
        Route::get('/{uuid}', [\App\Http\Controllers\Api\InventoryController::class, 'show']);
        Route::put('/{uuid}', [\App\Http\Controllers\Api\InventoryController::class, 'update']);
        Route::delete('/{uuid}', [\App\Http\Controllers\Api\InventoryController::class, 'destroy']);

        // Stock transactions
        Route::prefix('transactions')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\InventoryController::class, 'transactions']);
            Route::get('/{uuid}', [\App\Http\Controllers\Api\InventoryController::class, 'transactionShow']);
        });
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
