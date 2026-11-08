<?php

use Routes\Router;
use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\RegisterController;
use App\Controllers\ProfileController;
use App\Controllers\DashboardController;
use App\Controllers\OrderController;
use App\Controllers\OwnerController;
use App\Controllers\DriverController;
use App\Controllers\AIController;
use App\Controllers\PaymentController;
use App\Controllers\AdminVerificationController;
use App\Controllers\AdminChatController;
use App\Controllers\ConfigController;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\RoleMiddleware;
use App\Middlewares\ProfileCompleteMiddleware;

$router = new Router();

// ==========================================
// 1. PUBLIC ROUTES
// ==========================================
$router->get('/', [HomeController::class, 'index']);

// Auth: Login
$router->get('/login', [AuthController::class, 'loginView']);
$router->get('/masuk', [AuthController::class, 'loginView']);
$router->post('/login', [AuthController::class, 'loginProcess']);

// Auth: Logout (POST ONLY with CSRF)
$router->post('/logout', [AuthController::class, 'logout']);

// Google OAuth Routes (Real OAuth Only - No dev simulation)
$router->get('/auth/google/redirect', [AuthController::class, 'googleRedirect']);
$router->get('/auth/google/callback', [AuthController::class, 'googleCallback']);

// Password Reset Routes via WhatsApp OTP (Public)
$router->get('/lupa-password', [AuthController::class, 'forgotPasswordView']);
$router->post('/lupa-password', [AuthController::class, 'forgotPasswordProcess']);
$router->get('/lupa-password/verifikasi', [AuthController::class, 'verifyOtpView']);
$router->post('/lupa-password/verifikasi', [AuthController::class, 'verifyOtpProcess']);
$router->get('/lupa-password/password-baru', [AuthController::class, 'newPasswordView']);
$router->post('/lupa-password/password-baru', [AuthController::class, 'newPasswordProcess']);

// Legacy/Fallback token route
$router->get('/reset-password/{token}', [AuthController::class, 'resetPasswordView']);
$router->post('/reset-password/{token}', [AuthController::class, 'resetPasswordProcess']);

// Email Verification Link
$router->get('/verifikasi-email/{token}', [AuthController::class, 'verifyEmail']);
$router->post('/verifikasi-email/kirim-ulang', [AuthController::class, 'resendVerification'], [AuthMiddleware::class]);
$router->get('/verifikasi-email-baru/{token}', [ProfileController::class, 'verifyNewEmail']);

// ==========================================
// 1b. REGISTRATION ROUTES (PUBLIC)
// ==========================================
$router->get('/daftar', [RegisterController::class, 'chooseRoleView']);
$router->get('/register', [RegisterController::class, 'chooseRoleView']);

$router->get('/daftar/pelanggan', [RegisterController::class, 'customerView']);
$router->post('/daftar/pelanggan', [RegisterController::class, 'customerStore']);

$router->get('/daftar/mitra', [RegisterController::class, 'partnerView']);
$router->post('/daftar/mitra', [RegisterController::class, 'partnerStore']);

$router->get('/daftar/kurir', [RegisterController::class, 'courierView']);
$router->post('/daftar/kurir', [RegisterController::class, 'courierStore']);

$router->get('/verifikasi-menunggu', [RegisterController::class, 'pendingVerificationView'], [AuthMiddleware::class]);

// ==========================================
// 1c. ADMIN AUTH & VERIFICATION ROUTES
// ==========================================
$router->get('/admin/login', [AuthController::class, 'adminLoginView']);
$router->post('/admin/login', [AuthController::class, 'adminLoginProcess']);

