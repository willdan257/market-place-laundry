<?php

namespace App\Middlewares;

use App\Helpers\Session;
use App\Helpers\Response;
use App\Models\User;

class EmailVerifiedMiddleware
{
    public function handle(): bool
    {
        $sessionUser = Session::get('user');
        if (!$sessionUser) {
            Session::setFlash('error', 'Silakan login terlebih dahulu.');
            Response::redirect('/login');
            return false;
        }

        $userId = (int) ($sessionUser['id'] ?? 0);
        $userModel = new User();
        $user = $userModel->find($userId);

        if (!$user) {
            Session::setFlash('error', 'Akun pengguna tidak ditemukan.');
            Response::redirect('/login');
            return false;
        }

        // Only enforce for customer / pelanggan
        $role = strtolower($user['role'] ?? 'customer');
        if (in_array($role, ['customer', 'pelanggan'], true)) {
            if (empty($user['email_verified_at'])) {
                Session::setFlash('warning', 'Alamat email Anda belum terverifikasi. Harap verifikasi email Anda sebelum membuat pesanan laundry.');
                Response::redirect('/customer/dashboard');
                return false;
            }
        }

        return true;
    }
}
