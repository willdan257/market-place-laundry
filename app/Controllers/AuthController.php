<?php

namespace App\Controllers;

use App\Helpers\Session;
use App\Helpers\Formatter;
use App\Helpers\Env;
use App\Models\User;
use App\Services\GoogleAuthService;
use App\Services\MailService;
use App\Services\RateLimiter;

class AuthController extends BaseController
{
    public function loginView(): void
    {
        if (Session::has('user')) {
            $user = Session::get('user');
            $role = strtolower(trim((string) ($user['role'] ?? '')));
            $canonicalRole = match ($role) {
                'admin'                 => 'admin',
                'owner', 'mitra'        => 'owner',
                'driver', 'kurir'       => 'driver',
                'customer', 'pelanggan' => 'customer',
                default                 => null,
            };
            $target = match ($canonicalRole) {
                'admin'    => '/admin/verifikasi',
                'owner'    => '/owner/dashboard',
                'driver'   => '/driver/dashboard',
                'customer' => '/customer/dashboard',
                default    => '/dashboard',
            };
            $this->redirect($target);
            return;
        }

        $googleAuthService = new GoogleAuthService();

        $this->render('auth/login', [
            'title'              => 'Masuk Akun - Marketplace Laundry',
            'googleAuthUrl'      => $googleAuthService->getAuthUrl(),
            'isGoogleConfigured' => $googleAuthService->isConfigured(),
            'googleDisabledReason' => $googleAuthService->getDisabledReason(),
            'flashSuccess'       => Session::getFlash('success'),
            'flashError'         => Session::getFlash('error'),
            'flashWarning'       => Session::getFlash('warning'),
            'oldIdentity'        => Session::getFlash('old_identity') ?? '',
            'loginAs'            => Session::getFlash('old_login_as') ?? 'customer',
        ]);
    }

