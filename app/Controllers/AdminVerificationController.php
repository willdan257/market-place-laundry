<?php

namespace App\Controllers;

use App\Helpers\Session;
use App\Models\User;
use App\Models\Partner;
use App\Models\Courier;
use App\Models\Store;
use App\Models\Driver;

class AdminVerificationController extends BaseController
{
    /**
     * Admin dashboard view for partner and courier verifications
     * GET /admin/verifikasi
     */
    public function index(): void
    {
        $sessionUser = Session::get('user');
        $role = strtolower(trim((string) ($sessionUser['role'] ?? '')));
        if (!$sessionUser || $role !== 'admin') {
            http_response_code(403);
            $this->render('403', [
                'title'   => '403 Akses Ditolak - Marketplace Laundry',
                'message' => 'Halaman Admin Console hanya dapat diakses oleh Administrator.'
            ]);
            return;
        }

        $userModel = new User();
        $pdo = $userModel::getPdo();

        // 1. Fetch pending & processed partners
        $sqlPartners = "SELECT p.*, u.name, u.email, u.phone, u.status as user_status, u.created_at as registered_at
                        FROM partners p
                        JOIN users u ON u.id = p.user_id
                        ORDER BY (p.status_verifikasi = 'menunggu_verifikasi') DESC, p.created_at DESC";
        $partners = $userModel->query($sqlPartners);

        // 2. Fetch pending & processed couriers
        $sqlCouriers = "SELECT c.*, u.name, u.email, u.phone, u.status as user_status, u.created_at as registered_at
                        FROM couriers c
                        JOIN users u ON u.id = c.user_id
                        ORDER BY (c.status_verifikasi = 'menunggu_verifikasi') DESC, c.created_at DESC";
        $couriers = $userModel->query($sqlCouriers);

        $pendingPartnersCount = count(array_filter($partners, fn($p) => ($p['status_verifikasi'] ?? '') === 'menunggu_verifikasi'));
        $pendingCouriersCount = count(array_filter($couriers, fn($c) => ($c['status_verifikasi'] ?? '') === 'menunggu_verifikasi'));

        // 3. Fetch customers with search & pagination (20 per page)
        $totalCustomersCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('customer', 'pelanggan')")->fetchColumn();

        $customerSearch = trim((string) ($_GET['customer_search'] ?? ''));
        $customerPage   = max(1, (int) ($_GET['customer_page'] ?? 1));
        $perPage        = 20;
        $offset         = ($customerPage - 1) * $perPage;

        $searchParams = [];
        $whereSearch = "";
        if ($customerSearch !== '') {
            $whereSearch = "AND (u.name LIKE :cs1 OR u.email LIKE :cs2 OR u.phone LIKE :cs3)";
            $searchWildcard = '%' . $customerSearch . '%';
            $searchParams = [
                'cs1' => $searchWildcard,
                'cs2' => $searchWildcard,
                'cs3' => $searchWildcard,
            ];

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE u.role IN ('customer', 'pelanggan') {$whereSearch}");
            $countStmt->execute($searchParams);
            $filteredCustomerCount = (int) $countStmt->fetchColumn();
        } else {
            $filteredCustomerCount = $totalCustomersCount;
        }

        $customerTotalPages = $filteredCustomerCount > 0 ? (int) ceil($filteredCustomerCount / $perPage) : 1;

        $sqlCustomers = "
            SELECT 
                u.id, u.name, u.email, u.phone, u.status as user_status, u.created_at as registered_at,
                COALESCE(c.alamat_utama, (
                    SELECT ca.full_address 
                    FROM customer_addresses ca 
                    WHERE ca.user_id = u.id 
                    ORDER BY ca.is_primary DESC, ca.id DESC 
                    LIMIT 1
                ), '-') as address,
                (SELECT COUNT(*) FROM laundry_orders o WHERE o.customer_id = u.id) as order_count
            FROM users u
            LEFT JOIN customers c ON c.user_id = u.id
            WHERE u.role IN ('customer', 'pelanggan')
              {$whereSearch}
            ORDER BY u.created_at DESC, u.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $custStmt = $pdo->prepare($sqlCustomers);
        foreach ($searchParams as $k => $v) {
            $custStmt->bindValue($k, $v);
        }
        $custStmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $custStmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $custStmt->execute();
        $customers = $custStmt->fetchAll(\PDO::FETCH_ASSOC);

        $activeTab = $_GET['tab'] ?? (isset($_GET['customer_search']) || isset($_GET['customer_page']) ? 'customers' : 'partners');

        $this->render('admin/verification', [
            'title'                => 'Pusat Verifikasi Mitra & Kurir - Marketplace Laundry',
            'user'                 => $sessionUser,
            'partners'             => $partners,
            'couriers'             => $couriers,
            'pendingPartnersCount' => $pendingPartnersCount,
            'pendingCouriersCount' => $pendingCouriersCount,
            'totalCustomersCount'  => $totalCustomersCount,
            'customers'            => $customers,
            'customerSearch'       => $customerSearch,
            'customerPagination'   => [
                'page'        => $customerPage,
                'per_page'    => $perPage,
                'total'       => $filteredCustomerCount,
                'total_pages' => $customerTotalPages,
            ],
            'activeTab'            => $activeTab,
            'flashSuccess'         => Session::getFlash('success'),
            'flashError'           => Session::getFlash('error'),
        ]);
    }

