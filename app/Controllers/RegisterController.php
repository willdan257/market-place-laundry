<?php

namespace App\Controllers;

use App\Helpers\Session;
use App\Helpers\Formatter;
use App\Helpers\Env;
use App\Models\BaseModel;
use App\Models\User;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Partner;
use App\Models\PartnerService;
use App\Models\Courier;
use App\Models\Store;
use App\Models\Service;
use App\Models\Driver;
use App\Services\GoogleAuthService;
use App\Services\MailService;
use App\Services\RateLimiter;
use Exception;

class RegisterController extends BaseController
{
    /**
     * Role selection view: /daftar or /register
     */
    public function chooseRoleView(): void
    {
        if (Session::has('user')) {
            $this->redirect('/dashboard');
            return;
        }

        $this->render('auth/register_choose', [
            'title' => 'Pilih Peran Akun - MarketplaceLaundry'
        ]);
    }

    /**
     * Customer registration view: /daftar/pelanggan
     */
    public function customerView(): void
    {
        if (Session::has('user')) {
            $this->redirect('/dashboard');
            return;
        }

        $googleAuthService = new GoogleAuthService();

        $this->render('auth/register_customer', [
            'title'                => 'Daftar Sebagai Pelanggan - MarketplaceLaundry',
            'googleAuthUrl'        => $googleAuthService->getAuthUrl(),
            'isGoogleConfigured'   => $googleAuthService->isConfigured(),
            'googleDisabledReason' => $googleAuthService->getDisabledReason(),
            'flashError'           => Session::getFlash('error'),
        ]);
    }

