<?php

namespace App\Controllers;

use App\Helpers\Session;

class DashboardController extends BaseController
{
    public function index(): void
    {
        $user = Session::get('user');
        if (!$user) {
            $this->redirect('/login');
            return;
        }

        $rawRole = strtolower(trim((string) ($user['role'] ?? '')));
        $canonicalRole = match ($rawRole) {
            'admin'                 => 'admin',
            'owner', 'mitra'        => 'owner',
            'driver', 'kurir'       => 'driver',
            'customer', 'pelanggan' => 'customer',
            default                 => null,
        };

        switch ($canonicalRole) {
            case 'admin':
                $this->redirect('/admin/verifikasi');
                return;
            case 'owner':
                $this->redirect('/owner/dashboard');
                return;
            case 'driver':
                $this->redirect('/driver/dashboard');
                return;
            case 'customer':
                $this->redirect('/customer/dashboard');
                return;
            default:
                http_response_code(403);
                $this->render('403', [
                    'title'   => '403 Akses Ditolak - Marketplace Laundry',
                    'message' => 'Peran akun Anda (' . htmlspecialchars($rawRole ?: 'tidak diketahui') . ') tidak valid atau tidak dikenali oleh sistem.'
                ]);
                return;
        }
    }

    public function customerDashboard(): void
    {
        $user = Session::get('user');

        $orderModel   = new \App\Models\Order();
        // Semua order untuk statistik kartu
        $allOrders    = $orderModel->getCustomerOrders($user['id']);
        // Hanya aktif untuk tabel "Pesanan Terbaru"
        $activeOrders = $orderModel->getActiveCustomerOrders($user['id']);

        $totalCount     = count($allOrders);
        $pendingCount   = count($activeOrders);
        $completedCount = count(array_filter($allOrders, fn($o) => in_array($o['order_status'], ['delivered', 'completed'], true)));

        $this->render('dashboard/customer', [
            'title'          => 'Dashboard Customer - Marketplace Laundry',
            'user'           => $user,
            'activeOrders'   => $activeOrders,
            'totalCount'     => $totalCount,
            'pendingCount'   => $pendingCount,
            'completedCount' => $completedCount,
        ]);
    }

    /**
     * Endpoint JSON untuk auto-refresh pesanan aktif dashboard customer
     */
    public function activeCustomerOrdersJson(): void
    {
        $user = Session::get('user');
        if (!$user || empty($user['id'])) {
            \App\Helpers\Response::json(['error' => 'Unauthorized'], 401);
            return;
        }

        $orderModel   = new \App\Models\Order();
        $activeOrders = $orderModel->getActiveCustomerOrders((int)$user['id']);

        $formatted = [];
        foreach ($activeOrders as $o) {
            $badge = \App\Helpers\OrderStatusHelper::getBadge($o['order_status']);
            $formatted[] = [
                'id'                 => (int)$o['id'],
                'order_number'       => $o['order_number'],
                'store_name'         => $o['store_name'] ?? 'Toko Laundry',
                'service_name'       => $o['service_name'] ?? 'Laundry Kiloan',
                'total_amount'       => (float)($o['total_amount'] ?? 0),
                'formatted_total'    => 'Rp ' . number_format((float)($o['total_amount'] ?? 0), 0, ',', '.'),
                'order_status'       => $o['order_status'],
                'status_badge_class' => $badge[0],
                'status_label'       => $badge[1],
                'created_at'         => date('d M Y, H:i', strtotime($o['created_at'])),
            ];
        }

        \App\Helpers\Response::json([
            'success' => true,
            'count'   => count($formatted),
            'orders'  => $formatted,
        ]);
    }

    public function ownerDashboard(): void
    {
        $user = Session::get('user');

        $storeModel = new \App\Models\Store();
        $store = $storeModel->findByOwnerId((int)$user['id']);

        if (!$store) {
            $this->render('dashboard/owner', [
                'title'         => 'Dashboard Owner Laundry - Marketplace Laundry',
                'user'          => $user,
                'store'         => null,
                'analyticsData' => [
                    'total_orders'        => 0,
                    'completed_orders'    => 0,
                    'pending_orders'      => 0,
                    'cancelled_orders'    => 0,
                    'total_revenue'       => 0.0,
                    'formatted_revenue'   => 'Rp 0',
                    'formatted_avg_order' => 'Rp 0',
                    'popular_services'    => [],
                    'most_popular_service'=> '-',
                    'period_label'        => 'Belum ada toko terdaftar',
                    'filter'              => 'week',
                    'start_date'          => '',
                    'end_date'            => ''
                ],
                'storeOrders'       => [],
                'activeStoreOrders' => [],
            ]);
            return;
        }

        $filter    = (string)($_GET['filter'] ?? 'week');
        $startDate = !empty($_GET['start_date']) ? (string)$_GET['start_date'] : null;
        $endDate   = !empty($_GET['end_date'])   ? (string)$_GET['end_date']   : null;

        $analyticsService = new \App\Services\OwnerAnalyticsService();
        $analyticsData    = $analyticsService->getAnalyticsData((int)$store['id'], $filter, $startDate, $endDate);

        $orderModel        = new \App\Models\Order();
        $storeOrders       = $orderModel->getStoreOrders((int)$store['id']);
        $activeStoreOrders = $orderModel->getActiveStoreOrders((int)$store['id']);

        $this->render('dashboard/owner', [
            'title'             => 'Dashboard Owner Laundry - Marketplace Laundry',
            'user'              => $user,
            'store'             => $store,
            'analyticsData'     => $analyticsData,
            'storeOrders'       => $storeOrders,
            'activeStoreOrders' => $activeStoreOrders,
        ]);
    }

    public function driverDashboard(): void
    {
        $user = Session::get('user');

        $driverModel = new \App\Models\Driver();
        $driver      = $driverModel->findByUserId((int)$user['id']);

        $orderModel  = new \App\Models\Order();

        if ($driver) {
            $activeTasks = $orderModel->getActiveDriverTasks((int)$driver['id']);
            $earnings    = $orderModel->getDriverEarnings((int)$driver['id']);
        } else {
            $activeTasks = [];
            $earnings    = [
                'today'           => 0.0,
                'week'            => 0.0,
                'month'           => 0.0,
                'total'           => 0.0,
                'completed_count' => 0,
            ];
        }

        $this->render('dashboard/driver', [
            'title'       => 'Dashboard Kurir - Marketplace Laundry',
            'user'        => $user,
            'driver'      => $driver,
            'activeTasks' => $activeTasks,
            'earnings'    => $earnings,
        ]);
    }
}