$adminGuards = [AuthMiddleware::class, new RoleMiddleware(['admin'])];
$router->get('/admin/verifikasi', [AdminVerificationController::class, 'index'], $adminGuards);
$router->post('/admin/verifikasi/approve', [AdminVerificationController::class, 'approve'], $adminGuards);
$router->post('/admin/verifikasi/reject', [AdminVerificationController::class, 'reject'], $adminGuards);
$router->get('/admin/chats', [AdminChatController::class, 'index'], $adminGuards);
$router->get('/admin/konfigurasi', [ConfigController::class, 'index'], $adminGuards);
$router->post('/admin/konfigurasi', [ConfigController::class, 'save'], $adminGuards);
$router->post('/admin/konfigurasi/test-ai', [ConfigController::class, 'testAi'], $adminGuards);
$router->post('/admin/konfigurasi/test-wa', [ConfigController::class, 'testWa'], $adminGuards);
$router->get('/admin/configuration', [ConfigController::class, 'index'], $adminGuards);
$router->post('/admin/configuration', [ConfigController::class, 'save'], $adminGuards);
$router->post('/admin/configuration/test-ai', [ConfigController::class, 'testAi'], $adminGuards);
$router->post('/admin/configuration/test-wa', [ConfigController::class, 'testWa'], $adminGuards);
$router->get('/admin/chats/{orderId}', [AdminChatController::class, 'detail'], $adminGuards);
$router->get('/admin/assistant', [AIController::class, 'adminChatView'], $adminGuards);
$router->post('/admin/assistant/message', [AIController::class, 'message'], $adminGuards);
$router->post('/admin/assistant/reset', [AIController::class, 'reset'], $adminGuards);

// ==========================================
// 2. PROFILE & ONBOARDING ROUTES (AUTH REQUIRED)
// ==========================================
$router->get('/profile/complete', [ProfileController::class, 'completeProfileView'], [AuthMiddleware::class]);
$router->post('/profile/complete', [ProfileController::class, 'saveCompleteProfile'], [AuthMiddleware::class]);
$router->get('/profile', [ProfileController::class, 'profileView'], [AuthMiddleware::class]);
$router->get('/profile/edit', [ProfileController::class, 'editProfileView'], [AuthMiddleware::class]);
$router->post('/profile/edit', [ProfileController::class, 'updateProfile'], [AuthMiddleware::class]);
$router->post('/profile/change-password', [ProfileController::class, 'changePassword'], [AuthMiddleware::class]);

// ==========================================
// 3. DASHBOARD ROUTES (AUTH & ROLE-BASED ACCESS CONTROL)
// ==========================================
$router->get('/dashboard', [DashboardController::class, 'index'], [AuthMiddleware::class]);

$router->get(
    '/customer/dashboard',
    [DashboardController::class, 'customerDashboard'],
    [AuthMiddleware::class, new RoleMiddleware(['customer']), ProfileCompleteMiddleware::class]
);

$router->get(
    '/customer/dashboard/active-orders',
    [DashboardController::class, 'activeCustomerOrdersJson'],
    [AuthMiddleware::class, new RoleMiddleware(['customer']), ProfileCompleteMiddleware::class]
);

$router->get(
    '/owner/dashboard',
    [DashboardController::class, 'ownerDashboard'],
    [AuthMiddleware::class, new RoleMiddleware(['owner'])]
);

$router->get(
    '/driver/dashboard',
    [DashboardController::class, 'driverDashboard'],
    [AuthMiddleware::class, new RoleMiddleware(['driver'])]
);

// ==========================================
// 4. CUSTOMER ORDER CREATION & MANAGEMENT ROUTES
// ==========================================
$customerGuards = [AuthMiddleware::class, new RoleMiddleware(['customer']), ProfileCompleteMiddleware::class];
$customerOrderGuards = [AuthMiddleware::class, new RoleMiddleware(['customer']), ProfileCompleteMiddleware::class];

$router->get('/customer/orders', [OrderController::class, 'index'], $customerGuards);
$router->get('/customer/orders/create', [OrderController::class, 'create'], $customerOrderGuards);
$router->post('/customer/orders/confirm', [OrderController::class, 'confirm'], $customerOrderGuards);
$router->post('/customer/orders/store', [OrderController::class, 'store'], $customerOrderGuards);
$router->get('/customer/orders/{id}', [OrderController::class, 'show'], $customerGuards);
$router->get('/orders/{id}', [OrderController::class, 'show'], $customerGuards);
$router->post('/customer/orders/{id}/complete', [OrderController::class, 'complete'], $customerGuards);