    /**
     * Process Customer registration POST
     */
    public function customerStore(): void
    {
        $this->verifyCsrfOrAbort('/daftar/pelanggan');

        // Rate limiting: 10 attempts per IP per 15 minutes
        $clientIp = RateLimiter::getClientIp();
        $rateLimitKey = 'register_customer_' . hash('sha256', $clientIp);
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10, 900)) {
            $minutes = ceil(RateLimiter::availableIn($rateLimitKey, 900) / 60);
            Session::setFlash('error', "Terlalu banyak permintaan pendaftaran. Silakan tunggu {$minutes} menit.");
            $this->redirect('/daftar/pelanggan');
            return;
        }
        RateLimiter::hit($rateLimitKey, 900);

        $name     = trim((string) ($_POST['name'] ?? ''));
        $email    = Formatter::normalizeEmail($_POST['email'] ?? '');
        $phone    = Formatter::normalizePhone($_POST['phone'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirmation'] ?? '');
        $address  = trim((string) ($_POST['alamat_utama'] ?? ''));
        $terms    = isset($_POST['terms']);

        // Validation
        if (empty($name) || strlen($name) < 3) {
            Session::setFlash('error', 'Nama lengkap minimal 3 karakter.');
            $this->redirect('/daftar/pelanggan');
            return;
        }

        if (!Formatter::isValidEmail($email)) {
            Session::setFlash('error', 'Format email tidak valid (contoh: nama@email.com atau akun@test).');
            $this->redirect('/daftar/pelanggan');
            return;
        }

        if (!Formatter::isValidPhone($phone)) {
            Session::setFlash('error', 'Nomor telepon/WhatsApp wajib berupa nomor HP Indonesia yang valid (contoh: 081234567890).');
            $this->redirect('/daftar/pelanggan');
            return;
        }

        if (!Formatter::isValidPassword($password)) {
            Session::setFlash('error', 'Password wajib diisi.');
            $this->redirect('/daftar/pelanggan');
            return;
        }

        if ($password !== $passwordConfirm) {
            Session::setFlash('error', 'Konfirmasi password tidak cocok.');
            $this->redirect('/daftar/pelanggan');
            return;
        }

        if (!$terms) {
            Session::setFlash('error', 'Anda harus menyetujui Syarat & Ketentuan.');
            $this->redirect('/daftar/pelanggan');
            return;
        }

        $userModel = new User();
        if ($userModel->isEmailTaken($email)) {
            Session::setFlash('error', 'Alamat email sudah terdaftar. Silakan gunakan email lain atau masuk ke akun Anda.');
            $this->redirect('/daftar/pelanggan');
            return;
        }

        if ($userModel->isPhoneTaken($phone)) {
            Session::setFlash('error', 'Nomor telepon sudah terdaftar di sistem.');
            $this->redirect('/daftar/pelanggan');
            return;
        }

        // Multi-table write inside PDO transaction
        $pdo = BaseModel::getPdo();
        $pdo->beginTransaction();

        try {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $stmtUser = $pdo->prepare(
                "INSERT INTO users (google_id, name, email, password, phone, role, status, email_verified_at)
                 VALUES (NULL, :name, :email, :password, :phone, 'customer', 'aktif', NULL)"
            );
            $stmtUser->execute([
                'name'     => $name,
                'email'    => $email,
                'password' => $passwordHash,
                'phone'    => $phone,
            ]);
            $userId = (int) $pdo->lastInsertId();

            // Insert into customers table
            $stmtCust = $pdo->prepare(
                "INSERT INTO customers (user_id, alamat_utama) VALUES (:uid, :addr)"
            );
            $stmtCust->execute([
                'uid'  => $userId,
                'addr' => !empty($address) ? $address : null
            ]);

            // If address is filled, insert into customer_addresses
            if (!empty($address)) {
                $stmtAddr = $pdo->prepare(
                    "INSERT INTO customer_addresses (user_id, label, recipient_name, recipient_phone, full_address, rt, rw, district, city, postal_code, latitude, longitude, is_primary)
                     VALUES (:uid, 'Utama', :rec_name, :rec_phone, :full_addr, '01', '01', 'Kecamatan', 'Kota', '12345', -6.20880000, 106.84560000, 1)"
                );
                $stmtAddr->execute([
                    'uid'       => $userId,
                    'rec_name'  => $name,
                    'rec_phone' => $phone,
                    'full_addr' => $address
                ]);
            }

            // Create email verification token
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);

            $stmtVerif = $pdo->prepare(
                "INSERT INTO email_verifications (user_id, email, token_hash, type, expires_at)
                 VALUES (:uid, :email, :token_hash, 'register', :expires_at)"
            );
            $stmtVerif->execute([
                'uid'        => $userId,
                'email'      => $email,
                'token_hash' => $tokenHash,
                'expires_at' => $expiresAt
            ]);

            $pdo->commit();

            // Send Verification Email via MailService (fail-safe)
            $appUrl = rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/');
            $verifyUrl = "{$appUrl}/verifikasi-email/{$rawToken}";
            $mailService = new MailService();
            $mailService->sendVerificationEmail($email, $name, $verifyUrl);

            // Fetch fresh sanitized user
            $freshUser = $userModel->find($userId);
            Session::regenerate();
            Session::set('user', User::sanitizeSessionUser($freshUser));

            $isDev = in_array(strtolower((string) Env::get('APP_ENV', '')), ['local', 'development'], true);
            if ($isDev) {
                @file_put_contents(
                    __DIR__ . '/../../storage/logs/mail.log',
                    "[" . date('Y-m-d H:i:s') . "] [DEV] Tautan verifikasi email pelanggan untuk {$email}: {$verifyUrl}\n",
                    FILE_APPEND | LOCK_EX
                );
                Session::setFlash('success', 'Akun pelanggan Anda berhasil dibuat! Verifikasi email bersifat opsional untuk keamanan akun. [Mode Dev] Link verifikasi email: ' . $verifyUrl);
            } else {
                Session::setFlash('success', 'Akun pelanggan Anda berhasil dibuat! Kami telah mengirimkan tautan verifikasi ke email Anda. Verifikasi email bersifat opsional untuk membantu keamanan akun dan pemulihan password.');
            }

            $this->redirect('/customer/dashboard');
        } catch (Exception $e) {
            $pdo->rollBack();
            Session::setFlash('error', 'Terjadi kesalahan sistem saat membuat akun: ' . $e->getMessage());
            $this->redirect('/daftar/pelanggan');
        }
    }

    /**
     * Partner registration view: /daftar/mitra
     */
    public function partnerView(): void
    {
        if (Session::has('user')) {
            $this->redirect('/dashboard');
            return;
        }

        $this->render('auth/register_partner', [
            'title'      => 'Daftar Mitra Laundry - MarketplaceLaundry',
            'flashError' => Session::getFlash('error'),
        ]);
    }

    /**
     * Process Partner registration POST
     */
    public function partnerStore(): void
    {
        $this->verifyCsrfOrAbort('/daftar/mitra');

        $clientIp = RateLimiter::getClientIp();
        $rateLimitKey = 'register_partner_' . hash('sha256', $clientIp);
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10, 900)) {
            $minutes = ceil(RateLimiter::availableIn($rateLimitKey, 900) / 60);
            Session::setFlash('error', "Terlalu banyak permintaan pendaftaran mitra. Silakan tunggu {$minutes} menit.");
            $this->redirect('/daftar/mitra');
            return;
        }
        RateLimiter::hit($rateLimitKey, 900);

        // Step 1: Account Info
        $name     = trim((string) ($_POST['name'] ?? ''));
        $email    = Formatter::normalizeEmail($_POST['email'] ?? '');
        $phone    = Formatter::normalizePhone($_POST['phone'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirmation'] ?? '');

        // Step 2: Store Info
        $namaUsaha      = trim((string) ($_POST['nama_usaha'] ?? ''));
        $alamat         = trim((string) ($_POST['alamat'] ?? ''));
        $lat            = !empty($_POST['lat']) ? (float) $_POST['lat'] : -6.20880000;
        $lng            = !empty($_POST['lng']) ? (float) $_POST['lng'] : 106.84560000;
        $jamOperasional = trim((string) ($_POST['jam_operasional'] ?? '08:00 - 20:00'));

        // Step 3: Initial Services & Docs
        $hargaKiloan   = !empty($_POST['harga_kiloan']) ? (float) $_POST['harga_kiloan'] : 8000.0;
        $hargaSatuan   = !empty($_POST['harga_satuan']) ? (float) $_POST['harga_satuan'] : 15000.0;
        $hargaExpress  = !empty($_POST['harga_express']) ? (float) $_POST['harga_express'] : 15000.0;

        // Validation
        if (empty($name) || strlen($name) < 3) {
            Session::setFlash('error', 'Nama pemilik laundry minimal 3 karakter.');
            $this->redirect('/daftar/mitra');
            return;
        }

        if (!Formatter::isValidEmail($email)) {
            Session::setFlash('error', 'Format email tidak valid (contoh: nama@email.com atau akun@test).');
            $this->redirect('/daftar/mitra');
            return;
        }

        if (!Formatter::isValidPhone($phone)) {
            Session::setFlash('error', 'Nomor telepon/WhatsApp wajib nomor HP Indonesia yang valid.');
            $this->redirect('/daftar/mitra');
            return;
        }

        if (!Formatter::isValidPassword($password)) {
            Session::setFlash('error', 'Password wajib diisi.');
            $this->redirect('/daftar/mitra');
            return;
        }

        if ($password !== $passwordConfirm) {
            Session::setFlash('error', 'Konfirmasi password tidak cocok.');
            $this->redirect('/daftar/mitra');
            return;
        }

        if (empty($namaUsaha)) {
            Session::setFlash('error', 'Nama usaha laundry wajib diisi.');
            $this->redirect('/daftar/mitra');
            return;
        }

        if (empty($alamat)) {
            Session::setFlash('error', 'Alamat lengkap usaha laundry wajib diisi.');
            $this->redirect('/daftar/mitra');
            return;
        }

        $userModel = new User();
        if ($userModel->isEmailTaken($email)) {
            Session::setFlash('error', 'Email sudah terdaftar. Silakan gunakan email lain.');
            $this->redirect('/daftar/mitra');
            return;
        }

        if ($userModel->isPhoneTaken($phone)) {
            Session::setFlash('error', 'Nomor telepon sudah terdaftar di sistem.');
            $this->redirect('/daftar/mitra');
            return;
        }

        // File upload
        $docPath = $this->handleFileUpload('dokumen', 'partners');

        // Multi-table write in PDO transaction
        $pdo = BaseModel::getPdo();
        $pdo->beginTransaction();

        try {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $stmtUser = $pdo->prepare(
                "INSERT INTO users (google_id, name, email, password, phone, role, status, email_verified_at)
                 VALUES (NULL, :name, :email, :password, :phone, 'owner', 'menunggu_verifikasi', NULL)"
            );
            $stmtUser->execute([
                'name'     => $name,
                'email'    => $email,
                'password' => $passwordHash,
                'phone'    => $phone,
            ]);
            $userId = (int) $pdo->lastInsertId();

            // Insert into partners
            $stmtPartner = $pdo->prepare(
                "INSERT INTO partners (user_id, nama_usaha, alamat, lat, lng, jam_operasional, dokumen, status_verifikasi)
                 VALUES (:uid, :nama_usaha, :alamat, :lat, :lng, :jam, :dok, 'menunggu_verifikasi')"
            );
            $stmtPartner->execute([
                'uid'        => $userId,
                'nama_usaha' => $namaUsaha,
                'alamat'     => $alamat,
                'lat'        => $lat,
                'lng'        => $lng,
                'jam'        => $jamOperasional,
                'dok'        => $docPath
            ]);
            $partnerId = (int) $pdo->lastInsertId();

            // Batch add initial services
            $stmtPs = $pdo->prepare(
                "INSERT INTO partner_services (partner_id, nama_layanan, tipe, harga) VALUES (:pid, :nama, :tipe, :harga)"
            );
            $initialServices = [
                ['Cuci Kiloan Reguler', 'kiloan', $hargaKiloan],
                ['Cuci Satuan Kemeja / Jas', 'satuan', $hargaSatuan],
                ['Cuci Kiloan Express', 'express', $hargaExpress]
            ];
            foreach ($initialServices as $srv) {
                $stmtPs->execute([
                    'pid'   => $partnerId,
                    'nama'  => $srv[0],
                    'tipe'  => $srv[1],
                    'harga' => $srv[2]
                ]);
            }

            // Create stores record (initially is_open = 0 until approved)
            $stmtStore = $pdo->prepare(
                "INSERT INTO stores (owner_id, store_name, phone, full_address, latitude, longitude, is_open)
                 VALUES (:owner_id, :store_name, :phone, :full_address, :latitude, :longitude, 0)"
            );
            $stmtStore->execute([
                'owner_id'     => $userId,
                'store_name'   => $namaUsaha,
                'phone'        => $phone,
                'full_address' => $alamat,
                'latitude'     => $lat,
                'longitude'    => $lng
            ]);
            $storeId = (int) $pdo->lastInsertId();

            // Create default services for order engine
            $stmtSvc = $pdo->prepare(
                "INSERT INTO services (store_id, service_name, unit, price_per_unit, estimated_duration_hours, description)
                 VALUES (:store_id, :name, 'kg', :price, :dur, :desc)"
            );
            $stmtSvc->execute([
                'store_id' => $storeId,
                'name'     => 'Cuci Kiloan Reguler',
                'price'    => $hargaKiloan,
                'dur'      => 24,
                'desc'     => 'Layanan cuci lipat wangi kiloan reguler.'
            ]);
            $stmtSvc->execute([
                'store_id' => $storeId,
                'name'     => 'Cuci Kiloan Express',
                'price'    => $hargaExpress,
                'dur'      => 6,
                'desc'     => 'Layanan cuci express kilat selesai dalam 6 jam.'
            ]);

            $pdo->commit();

            $freshUser = $userModel->find($userId);
            Session::regenerate();
            Session::set('user', User::sanitizeSessionUser($freshUser));

            Session::setFlash('warning', 'Pendaftaran Mitra Laundry berhasil dikirim! Akun Anda sedang dalam proses verifikasi tim Admin.');
            $this->redirect('/verifikasi-menunggu');
        } catch (Exception $e) {
            $pdo->rollBack();
            Session::setFlash('error', 'Terjadi kesalahan sistem saat mendaftarkan mitra: ' . $e->getMessage());
            $this->redirect('/daftar/mitra');
        }
    }

    /**
     * Courier registration view: /daftar/kurir
     */
    public function courierView(): void
    {
        if (Session::has('user')) {
            $this->redirect('/dashboard');
            return;
        }

        $this->render('auth/register_courier', [
            'title'      => 'Daftar Kurir Laundry - MarketplaceLaundry',
            'flashError' => Session::getFlash('error'),
        ]);
    }

    /**
     * Process Courier registration POST
     */
    public function courierStore(): void
    {
        $this->verifyCsrfOrAbort('/daftar/kurir');

        $clientIp = RateLimiter::getClientIp();
        $rateLimitKey = 'register_courier_' . hash('sha256', $clientIp);
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10, 900)) {
            $minutes = ceil(RateLimiter::availableIn($rateLimitKey, 900) / 60);
            Session::setFlash('error', "Terlalu banyak permintaan pendaftaran kurir. Silakan tunggu {$minutes} menit.");
            $this->redirect('/daftar/kurir');
            return;
        }
        RateLimiter::hit($rateLimitKey, 900);

        $name     = trim((string) ($_POST['name'] ?? ''));
        $email    = Formatter::normalizeEmail($_POST['email'] ?? '');
        $phone    = Formatter::normalizePhone($_POST['phone'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirmation'] ?? '');
        $jenisKendaraan  = trim((string) ($_POST['jenis_kendaraan'] ?? 'Motor'));
        $platNomor       = strtoupper(trim((string) ($_POST['plat_nomor'] ?? '')));

        if (empty($name) || strlen($name) < 3) {
            Session::setFlash('error', 'Nama lengkap minimal 3 karakter.');
            $this->redirect('/daftar/kurir');
            return;
        }

        if (!Formatter::isValidEmail($email)) {
            Session::setFlash('error', 'Format email tidak valid (contoh: nama@email.com atau akun@test).');
            $this->redirect('/daftar/kurir');
            return;
        }

        if (!Formatter::isValidPhone($phone)) {
            Session::setFlash('error', 'Nomor telepon/WhatsApp wajib nomor HP Indonesia yang valid.');
            $this->redirect('/daftar/kurir');
            return;
        }

        if (!Formatter::isValidPassword($password)) {
            Session::setFlash('error', 'Password wajib diisi.');
            $this->redirect('/daftar/kurir');
            return;
        }

        if ($password !== $passwordConfirm) {
            Session::setFlash('error', 'Konfirmasi password tidak cocok.');
            $this->redirect('/daftar/kurir');
            return;
        }

        if (empty($platNomor)) {
            Session::setFlash('error', 'Nomor plat kendaraan wajib diisi.');
            $this->redirect('/daftar/kurir');
            return;
        }

        $userModel = new User();
        if ($userModel->isEmailTaken($email)) {
            Session::setFlash('error', 'Email sudah terdaftar.');
            $this->redirect('/daftar/kurir');
            return;
        }

        if ($userModel->isPhoneTaken($phone)) {
            Session::setFlash('error', 'Nomor telepon sudah terdaftar di sistem.');
            $this->redirect('/daftar/kurir');
            return;
        }

        $docPath = $this->handleFileUpload('dokumen', 'documents');

        $pdo = BaseModel::getPdo();
        $pdo->beginTransaction();

        try {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $stmtUser = $pdo->prepare(
                "INSERT INTO users (google_id, name, email, password, phone, role, status, email_verified_at)
                 VALUES (NULL, :name, :email, :password, :phone, 'driver', 'menunggu_verifikasi', NULL)"
            );
            $stmtUser->execute([
                'name'     => $name,
                'email'    => $email,
                'password' => $passwordHash,
                'phone'    => $phone,
            ]);
            $userId = (int) $pdo->lastInsertId();

            $stmtCourier = $pdo->prepare(
                "INSERT INTO couriers (user_id, jenis_kendaraan, plat_nomor, dokumen, status_verifikasi)
                 VALUES (:uid, :jenis, :plat, :dok, 'menunggu_verifikasi')"
            );
            $stmtCourier->execute([
                'uid'   => $userId,
                'jenis' => $jenisKendaraan,
                'plat'  => $platNomor,
                'dok'   => $docPath
            ]);

            // Create driver entry with is_available = 0
            $stmtDriver = $pdo->prepare(
                "INSERT INTO drivers (user_id, vehicle_type, license_plate, is_available, current_latitude, current_longitude)
                 VALUES (:uid, :vehicle, :license, 0, -6.20880000, 106.84560000)"
            );
            $stmtDriver->execute([
                'uid'     => $userId,
                'vehicle' => $jenisKendaraan,
                'license' => $platNomor
            ]);

            $pdo->commit();

            $freshUser = $userModel->find($userId);
            Session::regenerate();
            Session::set('user', User::sanitizeSessionUser($freshUser));

            Session::setFlash('warning', 'Pendaftaran Kurir berhasil dikirim! Akun Anda sedang dalam proses verifikasi tim Admin.');
            $this->redirect('/verifikasi-menunggu');
        } catch (Exception $e) {
            $pdo->rollBack();
            Session::setFlash('error', 'Terjadi kesalahan sistem saat mendaftar kurir: ' . $e->getMessage());
            $this->redirect('/daftar/kurir');
        }
    }

    /**
     * Pending verification view: /verifikasi-menunggu
     */
    public function pendingVerificationView(): void
    {
        $sessionUser = Session::get('user');
        if (!$sessionUser) {
            $this->redirect('/login');
            return;
        }

        $userModel = new User();
        $user = $userModel->find((int) $sessionUser['id']);

        if (!$user) {
            $this->redirect('/login');
            return;
        }

        $details = null;
        $role = strtolower($user['role'] ?? '');
        if ($role === 'owner' || $role === 'mitra') {
            $partnerModel = new Partner();
            $details = $partnerModel->findByUserId((int) $user['id']);
        } elseif ($role === 'driver' || $role === 'kurir') {
            $courierModel = new Courier();
            $details = $courierModel->findByUserId((int) $user['id']);
        }

        if (($user['status'] ?? '') === 'aktif') {
            Session::setFlash('success', 'Akun Anda sudah terverifikasi dan aktif.');
            $this->redirect('/dashboard');
            return;
        }

        $this->render('auth/verification_pending', [
            'title'   => 'Menunggu Verifikasi Admin - MarketplaceLaundry',
            'user'    => $user,
            'details' => $details
        ]);
    }

    /**
     * Helper to safely upload documents
     */
    private function handleFileUpload(string $fieldName, string $subfolder): ?string
    {
        if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $file = $_FILES[$fieldName];
        $maxBytes = 2 * 1024 * 1024;

        if ($file['size'] > $maxBytes) {
            Session::setFlash('error', 'Ukuran file dokumen melebihi batas 2MB.');
            return null;
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if (!in_array($mime, $allowedMimes, true)) {
            Session::setFlash('error', 'Format file tidak didukung. Harap upload gambar (JPG/PNG/WEBP) atau PDF.');
            return null;
        }

        $ext = match ($mime) {
            'image/jpeg'        => 'jpg',
            'image/png'         => 'png',
            'image/webp'        => 'webp',
            'application/pdf'   => 'pdf',
            default             => 'bin'
        };

        $targetDir = __DIR__ . '/../../public/uploads/' . $subfolder;
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $filename = uniqid('doc_', true) . '.' . $ext;
        $targetPath = $targetDir . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return '/uploads/' . $subfolder . '/' . $filename;
        }

        return null;
    }

    /**
     * Helper to verify CSRF token
     */
    private function verifyCsrfOrAbort(string $redirectUrl): void
    {
        $token = $_POST['_token'] ?? null;
        if (!Session::verifyCsrfToken($token)) {
            Session::setFlash('error', 'Validasi keamanan (CSRF Token) gagal atau sesi Anda kedaluwarsa. Silakan coba kembali.');
            $this->redirect($redirectUrl);
            exit;
        }
    }
}

