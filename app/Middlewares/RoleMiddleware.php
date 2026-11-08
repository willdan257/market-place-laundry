<?php

namespace App\Middlewares;

use App\Helpers\Session;
use App\Helpers\Response;

class RoleMiddleware
{
    private array $allowedRoles;

    public function __construct(array $allowedRoles = [])
    {
        $this->allowedRoles = $allowedRoles;
    }

    public function handle(): bool
    {
        $user = Session::get('user');
        
        if (!$user) {
            Session::setFlash('error', 'Silakan login terlebih dahulu.');
            Response::redirect('/login');
            return false;
        }

        $userRole = strtolower($user['role'] ?? 'customer');
        $canonicalRole = match ($userRole) {
            'pelanggan' => 'customer',
            'mitra'     => 'owner',
            'kurir'     => 'driver',
            default     => $userRole
        };

        // If user is pending verification and trying to access owner/driver dashboards
        if (($user['status'] ?? 'aktif') === 'menunggu_verifikasi' && in_array($canonicalRole, ['owner', 'driver'], true)) {
            Session::setFlash('warning', 'Akun Anda masih dalam proses verifikasi tim Admin. Silakan menunggu persetujuan.');
            Response::redirect('/verifikasi-menunggu');
            return false;
        }

        $allowed = array_map(function ($r) {
            $lr = strtolower($r);
            return match ($lr) {
                'pelanggan' => 'customer',
                'mitra'     => 'owner',
                'kurir'     => 'driver',
                default     => $lr
            };
        }, $this->allowedRoles);

        if (!in_array($canonicalRole, $allowed, true) && !in_array($userRole, $this->allowedRoles, true)) {
            $recognizedRoles = ['admin', 'owner', 'driver', 'customer'];
            if (!in_array($canonicalRole, $recognizedRoles, true)) {
                http_response_code(403);
                echo "403 Forbidden - Role tidak dikenali.";
                return false;
            }

            Session::setFlash('error', 'Akses ditolak! Anda tidak memiliki izin untuk membuka halaman tersebut.');
            $targetDashboard = match ($canonicalRole) {
                'admin'  => '/admin/verifikasi',
                'owner'  => '/owner/dashboard',
                'driver' => '/driver/dashboard',
                default  => '/customer/dashboard',
            };
            Response::redirect($targetDashboard);
            return false;
        }

        return true;
    }
}
