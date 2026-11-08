<?php

namespace App\Middlewares;

use App\Helpers\Session;
use App\Helpers\Response;
use App\Helpers\Formatter;
use App\Models\User;

class ProfileCompleteMiddleware
{
    public function handle(): bool
    {
        $user = Session::get('user');
        if (!$user) {
            Response::redirect('/login');
            return false;
        }

        // Only enforce profile completeness for Customer role
        $role = strtolower($user['role'] ?? 'customer');
        if (in_array($role, ['customer', 'pelanggan'], true)) {
            $userModel = new User();
            $dbUser = $userModel->find((int) $user['id']);
            if (!$dbUser) {
                Response::redirect('/login');
                return false;
            }

            // Syarat nomor HP wajib diisi dan valid
            if (empty($dbUser['phone']) || !Formatter::isValidPhone($dbUser['phone'])) {
                Session::setFlash('error', 'Nomor HP wajib diisi dengan benar sebelum memesan.');
                Response::redirect('/profile/complete');
                return false;
            }

            // Syarat alamat penjemputan lengkap (RT, RW, koordinat)
            if (!$userModel->isProfileComplete((int) $user['id'])) {
                Session::setFlash('error', 'Silakan lengkapi data profil Anda (Nomor HP, Alamat, RT, RW, Koordinat) sebelum melanjutkan.');
                Response::redirect('/profile/complete');
                return false;
            }
        }

        return true;
    }
}