    public function loginProcess(): void
    {
        $token = $_POST['_token'] ?? null;
        if (!Session::verifyCsrfToken($token)) {
            Session::setFlash('error', 'Validasi keamanan gagal (CSRF mismatch). Silakan muat ulang halaman.');
            $this->redirect('/login');
            return;
        }

        $identity = trim((string) ($_POST['identity'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $loginAs  = trim((string) ($_POST['login_as'] ?? ''));

        // Whitelist validation for login_as (Admin dipisahkan ke /admin/login)
        $allowedRoles = ['customer', 'owner', 'driver'];
        if (empty($loginAs) || !in_array($loginAs, $allowedRoles, true)) {
            Session::setFlash('old_identity', $identity);
            Session::setFlash('error', 'Silakan pilih peran akun yang valid.');
            $this->redirect('/login');
            return;
        }

        if (empty($identity) || empty($password)) {
            Session::setFlash('old_identity', $identity);
            Session::setFlash('old_login_as', $loginAs);
            Session::setFlash('error', 'Email/No. WhatsApp dan Password wajib diisi.');
            $this->redirect('/login');
            return;
        }

        // Rate limiting per IP + Identity (5 failed attempts per 15 minutes)
        $clientIp = RateLimiter::getClientIp();
        $rateLimitKey = 'login_' . hash('sha256', $clientIp . '_' . strtolower($identity));

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5, 900)) {
            $secondsLeft = RateLimiter::availableIn($rateLimitKey, 900);
            $minutesLeft = ceil($secondsLeft / 60);
            Session::setFlash('old_identity', $identity);
            Session::setFlash('old_login_as', $loginAs);
            Session::setFlash('error', "Terlalu banyak percobaan gagal. Akun dikunci sementara. Silakan coba kembali dalam {$minutesLeft} menit.");
            $this->redirect('/login');
            return;
        }

        $userModel = new User();
        $user = $userModel->findByEmailOrPhone($identity);

        if (!$user || empty($user['password']) || !password_verify($password, $user['password'])) {
            RateLimiter::hit($rateLimitKey, 900);
            Session::setFlash('old_identity', $identity);
            Session::setFlash('old_login_as', $loginAs);
            // Generic message to prevent user enumeration
            Session::setFlash('error', 'Email atau password salah.');
            $this->redirect('/login');
            return;
        }

        // Clear rate limiter on valid password
        RateLimiter::clear($rateLimitKey);

        // Login umum menolak akun admin dengan pesan umum
        $userRole = strtolower(trim((string) ($user['role'] ?? '')));
        if ($userRole === 'admin') {
            Session::setFlash('old_identity', $identity);
            Session::setFlash('old_login_as', $loginAs);
            Session::setFlash('error', 'Email atau password salah.');
            $this->redirect('/login');
            return;
        }

        // SETELAH password terverifikasi, bandingkan login_as dengan role akun
        $roleMap = [
            'customer'  => 'customer',
            'pelanggan' => 'customer',
            'owner'     => 'owner',
            'mitra'     => 'owner',
            'driver'    => 'driver',
            'kurir'     => 'driver',
            'admin'     => 'admin',
        ];
        $userRole = strtolower(trim((string) ($user['role'] ?? '')));
        $normalizedUserRole = $roleMap[$userRole] ?? $userRole;

        if ($loginAs !== $normalizedUserRole) {
            $roleDisplayNames = [
                'customer' => 'Pelanggan',
                'owner'    => 'Mitra Laundry',
                'driver'   => 'Kurir',
                'admin'    => 'Admin',
            ];
            $targetLabel = $roleDisplayNames[$loginAs] ?? $loginAs;

            // JANGAN buat session. Tampilkan pesan umum tanpa menyebut peran asli akun (cegah enumerasi)
            Session::setFlash('old_identity', $identity);
            Session::setFlash('old_login_as', $loginAs);
            Session::setFlash('error', "Akun ini tidak terdaftar sebagai {$targetLabel}. Silakan pilih peran yang sesuai.");
            $this->redirect('/login');
            return;
        }

        // Check verification & active status
        $status = $user['status'] ?? 'aktif';

        if ($status === 'nonaktif') {
            Session::setFlash('error', 'Akun Anda telah dinonaktifkan. Silakan hubungi Customer Service.');
            $this->redirect('/login');
            return;
        }

        if ($status === 'menunggu_verifikasi') {
            Session::regenerate();
            Session::set('user', User::sanitizeSessionUser($user));
            Session::setFlash('warning', 'Akun Anda masih dalam proses verifikasi tim Admin.');
            $this->redirect('/verifikasi-menunggu');
            return;
        }

        if ($status === 'ditolak') {
            Session::regenerate();
            Session::set('user', User::sanitizeSessionUser($user));
            Session::setFlash('error', 'Pendaftaran akun Anda ditolak oleh Admin.');
            $this->redirect('/verifikasi-menunggu');
            return;
        }

        // Successful login
        Session::regenerate();
        Session::remove('ai_chat_history');
        Session::set('user', User::sanitizeSessionUser($user));
        Session::setFlash('success', 'Selamat datang kembali, ' . htmlspecialchars($user['name']));

        // Redirect based on role
        $role = strtolower($user['role'] ?? 'customer');
        switch ($role) {
            case 'admin':
                $this->redirect('/admin/verifikasi');
                break;
            case 'owner':
            case 'mitra':
                $this->redirect('/owner/dashboard');
                break;
            case 'driver':
            case 'kurir':
                $this->redirect('/driver/dashboard');
                break;
            case 'customer':
            case 'pelanggan':
            default:
                if (!$userModel->isProfileComplete($user['id'])) {
                    $this->redirect('/profile/complete');
                } else {
                    $this->redirect('/customer/dashboard');
                }
                break;
        }
    }

    public function googleRedirect(): void
    {
        $googleAuthService = new GoogleAuthService();
        if (!$googleAuthService->isConfigured()) {
            Session::setFlash('error', 'Login dengan Google belum tersedia.');
            $this->redirect('/login');
            return;
        }
        $url = $googleAuthService->getAuthUrl();
        if (!$url) {
            Session::setFlash('error', 'Gagal membuat tautan otentikasi Google.');
            $this->redirect('/login');
            return;
        }
        $this->redirect($url);
    }

    public function googleCallback(): void
    {
        $code  = $_GET['code'] ?? null;
        $state = $_GET['state'] ?? null;

        if (!$code) {
            Session::setFlash('error', 'Gagal melakukan otentikasi Google OAuth (kode otorisasi tidak ditemukan).');
            $this->redirect('/login');
            return;
        }

        $googleAuthService = new GoogleAuthService();
        if (!$googleAuthService->verifyState($state)) {
            Session::setFlash('error', 'Validasi sesi OAuth gagal (State CSRF mismatch). Silakan coba login kembali.');
            $this->redirect('/login');
            return;
        }

        $googleProfile = $googleAuthService->getUserInfoFromCode($code);

        if (!$googleProfile || empty($googleProfile['google_id'])) {
            Session::setFlash('error', 'Gagal mendapatkan profil pengguna dari Google.');
            $this->redirect('/login');
            return;
        }

        // Strict Requirement: email_verified must be true from Google
        if (empty($googleProfile['email_verified']) || $googleProfile['email_verified'] !== true) {
            Session::setFlash('error', 'Email dari akun Google Anda belum terverifikasi oleh Google.');
            $this->redirect('/login');
            return;
        }

        $this->processUserAuthentication($googleProfile);
    }

    public function processUserAuthentication(array $googleProfile): void
    {
        $userModel = new User();

        // 1. Search by google_id first
        $user = $userModel->findByGoogleId($googleProfile['google_id']);

        // 2. If not found, search by verified email
        if (!$user && !empty($googleProfile['email'])) {
            $user = $userModel->findByEmail($googleProfile['email']);
            if ($user) {
                // Link Google identity to existing manual account without creating duplicate!
                $userModel->linkGoogleAccount(
                    (int) $user['id'],
                    $googleProfile['google_id'],
                    $googleProfile['picture'] ?? null
                );
                $user = $userModel->find((int) $user['id']);
            }
        }

        // 3. If user still does not exist: create brand new customer user
        if (!$user) {
            $userId = $userModel->createGoogleUser($googleProfile, 'customer');
            if (!$userId) {
                Session::setFlash('error', 'Gagal membuat akun Google baru.');
                $this->redirect('/login');
                return;
            }
            $user = $userModel->find($userId);
        }

        // Strict Requirement: Hanya peran Pelanggan/Customer yang boleh masuk lewat Google
        $role = strtolower(trim((string) ($user['role'] ?? 'customer')));
        if (!in_array($role, ['customer', 'pelanggan'], true)) {
            Session::setFlash('error', 'Login dengan Google hanya tersedia untuk akun Pelanggan. Akun ' . ucfirst($role) . ' wajib masuk menggunakan email/WhatsApp dan kata sandi.');
            $this->redirect('/login');
            return;
        }

        // Check account status
        $status = $user['status'] ?? 'aktif';

        if ($status === 'nonaktif') {
            Session::setFlash('error', 'Akun Anda telah dinonaktifkan. Silakan hubungi Customer Service.');
            $this->redirect('/login');
            return;
        }

        if ($status === 'menunggu_verifikasi') {
            Session::regenerate();
            Session::set('user', User::sanitizeSessionUser($user));
            Session::setFlash('warning', 'Akun Anda masih dalam proses verifikasi tim Admin.');
            $this->redirect('/verifikasi-menunggu');
            return;
        }

        if ($status === 'ditolak') {
            Session::regenerate();
            Session::set('user', User::sanitizeSessionUser($user));
            Session::setFlash('error', 'Pendaftaran akun Anda ditolak oleh Admin.');
            $this->redirect('/verifikasi-menunggu');
            return;
        }

        // Successful authentication
        Session::regenerate();
        Session::remove('ai_chat_history');
        Session::set('user', User::sanitizeSessionUser($user));
        Session::setFlash('success', 'Selamat datang, ' . htmlspecialchars($user['name']));

        // Role-based redirection
        $role = strtolower($user['role'] ?? 'customer');
        switch ($role) {
            case 'admin':
                $this->redirect('/admin/verifikasi');
                break;
            case 'owner':
            case 'mitra':
                $this->redirect('/owner/dashboard');
                break;
            case 'driver':
            case 'kurir':
                $this->redirect('/driver/dashboard');
                break;
            case 'customer':
            case 'pelanggan':
            default:
                if (!$userModel->isProfileComplete($user['id'])) {
                    Session::setFlash('success', 'Login Google berhasil! Silakan lengkapi data kontak dan alamat pengiriman Anda.');
                    $this->redirect('/profile/complete');
                } else {
                    $this->redirect('/customer/dashboard');
                }
                break;
        }
    }

    /**
     * Strict POST logout with CSRF verification
     */
    public function logout(): void
    {
        $token = $_POST['_token'] ?? null;
        if (!Session::verifyCsrfToken($token)) {
            Session::setFlash('error', 'Token keamanan CSRF tidak valid.');
            $this->redirect('/dashboard');
            return;
        }

        Session::remove('ai_chat_history');
        Session::destroy();
        Session::start();
        Session::regenerate();
        Session::setFlash('success', 'Anda telah berhasil keluar (logout).');
        $this->redirect('/login');
    }

    /**
     * Forgot Password View: GET /lupa-password
     */
    public function forgotPasswordView(): void
    {
        if (Session::has('user')) {
            $this->redirect('/dashboard');
            return;
        }

        $waConfig = \App\Services\WhatsAppService::getConfig();

        $this->render('auth/forgot_password', [
            'title'        => 'Lupa Password via WhatsApp - Marketplace Laundry',
            'isWaEnabled'  => $waConfig['enabled'],
            'flashSuccess' => Session::getFlash('success'),
            'flashError'   => Session::getFlash('error'),
            'flashWarning' => Session::getFlash('warning'),
        ]);
    }

    /**
     * Forgot Password Process via WhatsApp: POST /lupa-password
     */
    public function forgotPasswordProcess(): void
    {
        $token = $_POST['_token'] ?? null;
        if (!Session::verifyCsrfToken($token)) {
            Session::setFlash('error', 'Token keamanan CSRF tidak valid. Silakan coba kembali.');
            $this->redirect('/lupa-password');
            return;
        }

        $waConfig = \App\Services\WhatsAppService::getConfig();
        if (!$waConfig['enabled']) {
            Session::setFlash('error', 'Layanan reset kata sandi via WhatsApp saat ini sedang tidak aktif atau token API belum dikonfigurasi. Silakan hubungi Administrator.');
            $this->redirect('/lupa-password');
            return;
        }

        $rawPhone = trim((string) ($_POST['phone'] ?? ''));
        $normalizedPhone = Formatter::normalizePhone($rawPhone);

        // Rate limiting: 3 per hour per IP & per Phone
        $clientIp = RateLimiter::getClientIp();
        $ipKey    = 'forgot_wa_ip_' . hash('sha256', $clientIp);
        $phoneKey = 'forgot_wa_phone_' . hash('sha256', $normalizedPhone ?? $rawPhone);

        if (RateLimiter::tooManyAttempts($ipKey, 3, 3600) || (!empty($normalizedPhone) && RateLimiter::tooManyAttempts($phoneKey, 3, 3600))) {
            $secondsLeft = max(RateLimiter::availableIn($ipKey, 3600), RateLimiter::availableIn($phoneKey, 3600));
            $minutesLeft = max(1, (int) ceil($secondsLeft / 60));
            Session::setFlash('error', "Terlalu banyak permintaan kode OTP. Silakan tunggu {$minutesLeft} menit sebelum mencoba lagi.");
            $this->redirect('/lupa-password');
            return;
        }

        RateLimiter::hit($ipKey, 3600);
        if (!empty($normalizedPhone)) {
            RateLimiter::hit($phoneKey, 3600);
        }

        try {
            if (!empty($normalizedPhone) && Formatter::isValidPhone($normalizedPhone)) {
                $userModel = new User();
                $user = $userModel->findByPhone($normalizedPhone);

                if ($user && ($user['status'] ?? 'aktif') !== 'nonaktif') {
                    $otpCode = $userModel->createWhatsAppPasswordReset((int) $user['id'], $normalizedPhone);

                    // Kirim kode 6 digit via WhatsApp Fonnte
                    $waMessage = "*Marketplace Laundry - Reset Password*\n\n"
                               . "Halo {$user['name']},\n"
                               . "Berikut adalah kode verifikasi 6 digit untuk mengatur ulang kata sandi akun Anda:\n\n"
                               . "*{$otpCode}*\n\n"
                               . "Kode ini berlaku selama *10 menit* dan hanya dapat digunakan 1 kali. Jangan bagikan kode ini kepada siapa pun.";

                    \App\Services\WhatsAppService::sendSingle(
                        $normalizedPhone,
                        $waMessage,
                        null,
                        'customer',
                        'otp_password_reset'
                    );

                    // Simpan phone di session untuk verifikasi tahap selanjutnya
                    Session::set('pw_reset_phone', $normalizedPhone);
                }
            }
        } catch (\Throwable $e) {
            error_log('[AuthController::forgotPasswordProcess] Exception: ' . $e->getMessage());
            // Tetap berikan respons netral agar tidak membocorkan error sistem ke pengguna
        }

        // Respons selalu sama dan netral untuk mencegah user enumeration
        Session::setFlash('success', 'Jika nomor WhatsApp terdaftar di sistem kami, kode verifikasi 6 digit telah dikirimkan ke WhatsApp Anda.');
        $this->redirect('/lupa-password/verifikasi');
    }

    /**
     * Input OTP Code View: GET /lupa-password/verifikasi
     */
    public function verifyOtpView(): void
    {
        $phone = Session::get('pw_reset_phone');
        if (empty($phone)) {
            $this->redirect('/lupa-password');
            return;
        }

        $maskedPhone = \App\Services\WhatsAppService::maskPhone($phone);

        $this->render('auth/verify_otp', [
            'title'        => 'Masukkan Kode Verifikasi - Marketplace Laundry',
            'maskedPhone'  => $maskedPhone,
            'flashSuccess' => Session::getFlash('success'),
            'flashError'   => Session::getFlash('error'),
        ]);
    }

    /**
     * Verify OTP Code Process: POST /lupa-password/verifikasi
     */
    public function verifyOtpProcess(): void
    {
        $token = $_POST['_token'] ?? null;
        if (!Session::verifyCsrfToken($token)) {
            Session::setFlash('error', 'Token keamanan CSRF tidak valid. Silakan coba kembali.');
            $this->redirect('/lupa-password/verifikasi');
            return;
        }

        $phone = Session::get('pw_reset_phone');
        if (empty($phone)) {
            Session::setFlash('error', 'Sesi verifikasi telah kedaluwarsa. Silakan masukkan nomor kembali.');
            $this->redirect('/lupa-password');
            return;
        }

        $code = trim((string) ($_POST['otp_code'] ?? ''));
        if (empty($code) || strlen($code) !== 6 || !ctype_digit($code)) {
            Session::setFlash('error', 'Masukkan 6 digit angka kode verifikasi dengan benar.');
            $this->redirect('/lupa-password/verifikasi');
            return;
        }

        try {
            $userModel = new User();
            $record = $userModel->verifyWhatsAppResetCode($phone, $code);
        } catch (\Throwable $e) {
            error_log('[AuthController::verifyOtpProcess] Exception: ' . $e->getMessage());
            Session::setFlash('error', 'Terjadi kesalahan sistem saat memverifikasi kode. Silakan coba kembali.');
            $this->redirect('/lupa-password/verifikasi');
            return;
        }

        if (!$record) {
            Session::setFlash('error', 'Kode verifikasi tidak valid, sudah kedaluwarsa (10 menit), atau telah hangus karena salah lebih dari 5 kali.');
            $this->redirect('/lupa-password/verifikasi');
            return;
        }

        // Token valid! Simpan token_hash yang lolos verifikasi ke sesi untuk pembuatan password baru
        Session::set('pw_reset_verified_id', (int) $record['id']);
        Session::set('pw_reset_user_id', (int) $record['user_id']);
        Session::set('pw_reset_verified_phone', $phone);

        $this->redirect('/lupa-password/password-baru');
    }

    /**
     * Set New Password View: GET /lupa-password/password-baru
     */
    public function newPasswordView(): void
    {
        $userId = Session::get('pw_reset_user_id');
        $resetId = Session::get('pw_reset_verified_id');

        if (empty($userId) || empty($resetId)) {
            Session::setFlash('error', 'Sesi reset kata sandi tidak valid. Silakan ulangi proses.');
            $this->redirect('/lupa-password');
            return;
        }

        $this->render('auth/new_password', [
            'title'        => 'Buat Password Baru - Marketplace Laundry',
            'flashError'   => Session::getFlash('error'),
        ]);
    }

    /**
     * Set New Password Process: POST /lupa-password/password-baru
     */
    public function newPasswordProcess(): void
    {
        $csrfToken = $_POST['_token'] ?? null;
        if (!Session::verifyCsrfToken($csrfToken)) {
            Session::setFlash('error', 'Token keamanan CSRF tidak valid. Silakan coba kembali.');
            $this->redirect('/lupa-password/password-baru');
            return;
        }

        $userId = Session::get('pw_reset_user_id');
        $resetId = Session::get('pw_reset_verified_id');
        $phone = Session::get('pw_reset_verified_phone');

        if (empty($userId) || empty($resetId)) {
            Session::setFlash('error', 'Sesi reset kata sandi sudah tidak berlaku. Silakan ulangi proses.');
            $this->redirect('/lupa-password');
            return;
        }

        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

        if (!Formatter::isValidPassword($password)) {
            Session::setFlash('error', 'Password wajib diisi.');
            $this->redirect('/lupa-password/password-baru');
            return;
        }

        if ($password !== $passwordConfirmation) {
            Session::setFlash('error', 'Konfirmasi password tidak cocok.');
            $this->redirect('/lupa-password/password-baru');
            return;
        }

        try {
            $userModel = new User();
            $user = $userModel->find((int) $userId);
            if (!$user) {
                Session::setFlash('error', 'Pengguna tidak ditemukan.');
                $this->redirect('/login');
                return;
            }

            // 1. Update password & invalidate used token
            $userModel->consumePasswordReset((int) $userId, (int) $resetId, $password);

            // 2. Bersihkan seluruh session reset
            Session::remove('pw_reset_phone');
            Session::remove('pw_reset_verified_id');
            Session::remove('pw_reset_user_id');
            Session::remove('pw_reset_verified_phone');

            // 3. Hancurkan sesi lama pengguna & regenerate
            Session::regenerate();

            // 4. Kirim notifikasi konfirmasi ke WhatsApp
            $targetPhone = $user['phone'] ?? $phone;
            if (!empty($targetPhone)) {
                $confirmMsg = "*Marketplace Laundry - Keamanan Akun*\n\n"
                            . "Halo {$user['name']},\n"
                            . "Kata sandi akun Anda telah berhasil diubah pada " . date('d M Y, H:i') . " WIB.\n\n"
                            . "Jika Anda tidak melakukan perubahan ini, segera hubungi tim bantuan kami.";

                \App\Services\WhatsAppService::sendSingle(
                    $targetPhone,
                    $confirmMsg,
                    null,
                    'customer',
                    'password_changed_notice'
                );
            }
        } catch (\Throwable $e) {
            error_log('[AuthController::newPasswordProcess] Exception: ' . $e->getMessage());
            Session::setFlash('error', 'Terjadi kesalahan sistem saat menyimpan password baru. Silakan coba kembali.');
            $this->redirect('/lupa-password/password-baru');
            return;
        }

        Session::setFlash('success', 'Password Anda telah berhasil diubah! Silakan masuk dengan kata sandi baru Anda.');
        $this->redirect('/login');
    }

    /**
     * Reset Password View: GET /reset-password/{token}
     */
    public function resetPasswordView(string $token): void
    {
        $userModel = new User();
        $record = $userModel->verifyPasswordResetToken($token);

        if (!$record) {
            Session::setFlash('error', 'Tautan reset password tidak valid atau sudah kedaluwarsa (berlaku 60 menit).');
            $this->redirect('/lupa-password');
            return;
        }

        $this->render('auth/reset_password', [
            'title'        => 'Buat Password Baru - Marketplace Laundry',
            'token'        => $token,
            'flashError'   => Session::getFlash('error'),
            'flashSuccess' => Session::getFlash('success'),
        ]);
    }

    /**
     * Reset Password Process: POST /reset-password/{token}
     */
    public function resetPasswordProcess(string $token): void
    {
        $csrfToken = $_POST['_token'] ?? null;
        if (!Session::verifyCsrfToken($csrfToken)) {
            Session::setFlash('error', 'Token keamanan CSRF tidak valid. Silakan coba kembali.');
            $this->redirect("/reset-password/{$token}");
            return;
        }

        $userModel = new User();
        $record = $userModel->verifyPasswordResetToken($token);

        if (!$record) {
            Session::setFlash('error', 'Tautan reset password sudah tidak berlaku.');
            $this->redirect('/lupa-password');
            return;
        }

        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

        if (!Formatter::isValidPassword($password)) {
            Session::setFlash('error', 'Password wajib diisi.');
            $this->redirect("/reset-password/{$token}");
            return;
        }

        if ($password !== $passwordConfirmation) {
            Session::setFlash('error', 'Konfirmasi password tidak cocok.');
            $this->redirect("/reset-password/{$token}");
            return;
        }

        $userId = (int) $record['user_id'];
        $resetId = (int) $record['id'];
        $userModel->consumePasswordReset($userId, $resetId, $password);

        Session::setFlash('success', 'Kata sandi Anda berhasil diperbarui! Silakan masuk menggunakan kata sandi baru Anda.');
        $this->redirect('/login');
    }

    /**
     * Email Verification: GET /verifikasi-email/{token}
     */
    public function verifyEmail(string $token): void
    {
        $userModel = new User();
        $record = $userModel->verifyEmailToken($token);

        if (!$record) {
            Session::setFlash('error', 'Tautan verifikasi email tidak valid atau sudah kedaluwarsa.');
            $this->redirect('/dashboard');
            return;
        }

        $userId = (int) $record['user_id'];
        $verificationId = (int) $record['id'];
        $userModel->markEmailVerified($userId, $verificationId);

        // Refresh session if user is currently logged in
        $currentUser = Session::get('user');
        if ($currentUser && (int) ($currentUser['id'] ?? 0) === $userId) {
            $updated = $userModel->find($userId);
            Session::set('user', User::sanitizeSessionUser($updated));
        }

        Session::setFlash('success', '🎉 Alamat email Anda telah berhasil diverifikasi! Sekarang Anda dapat melakukan pemesanan.');
        $this->redirect('/dashboard');
    }

    /**
     * Resend Verification Email: POST /verifikasi-email/kirim-ulang
     */
    public function resendVerification(): void
    {
        $token = $_POST['_token'] ?? null;
        if (!Session::verifyCsrfToken($token)) {
            Session::setFlash('error', 'Token keamanan CSRF tidak valid.');
            $this->redirect('/dashboard');
            return;
        }

        $user = Session::get('user');
        if (!$user) {
            $this->redirect('/login');
            return;
        }

        $userId = (int) $user['id'];
        $clientIp = RateLimiter::getClientIp();
        $rateLimitKey = 'resend_email_' . hash('sha256', "{$userId}_{$clientIp}");

        if (RateLimiter::tooManyAttempts($rateLimitKey, 3, 300)) {
            $seconds = RateLimiter::availableIn($rateLimitKey, 300);
            $minutes = ceil($seconds / 60);
            Session::setFlash('error', "Terlalu banyak permintaan kirim ulang. Silakan tunggu {$minutes} menit sebelum meminta kembali.");
            $this->redirect('/dashboard');
            return;
        }
        RateLimiter::hit($rateLimitKey, 300);

        $userModel = new User();
        $freshUser = $userModel->find($userId);

        if (!empty($freshUser['email_verified_at'])) {
            Session::setFlash('success', 'Email Anda sudah terverifikasi.');
            $this->redirect('/dashboard');
            return;
        }

        $rawToken = $userModel->createEmailVerification($userId, $freshUser['email'], 'register');
        $appUrl = rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/');
        $verifyUrl = "{$appUrl}/verifikasi-email/{$rawToken}";

        $mailService = new MailService();
        $mailService->sendVerificationEmail($freshUser['email'], $freshUser['name'], $verifyUrl);

        $isDev = in_array(strtolower((string) Env::get('APP_ENV', '')), ['local', 'development'], true);
        if ($isDev) {
            @file_put_contents(
                __DIR__ . '/../../storage/logs/mail.log',
                "[" . date('Y-m-d H:i:s') . "] [DEV] Tautan verifikasi email (kirim ulang) untuk {$freshUser['email']}: {$verifyUrl}\n",
                FILE_APPEND | LOCK_EX
            );
            Session::setFlash('success', 'Tautan verifikasi baru berhasil dibuat! [Mode Dev] Link verifikasi: ' . $verifyUrl);
        } else {
            Session::setFlash('success', 'Tautan verifikasi baru telah dikirimkan ke email Anda. Silakan periksa inbox/spam.');
        }
        $this->redirect('/dashboard');
    }

    public function adminLoginView(): void
    {
        if (Session::has('user')) {
            $user = Session::get('user');
            $role = strtolower(trim((string) ($user['role'] ?? '')));
            if ($role === 'admin') {
                $this->redirect('/admin/verifikasi');
                return;
            }
            $target = match ($role) {
                'owner', 'mitra'        => '/owner/dashboard',
                'driver', 'kurir'       => '/driver/dashboard',
                'customer', 'pelanggan' => '/customer/dashboard',
                default                 => '/dashboard',
            };
            $this->redirect($target);
            return;
        }

        $this->render('auth/admin_login', [
            'title'        => 'Login Administrator - Marketplace Laundry',
            'flashSuccess' => Session::getFlash('success'),
            'flashError'   => Session::getFlash('error'),
            'flashWarning' => Session::getFlash('warning'),
            'oldIdentity'  => Session::getFlash('old_identity') ?? '',
        ]);
    }

    public function adminLoginProcess(): void
    {
        $token = $_POST['_token'] ?? null;
        if (!Session::verifyCsrfToken($token)) {
            Session::setFlash('error', 'Validasi keamanan gagal (CSRF mismatch). Silakan muat ulang halaman.');
            $this->redirect('/admin/login');
            return;
        }

        $identity = trim((string) ($_POST['identity'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (empty($identity) || empty($password)) {
            Session::setFlash('old_identity', $identity);
            Session::setFlash('error', 'Email/No. WhatsApp dan Password wajib diisi.');
            $this->redirect('/admin/login');
            return;
        }

        // Rate limiting: 5 kali gagal -> kunci 15 menit (900 detik) per akun dan per IP
        $clientIp = RateLimiter::getClientIp();
        $ipKey = 'admin_login_ip_' . hash('sha256', $clientIp);
        $accountKey = 'admin_login_acc_' . hash('sha256', strtolower($identity));

        $isIpLocked = RateLimiter::tooManyAttempts($ipKey, 5, 900);
        $isAccLocked = RateLimiter::tooManyAttempts($accountKey, 5, 900);

        if ($isIpLocked || $isAccLocked) {
            $secondsLeft = max(RateLimiter::availableIn($ipKey, 900), RateLimiter::availableIn($accountKey, 900));
            $minutesLeft = max(1, (int) ceil($secondsLeft / 60));
            self::logAdminLogin($identity, $clientIp, null, 0, 'locked');

            Session::setFlash('old_identity', $identity);
            Session::setFlash('error', "Terlalu banyak percobaan gagal. Akses administrator dikunci sementara. Silakan coba kembali dalam {$minutesLeft} menit.");
            $this->redirect('/admin/login');
            return;
        }

        $userModel = new User();
        $user = $userModel->findByEmailOrPhone($identity);

        if (!$user || empty($user['password']) || !password_verify($password, $user['password'])) {
            RateLimiter::hit($ipKey, 900);
            RateLimiter::hit($accountKey, 900);
            self::logAdminLogin($identity, $clientIp, $user ? (int) $user['id'] : null, 0, $user ? 'wrong_password' : 'not_found');

            Session::setFlash('old_identity', $identity);
            Session::setFlash('error', 'Email atau password salah.');
            $this->redirect('/admin/login');
            return;
        }

        // /admin/login hanya menerima akun dengan role admin
        $userRole = strtolower(trim((string) ($user['role'] ?? '')));
        if ($userRole !== 'admin') {
            RateLimiter::hit($ipKey, 900);
            RateLimiter::hit($accountKey, 900);
            self::logAdminLogin($identity, $clientIp, (int) $user['id'], 0, 'not_admin');

            Session::setFlash('old_identity', $identity);
            Session::setFlash('error', 'Akun ini bukan akun Administrator.');
            $this->redirect('/admin/login');
            return;
        }

        $status = $user['status'] ?? 'aktif';
        if ($status === 'nonaktif') {
            self::logAdminLogin($identity, $clientIp, (int) $user['id'], 0, 'inactive');
            Session::setFlash('error', 'Akun Administrator Anda telah dinonaktifkan.');
            $this->redirect('/admin/login');
            return;
        }

        // Login sukses: bersihkan hitungan rate limit & catat log berhasil
        RateLimiter::clear($ipKey);
        RateLimiter::clear($accountKey);
        self::logAdminLogin($identity, $clientIp, (int) $user['id'], 1, null);

        Session::regenerate();
        Session::remove('ai_chat_history');
        Session::set('user', User::sanitizeSessionUser($user));
        Session::setFlash('success', 'Selamat datang kembali, Administrator ' . htmlspecialchars($user['name']));
        $this->redirect('/admin/verifikasi');
    }

    public static function logAdminLogin(string $identity, string $ip, ?int $userId, int $success, ?string $reason): void
    {
        try {
            $pdo = \App\Models\BaseModel::getPdo();
            if (!$pdo) {
                return;
            }
            $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
            $stmt = $pdo->prepare("
                INSERT INTO admin_login_log (attempted_identity, ip_address, user_id, success, failure_reason, user_agent, created_at)
                VALUES (:identity, :ip, :user_id, :success, :reason, :agent, NOW())
            ");
            $stmt->execute([
                'identity' => substr($identity, 0, 255),
                'ip'       => substr($ip, 0, 45),
                'user_id'  => $userId,
                'success'  => $success,
                'reason'   => $reason ? substr($reason, 0, 100) : null,
                'agent'    => $userAgent,
            ]);
        } catch (\Throwable $e) {
            error_log("[admin_login_log] Log error: " . $e->getMessage());
        }
    }
}
