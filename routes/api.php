<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\{
    CategoryController,
    NotchpayWebhookController,
    ProductReviewController,
    OrderController,
    WishlistController,
    WalletController,
    TransactionController,
    InvoiceController,
    DisputeController,
};


use App\Http\Controllers\Api\Marketplace\{ProductController, ShopController};

// =====================================================
// ROUTES PUBLIQUES
// =====================================================
Route::apiResource('categories', CategoryController::class);
Route::get('products/{product}/reviews', [ProductReviewController::class, 'index']);
Route::post('/products/upload', [ProductController::class, 'upload']);

// Facture (téléchargement public via token)
Route::get('/invoices/download/{token}', [InvoiceController::class, 'download'])->name('invoice.download');

// Suivi de commande (public avec email)
Route::get('/track-order/{orderNumber}', [OrderController::class, 'trackOrder']);
Route::get('/tracking/{orderNumber}', [OrderController::class, 'getTrackingData']);

// =====================================================
// WEBHOOKS (sans auth)
// =====================================================
Route::match(['get', 'post'], '/webhooks/notchpay', [NotchpayWebhookController::class, 'handle']);
Route::match(['get', 'post'], '/orders/notchpay/webhook', [NotchpayWebhookController::class, 'handle']);
Route::post('/verify-payment', [OrderController::class, 'verifyAndConfirmPayment']);
Route::match(['get', 'post'], '/wallet/deposit/callback', [WalletController::class, 'depositCallback']);

// =====================================================
// ROUTES AUTHENTIFIÉES
// =====================================================
Route::middleware('auth:sanctum')->group(function () {
    // BOUTIQUE & PRODUITS
    Route::get('/my-shop/profile', [ShopController::class, 'myShopProfile']);
    Route::get('/my-shop/products', [ProductController::class, 'myProducts']);
    Route::apiResource('shops', ShopController::class);
    Route::apiResource('products', ProductController::class);
    Route::apiResource('wishlist', WishlistController::class);

    // REVIEWS
    Route::post('products/reviews', [ProductReviewController::class, 'store']);
    Route::put('products/reviews/{id}', [ProductReviewController::class, 'update']);
    Route::delete('products/reviews/{id}', [ProductReviewController::class, 'destroy']);

    // WALLET
    Route::prefix('wallet')->group(function () {
        Route::get('/security', [WalletController::class, 'securityStatus']);
        Route::post('/pin', [WalletController::class, 'setupPin']);
        Route::get('/balance', [WalletController::class, 'balance']);
        Route::post('/deposit', [WalletController::class, 'deposit']);
        Route::post('/withdraw', [WalletController::class, 'withdraw']);
        Route::post('/transfer', [WalletController::class, 'transfer']);
        Route::get('/history', [WalletController::class, 'history']);
        Route::get('/verify/{reference}', [WalletController::class, 'verifyPayment']);
        Route::get('/callback', [WalletController::class, 'callback']);
    });

    // TRANSACTIONS
    Route::apiResource('transactions', TransactionController::class)->only(['index', 'show']);

    // FACTURES (authentifié)
    Route::prefix('invoices')->group(function () {
        Route::get('/order/{order}', [InvoiceController::class, 'show']);
        Route::post('/generate/{order}', [InvoiceController::class, 'generate']);
    });

    // COMMANDES CLIENT
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'myOrders']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/{order}', [OrderController::class, 'show']);
        Route::post('/{order}/pay/wallet', [OrderController::class, 'payWithWallet']);
        Route::post('/{order}/pay/notchpay', [OrderController::class, 'payWithNotchPay']);
        Route::post('/{order}/confirm-delivery', [OrderController::class, 'confirmDelivery']);
        Route::post('/{order}/cancel', [OrderController::class, 'cancelOrder']);
    });

    // COMMANDES VENDEUR
    Route::prefix('seller/orders')->group(function () {
        Route::get('/', [OrderController::class, 'sellerOrders']);
        Route::put('/{order}/prepare', [OrderController::class, 'prepareOrder']);
        Route::put('/{order}/ship', [OrderController::class, 'shipOrder']);
        Route::put('/{order}/delivery-position', [OrderController::class, 'updateDeliveryPosition']);
        Route::post('/{order}/confirm-delivery', [OrderController::class, 'confirmDelivery']);
    });

    // LITIGES
    Route::prefix('disputes')->group(function () {
        Route::get('/', [DisputeController::class, 'index']); // client
        Route::post('/', [DisputeController::class, 'store']);
        Route::get('/seller', [DisputeController::class, 'sellerDisputes']);
        Route::get('/admin', [DisputeController::class, 'adminDisputes']);
        Route::get('/{dispute}', [DisputeController::class, 'show']);
        Route::post('/{dispute}/respond', [DisputeController::class, 'respond']);
        Route::post('/{dispute}/resolve', [DisputeController::class, 'resolve']);
    });

    // NOTIFICATIONS
    Route::prefix('notifications')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\NotificationController::class, 'index']);
        Route::get('/unread-count', [App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
        Route::post('/mark-all-read', [App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
        Route::post('/{id}/read', [App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    });

    // PUSH NOTIFICATIONS
    Route::post('/push/subscribe', [App\Http\Controllers\Api\PushSubscriptionController::class, 'store']);
    Route::post('/push/unsubscribe', [App\Http\Controllers\Api\PushSubscriptionController::class, 'destroy']);
    Route::get('/push/vapid-key', [App\Http\Controllers\Api\PushSubscriptionController::class, 'vapidKey']);
});

// =====================================================
// FICHIERS EXTERNES
// =====================================================
require __DIR__.'/users/user.php';
require __DIR__.'/community/community.php';
require __DIR__.'/marketplace/marketplace.php';
require __DIR__.'/AI/ai.php';
require __DIR__.'/mission/mission.php';
require __DIR__.'/moderation/moderation.php';
require __DIR__.'/auth/auth.php';
require __DIR__.'/admin/admin.php';
