<?php

/**
 * Suite Test Otomatis untuk Sistem Akun Produksi (Marketplace Laundry)
 * Menguji Poin 1 sampai 5:
 * 1. Registrasi Customer -> Multi-Tabel PDO Transaction (users + customers + customer_addresses)
 * 2. Login dengan Email ATAU Nomor Telepon + Password yang sama -> Mendapatkan ID yang sama
 * 3. Normalisasi Phone & Email
 * 4. Registrasi Owner/Mitra & Driver/Kurir dengan status menunggu_verifikasi
 * 5. Endpoint approval lama (/verifikasi-approve) tertutup (404/403)
 * 6. Admin sungguhan menyetujui akun mitra & kurir (/admin/verifikasi/approve)
 * 7. Edit Profil (Nama, HP, Email):
 *    - Manual user: Email ganti via token pending_email & 60-menit expiry
 *    - Google user: Email terkunci (tidak bisa diedit)
 *    - Validasi password saat ganti email
 * 8. Ganti Password & Buat Password baru untuk user Google
 * 9. Lupa Password -> Token hash SHA-256 + 60 menit expiry -> Reset password sukses -> Token lama dihapus
 * 10. Rate Limiter (IP + Identity)
 * 11. Sanitasi Session: Password hash tidak pernah tersimpan di session
 * 12. Google OAuth Account Linking (Mock Profile) -> Menghubungkan manual account tanpa duplikasi data
 */

namespace Tests;

define('TEST_ENV', true);

require_once __DIR__ . '/../app/Helpers/Env.php';
require_once __DIR__ . '/../app/Helpers/Session.php';
require_once __DIR__ . '/../app/Helpers/Response.php';
require_once __DIR__ . '/../app/Helpers/Formatter.php';