    /**
     * Admin approve partner or courier
     * POST /admin/verifikasi/approve
     */
    public function approve(): void
    {
        $token = $_POST['_token'] ?? null;
        if (!Session::verifyCsrfToken($token)) {
            Session::setFlash('error', 'Token validasi CSRF tidak valid.');
            $this->redirect('/admin/verifikasi');
            return;
        }

        $userId = (int) ($_POST['user_id'] ?? 0);
        $type = trim((string) ($_POST['type'] ?? '')); // 'partner' or 'courier'

        if ($userId <= 0 || !in_array($type, ['partner', 'courier'], true)) {
            Session::setFlash('error', 'Data permohonan verifikasi tidak valid.');
            $this->redirect('/admin/verifikasi');
            return;
        }

        $userModel = new User();
        $targetUser = $userModel->find($userId);

        if (!$targetUser) {
            Session::setFlash('error', 'Pengguna tidak ditemukan.');
            $this->redirect('/admin/verifikasi');
            return;
        }

        // Activate user
        $userModel->updateStatus($userId, 'aktif');

        if ($type === 'partner') {
            $partnerModel = new Partner();
            $partnerModel->updateVerificationStatus($userId, 'disetujui');

            $storeModel = new Store();
            $storeModel->execute("UPDATE stores SET is_open = 1 WHERE owner_id = :uid", ['uid' => $userId]);

            Session::setFlash('success', "Akun Mitra \"{$targetUser['name']}\" berhasil disetujui dan kini telah aktif.");
        } elseif ($type === 'courier') {
            $courierModel = new Courier();
            $courierModel->updateVerificationStatus($userId, 'disetujui');

            $driverModel = new Driver();
            $driverModel->execute("UPDATE drivers SET is_available = 1 WHERE user_id = :uid", ['uid' => $userId]);

            Session::setFlash('success', "Akun Kurir \"{$targetUser['name']}\" berhasil disetujui dan kini telah aktif.");
        }

        $this->redirect('/admin/verifikasi');
    }

    /**
     * Admin reject partner or courier
     * POST /admin/verifikasi/reject
     */
    public function reject(): void
    {
        $token = $_POST['_token'] ?? null;
        if (!Session::verifyCsrfToken($token)) {
            Session::setFlash('error', 'Token validasi CSRF tidak valid.');
            $this->redirect('/admin/verifikasi');
            return;
        }

        $userId = (int) ($_POST['user_id'] ?? 0);
        $type = trim((string) ($_POST['type'] ?? ''));
        $reason = trim((string) ($_POST['reason'] ?? ''));

        if ($userId <= 0 || !in_array($type, ['partner', 'courier'], true)) {
            Session::setFlash('error', 'Data permohonan verifikasi tidak valid.');
            $this->redirect('/admin/verifikasi');
            return;
        }

        if (empty($reason)) {
            $reason = 'Dokumen atau data identitas pendaftaran belum memenuhi syarat verifikasi.';
        }

        $userModel = new User();
        $targetUser = $userModel->find($userId);

        if (!$targetUser) {
            Session::setFlash('error', 'Pengguna tidak ditemukan.');
            $this->redirect('/admin/verifikasi');
            return;
        }

        $userModel->updateStatus($userId, 'ditolak');

        if ($type === 'partner') {
            $partnerModel = new Partner();
            $partnerModel->updateVerificationStatus($userId, 'ditolak', $reason);

            $storeModel = new Store();
            $storeModel->execute("UPDATE stores SET is_open = 0 WHERE owner_id = :uid", ['uid' => $userId]);

            Session::setFlash('error', "Pendaftaran Mitra \"{$targetUser['name']}\" telah ditolak dengan alasan yang tercatat.");
        } elseif ($type === 'courier') {
            $courierModel = new Courier();
            $courierModel->updateVerificationStatus($userId, 'ditolak', $reason);

            $driverModel = new Driver();
            $driverModel->execute("UPDATE drivers SET is_available = 0 WHERE user_id = :uid", ['uid' => $userId]);

            Session::setFlash('error', "Pendaftaran Kurir \"{$targetUser['name']}\" telah ditolak dengan alasan yang tercatat.");
        }

        $this->redirect('/admin/verifikasi');
    }
}
