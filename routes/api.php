<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\{
    AuthController,
    CategoryController,
    ProductReviewController,
    OrderController,
    WishlistController,
    WalletController,
    TransactionController,
    NotchpayWebhookController,
    InvoiceController,
    DisputeController,
};


use App\Http\Controllers\Api\Marketplace\{ProductController, ShopController};
use App\Http\Controllers\Api\Admin\{
    AdminProductController,
    AdminUserController,
    AdminActivityController,
    AdminAnalyticsController,
    AdminCategoryController
};

// =====================================================
// AUTH PUBLIQUE
// =====================================================
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'registerUser']);
    Route::post('/register/admin', [AuthController::class, 'registerAdmin']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// OAuth Google
Route::get('/auth/google', [AuthController::class, 'redirect']);
Route::get('/auth/google/callback', [AuthController::class, 'callback']);

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
// LOGIN FALLBACK
// =====================================================
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthenticated'], 401);
})->name('login');

// =====================================================
// WEBHOOKS (sans auth)
// =====================================================
Route::match(['get', 'post'], '/webhooks/notchpay', [OrderController::class, 'handleNotchPayWebhook']);
Route::match(['get', 'post'], '/orders/notchpay/webhook', [OrderController::class, 'handleNotchPayWebhook']);
Route::post('/verify-payment', [OrderController::class, 'verifyAndConfirmPayment']);
Route::match(['get', 'post'], '/wallet/deposit/callback', [WalletController::class, 'depositCallback']);

// =====================================================
// ROUTES AUTHENTIFIÉES
// =====================================================
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());

    // AUTH (email verification + token management)
    Route::prefix('auth')->group(function () {
        Route::post('/email/resend', [AuthController::class, 'sendVerificationCode']);
        Route::post('/email/verify', [AuthController::class, 'verifyEmail']);
        Route::post('/refresh', [AuthController::class, 'refreshToken']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout/all', [AuthController::class, 'logoutAll']);
    });

    // AUTH + VERIFIED EMAIL (profile & password)
    Route::middleware('verified.email')->prefix('auth')->group(function () {
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/password', [AuthController::class, 'changePassword']);
    });

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

    // ADMIN
    // Route::prefix('admin')->group(function () {
    //     Route::get('products/pending', [AdminProductController::class, 'pendingProducts']);
    //     Route::post('products/{id}/approve', [AdminProductController::class, 'approveProduct']);
    //     Route::post('products/{id}/reject', [AdminProductController::class, 'rejectProduct']);
    //     Route::get('products', [AdminProductController::class, 'allProducts']);
    //     Route::delete('products/{id}', [AdminProductController::class, 'deleteProduct']);

    //     Route::get('users', [AdminUserController::class, 'allUsers']);
    //     Route::post('users/{id}/suspend', [AdminUserController::class, 'suspendUser']);
    //     Route::post('users/{id}/unsuspend', [AdminUserController::class, 'unsuspendUser']);
    //     Route::delete('users/{id}', [AdminUserController::class, 'deleteUser']);

    //     Route::get('activities', [AdminActivityController::class, 'getActivityLog']);
    //     Route::get('analytics', [AdminAnalyticsController::class, 'getAnalytics']);
    //     Route::apiResource('categories', AdminCategoryController::class);

    //     Route::get('/orders', [OrderController::class, 'adminOrders']);
    // });

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

// =====================================================
// CORS & OPTIONS
// =====================================================
Route::options('/{any}', function () {
    return response()->json([], 200)
        ->header('Access-Control-Allow-Origin', 'http://localhost:3000')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN')
        ->header('Access-Control-Allow-Credentials', 'true');
})->where('any', '.*');

// Route de test CORS
Route::get('/cors-test', function () {
    return response()->json([
        'message' => 'CORS is working!',
        'timestamp' => now(),
        'headers' => request()->headers->all()
    ]);
});