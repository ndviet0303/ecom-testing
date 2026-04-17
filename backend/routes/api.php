<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Api\Admin\CouponAdminController;
use App\Http\Controllers\Api\Admin\LowStockInventoryController;
use App\Http\Controllers\Api\Admin\OrderAdminController;
use App\Http\Controllers\Api\Admin\ReturnRequestAdminController;
use App\Http\Controllers\Api\Admin\ShippingZoneAdminController;
use App\Http\Controllers\Api\BuildCompatibilityController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CompareController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\NewsletterController;
use App\Http\Controllers\Api\OrderCancelController;
use App\Http\Controllers\Api\Payments\OrderSePayQrController;
use App\Http\Controllers\Api\Payments\SePayPlaceholderController;
use App\Http\Controllers\Api\Webhooks\SePayWebhookController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RecentViewController;
use App\Http\Controllers\Api\ReturnRequestController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ShippingZoneController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['throttle:120,1'])->prefix('v1')->group(function (): void {
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}/reviews', [ReviewController::class, 'forProduct']);
    Route::get('products/{product}', [ProductController::class, 'show']);

    Route::get('coupons', [CouponController::class, 'index']);
    Route::get('coupons/{code}/preview', [CouponController::class, 'preview']);

    Route::get('shipping-zones', [ShippingZoneController::class, 'index']);

    Route::post('build/validate', [BuildCompatibilityController::class, 'validateBuild']);

    Route::post('newsletter/subscribe', [NewsletterController::class, 'subscribe']);
    Route::post('newsletter/unsubscribe', [NewsletterController::class, 'unsubscribe']);

    Route::get('payments/sepay', SePayPlaceholderController::class);
    Route::post('webhooks/sepay', SePayWebhookController::class);

    Route::get('cart', [CartController::class, 'show']);
    Route::post('cart/items', [CartController::class, 'addItem']);
    Route::put('cart/items/{productId}', [CartController::class, 'updateItem']);
    Route::delete('cart/items/{productId}', [CartController::class, 'removeItem']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('cart/merge', [CartController::class, 'merge']);
        Route::post('checkout', [CheckoutController::class, 'store']);

        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
        Route::get('orders/{order}/sepay-qr', [OrderSePayQrController::class, 'show']);
        Route::post('orders/{order}/cancel', [OrderCancelController::class, 'store']);

        Route::get('compare', [CompareController::class, 'index']);
        Route::post('compare/{product}', [CompareController::class, 'store']);
        Route::delete('compare/{product}', [CompareController::class, 'destroy']);

        Route::get('recent-views', [RecentViewController::class, 'index']);
        Route::post('recent-views/{product}', [RecentViewController::class, 'store']);

        Route::apiResource('addresses', AddressController::class)->except(['create', 'edit']);

        Route::get('wishlist', [WishlistController::class, 'index']);
        Route::post('wishlist/{product}', [WishlistController::class, 'store']);
        Route::delete('wishlist/{product}', [WishlistController::class, 'destroy']);

        Route::post('products/{product}/reviews', [ReviewController::class, 'store']);

        Route::get('return-requests', [ReturnRequestController::class, 'index']);
        Route::get('return-requests/{returnRequest}', [ReturnRequestController::class, 'show']);
        Route::post('return-requests', [ReturnRequestController::class, 'store']);
    });

    Route::middleware(['auth:sanctum', 'staff'])->prefix('admin')->group(function (): void {
        Route::get('orders', [OrderAdminController::class, 'index'])->name('api/v1/admin/orders');
        Route::patch('orders/{order}/status', [OrderAdminController::class, 'updateStatus'])->name('api/v1/admin/orders/status');
        Route::patch('orders/{order}/fulfillment', [OrderAdminController::class, 'updateFulfillment'])->name('api/v1/admin/orders/fulfillment');
        Route::get('inventory/low-stock', [LowStockInventoryController::class, 'index']);
        Route::get('audit-logs', [AdminAuditLogController::class, 'index']);
        Route::get('return-requests', [ReturnRequestAdminController::class, 'index']);
        Route::patch('return-requests/{returnRequest}', [ReturnRequestAdminController::class, 'update']);
    });

    Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function (): void {
        Route::post('products', [ProductController::class, 'store']);
        Route::put('products/{product}', [ProductController::class, 'update']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);

        Route::get('coupons', [CouponAdminController::class, 'index']);
        Route::post('coupons', [CouponAdminController::class, 'store']);
        Route::put('coupons/{coupon}', [CouponAdminController::class, 'update']);
        Route::delete('coupons/{coupon}', [CouponAdminController::class, 'destroy']);

        Route::get('shipping-zones', [ShippingZoneAdminController::class, 'index']);
        Route::post('shipping-zones', [ShippingZoneAdminController::class, 'store']);
        Route::put('shipping-zones/{shipping_zone}', [ShippingZoneAdminController::class, 'update']);
        Route::delete('shipping-zones/{shipping_zone}', [ShippingZoneAdminController::class, 'destroy']);
    });
});
