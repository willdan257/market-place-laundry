<?php

namespace App\Controllers;

use App\Helpers\Session;
use App\Helpers\Formatter;
use App\Helpers\Env;
use App\Models\User;
use App\Models\CustomerAddress;
use App\Services\MailService;
use App\Services\RateLimiter;

class ProfileController extends BaseController
{
    public function profileView(): void
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

        $addressModel = new CustomerAddress();
        $primaryAddress = $addressModel->getPrimaryAddress((int) $user['id']);

        $this->render('profile/index', [
            'title'        => 'Profil Saya - Marketplace Laundry',
            'user'         => $user,
            'address'      => $primaryAddress,
            'flashSuccess' => Session::getFlash('success'),
            'flashError'   => Session::getFlash('error'),
            'flashWarning' => Session::getFlash('warning')
        ]);
    }

    public function editProfileView(): void
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

        $isGoogleUser = !empty($user['google_id']);
        $hasPassword = !empty($user['password']);

        $this->render('profile/edit', [
            'title'        => 'Edit Profil & Akun - Marketplace Laundry',
            'user'         => $user,
            'isGoogleUser' => $isGoogleUser,
            'hasPassword'  => $hasPassword,
            'flashSuccess' => Session::getFlash('success'),
            'flashError'   => Session::getFlash('error'),
            'flashWarning' => Session::getFlash('warning')
        ]);
    }

    public function updateProfile(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::verifyCsrfToken($token)) {
            Session::setFlash('error', 'Token keamanan CSRF tidak valid. Silakan coba kembali.');
            $this->redirect('/profile/edit');
            return;
        }

        $sessionUser = Session::get('user');
        if (!$sessionUser) {
            $this->redirect('/login');
            return;
        }

        $userId = (int) $sessionUser['id'];
        $userModel = new User();
        $freshUser = $userModel->find($userId);

        $name = trim((string) ($_POST['name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $email = Formatter::normalizeEmail($_POST['email'] ?? '');
        $currentPassword = (string) ($_POST['current_password'] ?? '');

        // 1. Validation
        if (strlen($name) < 3) {
            Session::setFlash('error', 'Nama lengkap minimal 3 karakter.');
            $this->redirect('/profile/edit');
            return;
        }

        $normalizedPhone = Formatter::normalizePhone($phone);
        if (!Formatter::isValidPhone($phone)) {
            Session::setFlash('error', 'Nomor telepon tidak valid. Masukkan nomor HP Indonesia yang aktif (contoh: 081234567890).');
            $this->redirect('/profile/edit');
            return;
        }

        if ($userModel->isPhoneTaken($normalizedPhone, $userId)) {
            Session::setFlash('error', 'Nomor telepon sudah digunakan oleh akun lain.');
            $this->redirect('/profile/edit');
            return;
        }

        $isGoogleUser = !empty($freshUser['google_id']);
        $emailChanged = false;
        $pendingEmailMsg = '';

        // 2. Email Change Logic
        if (!$isGoogleUser && !empty($email) && $email !== $freshUser['email']) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Session::setFlash('error', 'Format alamat email baru tidak valid.');
                $this->redirect('/profile/edit');
                return;
            }

            if ($userModel->isEmailTaken($email, $userId)) {
                Session::setFlash('error', 'Alamat email baru sudah digunakan oleh akun lain.');
                $this->redirect('/profile/edit');
                return;
            }

            // Require current password for email change
            if (empty($currentPassword) || empty($freshUser['password']) || !password_verify($currentPassword, $freshUser['password'])) {
                Session::setFlash('error', 'Password saat ini wajib diisi dengan benar untuk mengubah alamat email.');
                $this->redirect('/profile/edit');
                return;
            }

            // Save as pending email + create verification token (60 mins expiry)
            $userModel->setPendingEmail($userId, $email);
            $rawToken = $userModel->createEmailVerification($userId, $email, 'email_change');

            $appUrl = rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/');
            $verifyUrl = "{$appUrl}/verifikasi-email-baru/{$rawToken}";

            $mailService = new MailService();
            $mailService->sendNewEmailVerification($email, $name, $verifyUrl);

            $emailChanged = true;
            $pendingEmailMsg = " Permintaan perubahan email ke {$email} telah dikirim. Email lama tetap aktif sampai Anda mengklik link konfirmasi.";
        }

        // 3. Update User Profile (Name & Phone)
        $userModel->updateProfile($userId, [
            'name'  => $name,
            'phone' => $normalizedPhone
        ]);

        // Refresh Session
        $updatedUser = $userModel->find($userId);
        Session::set('user', User::sanitizeSessionUser($updatedUser));

        Session::setFlash('success', 'Profil Anda berhasil diperbarui!' . $pendingEmailMsg);
        $this->redirect('/profile');
    }

    public function changePassword(): void
    {
        $token = $_POST['_token'] ?? '';
        if (!Session::verifyCsrfToken($token)) {
            Session::setFlash('error', 'Token keamanan CSRF tidak valid.');
            $this->redirect('/profile/edit');
            return;
        }

        $sessionUser = Session::get('user');
        if (!$sessionUser) {
            $this->redirect('/login');
            return;
        }

        $userId = (int) $sessionUser['id'];
        $userModel = new User();
        $freshUser = $userModel->find($userId);

        $hasPassword = !empty($freshUser['password']);
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $newPasswordConfirmation = (string) ($_POST['new_password_confirmation'] ?? '');

        if ($hasPassword) {
            if (empty($currentPassword) || !password_verify($currentPassword, $freshUser['password'])) {
                Session::setFlash('error', 'Password lama (saat ini) salah.');
                $this->redirect('/profile/edit');
                return;
            }
        }

        if (!Formatter::isValidPassword($newPassword)) {
            Session::setFlash('error', 'Password baru wajib diisi.');
            $this->redirect('/profile/edit');
            return;
        }

        if ($newPassword !== $newPasswordConfirmation) {
            Session::setFlash('error', 'Konfirmasi password baru tidak cocok.');
            $this->redirect('/profile/edit');
            return;
        }

        $userModel->updatePassword($userId, $newPassword);

        Session::setFlash('success', $hasPassword ? 'Password berhasil diperbarui!' : 'Password berhasil dibuat!');
        $this->redirect('/profile/edit');
    }

    public function verifyNewEmail(string $token): void
    {
        $userModel = new User();
        $record = $userModel->verifyEmailToken($token);

        if (!$record || $record['type'] !== 'email_change') {
            Session::setFlash('error', 'Tautan konfirmasi email baru tidak valid atau telah kedaluwarsa.');
            $this->redirect('/profile');
            return;
        }

        $userId = (int) $record['user_id'];
        $newEmail = $record['email'];

        // Confirm new email in users table & mark token used
        $userModel->confirmNewEmail($userId, $newEmail);
        $userModel->markEmailVerified($userId, (int) $record['id']);

        // Refresh session if logged in user is this user
        $sessionUser = Session::get('user');
        if ($sessionUser && (int) ($sessionUser['id'] ?? 0) === $userId) {
            $freshUser = $userModel->find($userId);
            Session::set('user', User::sanitizeSessionUser($freshUser));
        }

        Session::setFlash('success', "🎉 Alamat email Anda berhasil diperbarui menjadi {$newEmail}!");
        $this->redirect('/profile');
    }

    public function completeProfileView(): void
    {
        $sessionUser = Session::get('user');
        $userModel = new User();
        $user = $userModel->find((int) ($sessionUser['id'] ?? 0));
        if (!$user) {
            $this->redirect('/login');
            return;
        }

        $addressModel = new CustomerAddress();
        $primaryAddress = $addressModel->getPrimaryAddress($user['id']);

        $this->render('profile/complete', [
            'title'   => 'Lengkapi Profil Pelanggan - Marketplace Laundry',
            'user'    => $user,
            'address' => $primaryAddress,
            'errors'  => Session::getFlash('errors_data') ? json_decode(Session::getFlash('errors_data'), true) : []
        ]);
    }

    public function saveCompleteProfile(): void
    {
        $sessionUser = Session::get('user');
        if (!$sessionUser) {
            $this->redirect('/login');
            return;
        }

        $token = $_POST['_token'] ?? '';
        if (!Session::verifyCsrfToken($token)) {
            Session::setFlash('error', 'Token keamanan CSRF tidak valid.');
            $this->redirect('/profile/complete');
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['full_address'] ?? '');
        $rt = trim($_POST['rt'] ?? '');
        $rw = trim($_POST['rw'] ?? '');
        $lat = trim($_POST['latitude'] ?? '');
        $lng = trim($_POST['longitude'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $city = trim($_POST['city'] ?? '');

        $errors = [];

        if (strlen($name) < 3) {
            $errors['name'] = 'Nama lengkap minimal 3 karakter.';
        }

        if (!Formatter::isValidPhone($phone)) {
            $errors['phone'] = 'Nomor telepon wajib diisi dengan format nomor HP Indonesia yang valid (contoh: 081234567890).';
        }

        $userModel = new User();
        $normalizedPhone = Formatter::normalizePhone($phone);
        if ($userModel->isPhoneTaken($normalizedPhone, (int) $sessionUser['id'])) {
            $errors['phone'] = 'Nomor telepon sudah terdaftar di sistem.';
        }

        if (empty($address) || strlen($address) < 10) {
            $errors['full_address'] = 'Alamat lengkap wajib diisi minimal 10 karakter.';
        }
        if (empty($rt) || !preg_match('/^[0-9]{1,4}$/', $rt)) {
            $errors['rt'] = 'RT wajib diisi dengan angka (contoh: 001).';
        }
        if (empty($rw) || !preg_match('/^[0-9]{1,4}$/', $rw)) {
            $errors['rw'] = 'RW wajib diisi dengan angka (contoh: 005).';
        }
        if ($lat === '' || !is_numeric($lat) || $lat < -90 || $lat > 90) {
            $errors['latitude'] = 'Latitude wajib numerik (-90 hingga 90).';
        }
        if ($lng === '' || !is_numeric($lng) || $lng < -180 || $lng > 180) {
            $errors['longitude'] = 'Longitude wajib numerik (-180 hingga 180).';
        }

        if (!empty($errors)) {
            Session::setFlash('error', 'Terdapat kesalahan pada isian form profil Anda.');
            Session::setFlash('errors_data', json_encode($errors));
            $this->redirect('/profile/complete');
            return;
        }

        $userId = (int) $sessionUser['id'];

        // Save User & Address Data
        $userModel->updateProfile($userId, [
            'name'  => $name,
            'phone' => $normalizedPhone
        ]);

        $addressModel = new CustomerAddress();
        $addressModel->savePrimaryAddress($userId, [
            'recipient_name'  => $name,
            'recipient_phone' => $normalizedPhone,
            'full_address'    => $address,
            'rt'              => $rt,
            'rw'              => $rw,
            'district'        => $district,
            'city'            => $city,
            'latitude'        => (float) $lat,
            'longitude'       => (float) $lng
        ]);

        // Refresh Session User Data
        $updatedUser = $userModel->find($userId);
        Session::set('user', User::sanitizeSessionUser($updatedUser));

        Session::setFlash('success', 'Profil dan alamat berhasil diperbarui!');
        $this->redirect('/customer/dashboard');
    }
}
