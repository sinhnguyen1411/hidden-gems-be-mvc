<?php
use App\Core\Router;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CafeController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\AdvertisingController;
use App\Http\Controllers\CsrfController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\ShopMiddleware;
use App\Http\Middleware\AdminOrShopMiddleware;
use App\Core\Response;
use App\Core\JsonResponse;

/** @var Router $router */
$router = $app['router'];

// Ensure root (web) routes are also loaded when requiring only api.php (e.g., tests)
require __DIR__ . '/web.php';

// API routes start here

// Auth
$router->add('POST','/api/auth/register',[AuthController::class,'register']);
$router->add('POST','/api/auth/login',[AuthController::class,'login']);
$router->add('POST','/api/auth/refresh',[AuthController::class,'refresh']);
$router->add('GET','/api/users',[AuthController::class,'users'],[AuthMiddleware::class,AdminMiddleware::class]);

// Stores (cafes)
$router->add('GET','/api/cafes',[CafeController::class,'index']);
$router->add('GET','/api/cafes/search',[CafeController::class,'search']);
$router->add('GET','/api/cafes/{id}',[CafeController::class,'show']);
$router->add('GET','/api/cafes/{id}/reviews',[ReviewController::class,'list']);
$router->add('POST','/api/cafes/{id}/reviews',[ReviewController::class,'create'], [AuthMiddleware::class]);

// Global search across entities
$router->add('GET','/api/search',[SearchController::class,'global']);

// Store management
$router->add('POST','/api/stores',[StoreController::class,'create'],[AuthMiddleware::class, ShopMiddleware::class]);
$router->add('PATCH','/api/stores/{id}',[StoreController::class,'update'],[AuthMiddleware::class]);
$router->add('POST','/api/stores/{id}/branches',[StoreController::class,'createBranch'],[AuthMiddleware::class, ShopMiddleware::class]);
$router->add('GET','/api/me/stores',[StoreController::class,'myStores'],[AuthMiddleware::class]);
$router->add('POST','/api/stores/{id}/images',[StoreController::class,'uploadImage'],[AuthMiddleware::class]);

// Vouchers
$router->add('POST','/api/vouchers',[VoucherController::class,'create'],[AuthMiddleware::class, AdminOrShopMiddleware::class]);
$router->add('POST','/api/vouchers/assign',[VoucherController::class,'assign'],[AuthMiddleware::class, AdminOrShopMiddleware::class]);
$router->add('GET','/api/stores/{id}/vouchers',[VoucherController::class,'byStore']);

// Promotions
$router->add('POST','/api/promotions',[PromotionController::class,'create'],[AuthMiddleware::class, AdminMiddleware::class]);
$router->add('POST','/api/promotions/{id}/apply',[PromotionController::class,'applyStore'],[AuthMiddleware::class, ShopMiddleware::class]);
$router->add('POST','/api/promotions/{id}/review',[PromotionController::class,'reviewApplication'],[AuthMiddleware::class, AdminMiddleware::class]);
$router->add('GET','/api/stores/{id}/promotions',[PromotionController::class,'byStore']);

// Blog
$router->add('GET','/api/blog',[BlogController::class,'list']);
$router->add('POST','/api/blog',[BlogController::class,'create'],[AuthMiddleware::class, AdminMiddleware::class]);
$router->add('PATCH','/api/blog/{id}',[BlogController::class,'update'],[AuthMiddleware::class, AdminMiddleware::class]);

// Banners and media
$router->add('GET','/api/banners',[BannerController::class,'list']);
$router->add('POST','/api/banners',[BannerController::class,'create'],[AuthMiddleware::class, AdminMiddleware::class]);
$router->add('PATCH','/api/banners/{id}',[BannerController::class,'update'],[AuthMiddleware::class, AdminMiddleware::class]);

// Chat
$router->add('POST','/api/chat/send',[ChatController::class,'send'],[AuthMiddleware::class]);
$router->add('GET','/api/chat/messages',[ChatController::class,'messages'],[AuthMiddleware::class]);
$router->add('GET','/api/chat/conversations',[ChatController::class,'conversations'],[AuthMiddleware::class]);

// Admin & Contact
$router->add('GET','/api/admin/dashboard',[AdminController::class,'dashboard'],[AuthMiddleware::class, AdminMiddleware::class]);
$router->add('POST','/api/admin/users/role',[AdminController::class,'setRole'],[AuthMiddleware::class, AdminMiddleware::class]);
$router->add('GET','/api/admin/pending-stores',[AdminController::class,'pendingStores'],[AuthMiddleware::class, AdminMiddleware::class]);
$router->add('POST','/api/admin/stores/{id}/approve',[AdminController::class,'approveStore'],[AuthMiddleware::class, AdminMiddleware::class]);
$router->add('GET','/api/contact',[AdminController::class,'contact']);

// Wallet
$router->add('GET','/api/me/wallet',[WalletController::class,'me'],[AuthMiddleware::class]);
$router->add('GET','/api/me/wallet/history',[WalletController::class,'history'],[AuthMiddleware::class]);
$router->add('GET','/api/me/wallet/deposit-instructions',[WalletController::class,'depositInstructions'],[AuthMiddleware::class]);
// Simulated bank webhook: optionally secured by BANK_WEBHOOK_SECRET
$router->add('POST','/api/simulate/bank-transfer',[WalletController::class,'simulateBankTransfer']);

// Advertising
$router->add('GET','/api/ads/packages',[AdvertisingController::class,'packagesList']);
$router->add('POST','/api/ads/requests',[AdvertisingController::class,'create'],[AuthMiddleware::class, ShopMiddleware::class]);
$router->add('GET','/api/ads/requests/my',[AdvertisingController::class,'myRequests'],[AuthMiddleware::class]);
$router->add('GET','/api/ads/active',[AdvertisingController::class,'active']);
// Admin review for ads
$router->add('GET','/api/admin/ads/requests/pending',[AdvertisingController::class,'adminPending'],[AuthMiddleware::class, AdminMiddleware::class]);
$router->add('POST','/api/admin/ads/requests/{id}/review',[AdvertisingController::class,'adminReview'],[AuthMiddleware::class, AdminMiddleware::class]);

// CSRF token for SPAs (sets cookie and returns token)
$router->add('GET','/api/csrf-token',[CsrfController::class,'token']);