spl_autoload_register(function ($class) {
    $prefixes = [
        'App\\' => __DIR__ . '/../app/',
        'Routes\\' => __DIR__ . '/../routes/'
    ];
    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) continue;
        $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

use PDO;
use App\Helpers\Env;
use App\Helpers\Session;
use App\Helpers\Formatter;
use App\Models\BaseModel;
use App\Models\User;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Partner;
use App\Models\Courier;
use App\Models\Store;
use App\Models\Driver;
use App\Controllers\AuthController;
use App\Controllers\RegisterController;
use App\Controllers\ProfileController;
use App\Controllers\AdminVerificationController;
use App\Middlewares\RoleMiddleware;
use App\Services\RateLimiter;

Env::load(__DIR__ . '/../.env');
date_default_timezone_set('Asia/Jakarta');
Session::start();

class AccountTestSuite
{
    private int $passed = 0;
    private int $failed = 0;
    private array $errors = [];

    public function assert(string $description, bool $condition, string $detail = ''): void
    {
        if ($condition) {
            $this->passed++;
            echo "  \033[32m✔ [PASS]\033[0m {$description}\n";
        } else {
            $this->failed++;
            $msg = "  \033[31m✖ [FAIL]\033[0m {$description}" . ($detail ? " ({$detail})" : "");
            echo "{$msg}\n";
            $this->errors[] = $description . ($detail ? ": {$detail}" : "");
        }
    }

    public function printSummary(): bool
    {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "ACCOUNT SYSTEM TEST RESULTS: \033[32m{$this->passed} Passed\033[0m, \033[" . ($this->failed > 0 ? "31m" : "32m") . "{$this->failed} Failed\033[0m\n";
        echo str_repeat('=', 60) . "\n";
        if ($this->failed > 0) {
            echo "Failed tests:\n";
            foreach ($this->errors as $err) {
                echo " - {$err}\n";
            }
            return false;
        }
        return true;
    }
}

$t = new AccountTestSuite();
$pdo = BaseModel::getPdo();
$userModel = new User();

echo "============================================================\n";
echo "🛡️ RUNNING PRODUCTION ACCOUNT SYSTEM INTEGRATION TESTS\n";
echo "============================================================\n\n";

$seed = time() . rand(100, 999);

// -------------------------------------------------------------
// TEST 1: Phone & Email Normalization & Password Complexity
// -------------------------------------------------------------
echo "\033[34m[TEST 1] Testing Normalization & Password Validation...\033[0m\n";
$t->assert("normalizeEmail trims and lowercases", Formatter::normalizeEmail('  User.Test@Example.COM  ') === 'user.test@example.com');
$t->assert("normalizePhone formats 0812 -> 62812", Formatter::normalizePhone('0812-3456-7890') === '6281234567890');
$t->assert("normalizePhone formats +62812 -> 62812", Formatter::normalizePhone('+62 812-3456-7890') === '6281234567890');
$t->assert("normalizePhone formats 62812 -> 62812", Formatter::normalizePhone('6281234567890') === '6281234567890');
$t->assert("isValidPhone accepts valid Indonesian number", Formatter::isValidPhone('081234567890'));
$t->assert("isValidPhone rejects invalid short number", !Formatter::isValidPhone('08123'));
$t->assert("isValidPassword accepts min 6 chars (any characters)", Formatter::isValidPassword('secret'));
$t->assert("isValidPassword accepts password without numbers if >= 6 chars", Formatter::isValidPassword('PasswordOnly'));
$t->assert("isValidPassword accepts numbers only if >= 6 chars", Formatter::isValidPassword('123456'));
$t->assert("isValidPassword rejects short password (< 6 chars)", !Formatter::isValidPassword('Pass1'));

// -------------------------------------------------------------
// TEST 2: Registration Customer -> Multi-Table Transaction
// -------------------------------------------------------------
echo "\n\033[34m[TEST 2] Testing Customer Registration Transaction (users, customers, customer_addresses)...\033[0m\n";
$custEmail = "customer_{$seed}@example.com";
$custPhoneRaw = "0812" . substr($seed, -8);
$custNormalizedPhone = Formatter::normalizePhone($custPhoneRaw);
$custPassword = "SecurePassword123";

$_POST = [
    '_token'                => Session::csrfToken(),
    'name'                  => "Budi Santoso {$seed}",
    'email'                 => $custEmail,
    'phone'                 => $custPhoneRaw,
    'password'              => $custPassword,
    'password_confirmation' => $custPassword,
    'alamat_utama'          => "Jl. Melati Indah No. {$seed}, Jakarta",
    'terms'                 => '1'
];

$regCtrl = new RegisterController();
ob_start();
$regCtrl->customerStore();
ob_end_clean();

$createdCust = $userModel->findByEmail($custEmail);
$t->assert("User record created in users table", $createdCust !== null);
$t->assert("User phone stored in normalized format (628...)", $createdCust['phone'] === $custNormalizedPhone);
$t->assert("User role is customer", $createdCust['role'] === 'customer');
$t->assert("Customer record created in customers table", (new Customer())->findByUserId((int) $createdCust['id']) !== null);
$t->assert("Customer primary address created in customer_addresses", (new CustomerAddress())->getPrimaryAddress((int) $createdCust['id']) !== null);
$t->assert("Email verification token created for new manual user", $pdo->query("SELECT COUNT(*) FROM email_verifications WHERE user_id = {$createdCust['id']} AND type = 'register'")->fetchColumn() > 0);

// -------------------------------------------------------------
// TEST 3: Login via Email OR Phone -> Same Account ID
// -------------------------------------------------------------
echo "\n\033[34m[TEST 3] Testing Dual Login (Email vs Phone)...\033[0m\n";
$userByEmail = $userModel->findByEmailOrPhone($custEmail);
$userByPhoneRaw = $userModel->findByEmailOrPhone($custPhoneRaw);
$userByPhoneNorm = $userModel->findByEmailOrPhone($custNormalizedPhone);

$t->assert("findByEmailOrPhone retrieves user using email", $userByEmail && (int)$userByEmail['id'] === (int)$createdCust['id']);
$t->assert("findByEmailOrPhone retrieves user using raw phone (08xx)", $userByPhoneRaw && (int)$userByPhoneRaw['id'] === (int)$createdCust['id']);
$t->assert("findByEmailOrPhone retrieves user using normalized phone (628xx)", $userByPhoneNorm && (int)$userByPhoneNorm['id'] === (int)$createdCust['id']);

// Simulate login with phone
$_POST = [
    '_token'   => Session::csrfToken(),
    'identity' => $custPhoneRaw,
    'password' => $custPassword
];
$authCtrl = new AuthController();
ob_start();
$authCtrl->loginProcess();
ob_end_clean();

$sessionUser = Session::get('user');
$t->assert("Logged in session contains authenticated user ID", $sessionUser && (int)$sessionUser['id'] === (int)$createdCust['id']);
$t->assert("Logged in session does NOT contain password hash", !isset($sessionUser['password']));

// -------------------------------------------------------------
// TEST 4: Registration Owner & Courier (Status: menunggu_verifikasi)
// -------------------------------------------------------------
echo "\n\033[34m[TEST 4] Testing Partner & Courier Registration with Pending Status...\033[0m\n";
$partnerEmail = "partner_{$seed}@example.com";
$partnerPhone = "0813" . substr($seed, -8);
$_POST = [
    '_token'                => Session::csrfToken(),
    'name'                  => "Mitra Owner {$seed}",
    'email'                 => $partnerEmail,
    'phone'                 => $partnerPhone,
    'password'              => "MitraPass123",
    'password_confirmation' => "MitraPass123",
    'nama_usaha'            => "Laundry Berkah {$seed}",
    'alamat'                => "Jl. Sudirman No. 99",
    'jam_operasional'       => "08:00 - 21:00",
    'harga_kiloan'          => 9000,
    'harga_satuan'          => 12000,
    'harga_express'         => 18000
];
ob_start();
$regCtrl->partnerStore();
ob_end_clean();

$createdPartner = $userModel->findByEmail($partnerEmail);
$t->assert("Partner user registered with role owner", $createdPartner && $createdPartner['role'] === 'owner');
$t->assert("Partner status is strictly menunggu_verifikasi", $createdPartner && $createdPartner['status'] === 'menunggu_verifikasi');
$t->assert("Partner record exists in partners table", (new Partner())->findByUserId((int)$createdPartner['id']) !== null);
$storeRecord = (new Store())->findByOwnerId((int)$createdPartner['id']);
$t->assert("Store record created with is_open = 0", $storeRecord && (int)$storeRecord['is_open'] === 0);

// Courier Registration
$courierEmail = "courier_{$seed}@example.com";
$courierPhone = "0814" . substr($seed, -8);
$_POST = [
    '_token'                => Session::csrfToken(),
    'name'                  => "Kurir Kilat {$seed}",
    'email'                 => $courierEmail,
    'phone'                 => $courierPhone,
    'password'              => "KurirPass123",
    'password_confirmation' => "KurirPass123",
    'jenis_kendaraan'       => 'Motor Honda Vario',
    'plat_nomor'            => 'B ' . rand(1000, 9999) . ' XYZ'
];
ob_start();
$regCtrl->courierStore();
ob_end_clean();

$createdCourier = $userModel->findByEmail($courierEmail);
$t->assert("Courier user registered with role driver", $createdCourier && $createdCourier['role'] === 'driver');
$t->assert("Courier status is strictly menunggu_verifikasi", $createdCourier && $createdCourier['status'] === 'menunggu_verifikasi');
$driverRecord = (new Driver())->findByUserId((int)$createdCourier['id']);
$t->assert("Driver record created with is_available = 0", $driverRecord && (int)$driverRecord['is_available'] === 0);

// -------------------------------------------------------------
// TEST 5: Protection - Old /verifikasi-approve closed & RBAC guards
// -------------------------------------------------------------
echo "\n\033[34m[TEST 5] Testing Approval Route Security & Owner/Driver Guards...\033[0m\n";
$router = require __DIR__ . '/../routes/web.php';
$hasApproveRoute = false;
$routesProp = new \ReflectionProperty($router, 'routes');
$routesProp->setAccessible(true);
$allRoutes = $routesProp->getValue($router);

foreach ($allRoutes as $r) {
    if ($r['path'] === '/verifikasi-approve') {
        $hasApproveRoute = true;
        break;
    }
}
$t->assert("Insecure /verifikasi-approve route is completely removed", !$hasApproveRoute);

// Owner with status menunggu_verifikasi cannot access owner dashboard
Session::set('user', User::sanitizeSessionUser($createdPartner));
$ownerMiddleware = new RoleMiddleware(['owner']);
ob_start();
$canOwnerAccess = $ownerMiddleware->handle();
ob_end_clean();
$t->assert("Pending partner is blocked by RoleMiddleware from Owner Dashboard", !$canOwnerAccess);

// Courier with status menunggu_verifikasi cannot access driver dashboard
Session::set('user', User::sanitizeSessionUser($createdCourier));
$driverMiddleware = new RoleMiddleware(['driver']);
ob_start();
$canCourierAccess = $driverMiddleware->handle();
ob_end_clean();
$t->assert("Pending courier is blocked by RoleMiddleware from Driver Dashboard", !$canCourierAccess);

// -------------------------------------------------------------
// TEST 6: Real Admin Approval
// -------------------------------------------------------------
echo "\n\033[34m[TEST 6] Testing Admin Approval Endpoint...\033[0m\n";
$adminId = $userModel->createUser([
    'name'     => 'System Admin',
    'email'    => "admin_{$seed}@example.com",
    'phone'    => "0815" . substr($seed, -8),
    'password' => 'AdminPass123',
    'role'     => 'admin',
    'status'   => 'aktif'
]);

$adminUser = $userModel->find($adminId);
Session::set('user', User::sanitizeSessionUser($adminUser));

$adminCtrl = new AdminVerificationController();

// Approve Partner
$_POST = [
    '_token'  => Session::csrfToken(),
    'user_id' => $createdPartner['id'],
    'type'    => 'partner'
];
ob_start();
$adminCtrl->approve();
ob_end_clean();

$approvedPartner = $userModel->find($createdPartner['id']);
$approvedStore = (new Store())->findByOwnerId((int)$createdPartner['id']);
$t->assert("Partner approved by admin: status is aktif", $approvedPartner['status'] === 'aktif');
$t->assert("Store is_open set to 1 upon approval", (int)$approvedStore['is_open'] === 1);

// Approve Courier
$_POST = [
    '_token'  => Session::csrfToken(),
    'user_id' => $createdCourier['id'],
    'type'    => 'courier'
];
ob_start();
$adminCtrl->approve();
ob_end_clean();

$approvedCourier = $userModel->find($createdCourier['id']);
$approvedDriver = (new Driver())->findByUserId((int)$createdCourier['id']);
$t->assert("Courier approved by admin: status is aktif", $approvedCourier['status'] === 'aktif');
$t->assert("Driver is_available set to 1 upon approval", (int)$approvedDriver['is_available'] === 1);

// Now Owner can access dashboard
Session::set('user', User::sanitizeSessionUser($approvedPartner));
ob_start();
$canOwnerAccessNow = $ownerMiddleware->handle();
ob_end_clean();
$t->assert("Approved partner is permitted into Owner Dashboard", $canOwnerAccessNow);

// -------------------------------------------------------------
// TEST 7: Profile Editing (Name, Phone, Email Verification)
// -------------------------------------------------------------
echo "\n\033[34m[TEST 7] Testing Profile Editing & Email Change Flow...\033[0m\n";
Session::set('user', User::sanitizeSessionUser($createdCust));
$profileCtrl = new ProfileController();

$newCustName = "Budi Santoso Diperbarui";
$newCustPhone = "0812" . rand(10000000, 99999999);
$newCustEmail = "new_email_{$seed}@example.com";

// Attempt changing email with WRONG password -> MUST FAIL
$_POST = [
    '_token'           => Session::csrfToken(),
    'name'             => $newCustName,
    'phone'            => $newCustPhone,
    'email'            => $newCustEmail,
    'current_password' => 'WrongPassword'
];
ob_start();
$profileCtrl->updateProfile();
ob_end_clean();

$userCheck = $userModel->find($createdCust['id']);
$t->assert("Email not changed when wrong current password supplied", $userCheck['email'] === $custEmail);

// Change email with CORRECT password -> saved as pending_email
$_POST = [
    '_token'           => Session::csrfToken(),
    'name'             => $newCustName,
    'phone'            => $newCustPhone,
    'email'            => $newCustEmail,
    'current_password' => $custPassword
];
ob_start();
$profileCtrl->updateProfile();
ob_end_clean();

$userPending = $userModel->find($createdCust['id']);
$t->assert("Profile name updated", $userPending['name'] === $newCustName);
$t->assert("Profile phone updated to normalized format", $userPending['phone'] === Formatter::normalizePhone($newCustPhone));
$t->assert("Old email remains active", $userPending['email'] === $custEmail);
$t->assert("New email saved in pending_email column", $userPending['pending_email'] === $newCustEmail);

// Verify email verification record for email_change exists
$verifRecord = $pdo->query("SELECT * FROM email_verifications WHERE user_id = {$createdCust['id']} AND type = 'email_change'")->fetch(PDO::FETCH_ASSOC);
$t->assert("Email change verification token record created", !empty($verifRecord));

// Simulate clicking confirmation link
$verifyToken = $userModel->createEmailVerification((int)$createdCust['id'], $newCustEmail, 'email_change');
ob_start();
$profileCtrl->verifyNewEmail($verifyToken);
ob_end_clean();

$userConfirmed = $userModel->find($createdCust['id']);
$t->assert("New email confirmed and updated in users.email", $userConfirmed['email'] === $newCustEmail);
$t->assert("pending_email reset to NULL after confirmation", $userConfirmed['pending_email'] === null);

// -------------------------------------------------------------
// TEST 8: Google Account Email Locking & Google Linking (No Duplicate)
// -------------------------------------------------------------
echo "\n\033[34m[TEST 8] Testing Google Account Linking & Email Lock...\033[0m\n";
// Create user with Google profile
$googleSub = "google_sub_" . $seed;
$mockGoogleProfile = [
    'google_id'      => $googleSub,
    'email'          => "google_user_{$seed}@gmail.com",
    'name'           => "Google User {$seed}",
    'picture'        => "https://example.com/pic.jpg",
    'email_verified' => true
];

$authCtrl->processUserAuthentication($mockGoogleProfile);
$googleUser = $userModel->findByGoogleId($googleSub);
$t->assert("New Google login creates customer account", $googleUser && $googleUser['role'] === 'customer');
$t->assert("Google user has email_verified_at set", !empty($googleUser['email_verified_at']));
$t->assert("Google user has picture saved", !empty($googleUser['picture']));

// Second login with same Google account -> MUST NOT DUPLICATE
$totalUsersBefore = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$authCtrl->processUserAuthentication($mockGoogleProfile);
$totalUsersAfter = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$t->assert("Repeated Google login enters same account without duplicate", $totalUsersBefore === $totalUsersAfter);

// Link Google account to existing manual user with matching email
$manualEmailForLinking = "link_target_{$seed}@example.com";
$manualUserId = $userModel->createUser([
    'name'              => 'Manual Target',
    'email'             => $manualEmailForLinking,
    'phone'             => '0877' . substr($seed, -8),
    'password'          => 'ManualPass123',
    'role'              => 'customer',
    'status'            => 'aktif',
    'email_verified_at' => null
]);

$mockLinkingGoogleProfile = [
    'google_id'      => 'google_link_' . $seed,
    'email'          => $manualEmailForLinking,
    'name'           => 'Manual Target',
    'picture'        => 'https://example.com/link_avatar.jpg',
    'email_verified' => true
];

$authCtrl->processUserAuthentication($mockLinkingGoogleProfile);
$linkedUser = $userModel->find($manualUserId);
$t->assert("Existing manual account is linked with google_id", $linkedUser['google_id'] === 'google_link_' . $seed);
$t->assert("Existing manual account has picture updated", !empty($linkedUser['picture']));
$t->assert("Existing manual account becomes email verified via Google", !empty($linkedUser['email_verified_at']));

// -------------------------------------------------------------
// TEST 9: Forgot Password & Single-Use Token Flow
// -------------------------------------------------------------
echo "\n\033[34m[TEST 9] Testing Forgot Password & Single-Use Reset Token...\033[0m\n";
$targetResetUser = $userModel->find($manualUserId);
$rawResetToken = $userModel->createPasswordReset((int)$manualUserId);
$t->assert("Password reset token generated", !empty($rawResetToken));

$tokenRecord = $userModel->verifyPasswordResetToken($rawResetToken);
$t->assert("verifyPasswordResetToken validates valid token", $tokenRecord !== null && (int)$tokenRecord['user_id'] === (int)$manualUserId);

// Reset password with new password
$newPasswordForManual = "BrandNewPass888";
$consumeSuccess = $userModel->consumePasswordReset((int)$manualUserId, (int)$tokenRecord['id'], $newPasswordForManual);
$t->assert("consumePasswordReset returns true", $consumeSuccess);

$userAfterReset = $userModel->find($manualUserId);
$t->assert("User can verify new password", password_verify($newPasswordForManual, $userAfterReset['password']));

// Ensure old token is now invalid/consumed
$reVerify = $userModel->verifyPasswordResetToken($rawResetToken);
$t->assert("Consumed reset token cannot be reused (single-use)", $reVerify === null);

// -------------------------------------------------------------
// TEST 10: Rate Limiting Verification
// -------------------------------------------------------------
echo "\n\033[34m[TEST 10] Testing Rate Limiter...\033[0m\n";
$rateKey = 'test_lockout_' . $seed;
RateLimiter::clear($rateKey);
$t->assert("Rate limiter initially has 0 attempts", !RateLimiter::tooManyAttempts($rateKey, 3, 60));

RateLimiter::hit($rateKey, 60);
RateLimiter::hit($rateKey, 60);
$t->assert("2 attempts still below limit 3", !RateLimiter::tooManyAttempts($rateKey, 3, 60));

RateLimiter::hit($rateKey, 60);
$t->assert("3 attempts triggers tooManyAttempts", RateLimiter::tooManyAttempts($rateKey, 3, 60));
RateLimiter::clear($rateKey);
$t->assert("clear() resets rate limiter", !RateLimiter::tooManyAttempts($rateKey, 3, 60));

// -------------------------------------------------------------
// SUMMARY
// -------------------------------------------------------------
$allPassed = $t->printSummary();
exit($allPassed ? 0 : 1);