// ------------------------------------------
// AI Assistant (read-only)
// Endpoint publik: mode ditentukan server dari session (customer login = data sendiri, selain itu = info publik)
// ------------------------------------------
$router->post('/assistant/message', [AIController::class, 'message']);
$router->post('/assistant/reset', [AIController::class, 'reset']);
$router->get('/customer/assistant', [AIController::class, 'chatView'], $customerGuards);
$router->post('/customer/assistant/message', [AIController::class, 'message'], $customerGuards);
$router->post('/customer/assistant/reset', [AIController::class, 'reset'], $customerGuards);

// ==========================================
// 5. OWNER MANAGEMENT ROUTES (STRICT OWNER ROLE)
// ==========================================
$ownerGuards = [AuthMiddleware::class, new RoleMiddleware(['owner'])];

$router->get('/owner/orders', [OwnerController::class, 'orders'], $ownerGuards);
$router->get('/owner/drivers', [OwnerController::class, 'drivers'], $ownerGuards);
$router->get('/owner/orders/{id}', [OwnerController::class, 'orderDetail'], $ownerGuards);
$router->post('/owner/orders/{id}/weigh', [OwnerController::class, 'processWeigh'], $ownerGuards);
$router->get('/owner/orders/{id}/assign', [OwnerController::class, 'assignForm'], $ownerGuards);
$router->post('/owner/orders/{id}/assign', [OwnerController::class, 'assignStore'], $ownerGuards);
$router->post('/owner/orders/{id}/status', [OwnerController::class, 'updateStatus'], $ownerGuards);
$router->post('/owner/store/toggle-status', [OwnerController::class, 'toggleStoreStatus'], $ownerGuards);
$router->get('/owner/assistant', [AIController::class, 'ownerChatView'], $ownerGuards);
$router->post('/owner/assistant/message', [AIController::class, 'message'], $ownerGuards);
$router->post('/owner/assistant/reset', [AIController::class, 'reset'], $ownerGuards);

// ==========================================
// 6. DRIVER TRIP MANAGEMENT ROUTES (STRICT DRIVER ROLE)
// ==========================================
$driverGuards = [AuthMiddleware::class, new RoleMiddleware(['driver'])];

$router->get('/driver/orders', [DriverController::class, 'orders'], $driverGuards);
$router->get('/driver/history', [DriverController::class, 'history'], $driverGuards);
$router->get('/driver/orders/{id}', [DriverController::class, 'show'], $driverGuards);
$router->post('/driver/orders/{id}/status', [DriverController::class, 'updateStatus'], $driverGuards);
$router->get('/driver/assistant', [AIController::class, 'driverChatView'], $driverGuards);
$router->post('/driver/assistant/message', [AIController::class, 'message'], $driverGuards);
$router->post('/driver/assistant/reset', [AIController::class, 'reset'], $driverGuards);

// ==========================================
// 7. PAYMENT ROUTES (STRICT RBAC & SIGNATURE GUARDS)
// ==========================================
// Customer payment views & actions
$router->get('/payment/{orderId}', [PaymentController::class, 'method'], $customerGuards);
$router->post('/payment/{orderId}/qris', [PaymentController::class, 'createQris'], $customerGuards);
$router->get('/payment/{orderId}/qris', [PaymentController::class, 'showQris'], $customerGuards);
$router->post('/payment/{orderId}/cash', [PaymentController::class, 'createCash'], $customerGuards);
$router->get('/payment/{orderId}/status', [PaymentController::class, 'status'], $customerGuards);
$router->get('/payment/{orderId}/success', [PaymentController::class, 'success'], $customerGuards);
$router->get('/payment/{orderId}/expired', [PaymentController::class, 'expired'], $customerGuards);
$router->get('/payment/{orderId}/poll', [PaymentController::class, 'poll'], $customerGuards);

// Owner and Driver Cash payment confirmation
$router->post('/payment/cash/{orderId}/confirm', [PaymentController::class, 'confirmCash'], [AuthMiddleware::class, new RoleMiddleware(['owner', 'driver'])]);

// Midtrans webhook (public callback, strictly verified by SHA512 signature)
$router->post('/payment/webhook/midtrans', [PaymentController::class, 'webhook']);

return $router;
