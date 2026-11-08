<?php

namespace App\Controllers;

use App\Helpers\Session;
use App\Models\OrderChat;

class AdminChatController extends BaseController
{
    /**
     * Memastikan akses khusus peran admin.
     * Mengembalikan true jika admin, atau false & render 403 jika ditolak.
     */
    private function authorizeAdmin(): bool
    {
        $user = Session::get('user');
        if (!$user) {
            $this->redirect('/login');
            return false;
        }

        $role = strtolower(trim((string) ($user['role'] ?? '')));
        if ($role !== 'admin') {
            http_response_code(403);
            $this->render('403', [
                'title'   => '403 Akses Ditolak - Marketplace Laundry',
                'message' => 'Halaman Riwayat Chat hanya diperuntukkan bagi Administrator Sistem.',
            ]);
            return false;
        }

        return true;
    }

    /**
     * Daftar riwayat percakapan chat (Tanya AI & Pesanan).
     * GET /admin/chats[?tab=ai|order]
     */
    public function index(): void
    {
        if (!$this->authorizeAdmin()) {
            return;
        }

        $sessionUser = Session::get('user');

        // Tab aktif: 'ai' (default) atau 'order'
        $activeTab = trim((string) ($_GET['tab'] ?? 'ai'));
        if (!in_array($activeTab, ['ai', 'order'], true)) {
            $activeTab = 'ai';
        }

        $roleFilter = trim((string) ($_GET['role'] ?? ''));
        $search     = trim((string) ($_GET['search'] ?? ''));
        $startDate  = trim((string) ($_GET['start_date'] ?? ''));
        $endDate    = trim((string) ($_GET['end_date'] ?? ''));
        $page       = max(1, (int) ($_GET['page'] ?? 1));

        $filters = [
            'tab'        => $activeTab,
            'role'       => $roleFilter,
            'search'     => $search,
            'start_date' => $startDate,
            'end_date'   => $endDate,
        ];

        $aiChatModel    = new \App\Models\AiChatMessage();
        $orderChatModel = new OrderChat();

        $aiTotalCount     = \App\Models\AiChatMessage::countTotal();
        $orderTotalResult = $orderChatModel->getConversations([], 1, 1);
        $orderTotalCount  = (int) ($orderTotalResult['pagination']['total'] ?? 0);

        if ($activeTab === 'ai') {
            $result = $aiChatModel->getAiConversations($filters, $page, 20);
            $aiMessages = $result['items'];
            $orderConversations = [];
        } else {
            $result = $orderChatModel->getConversations($filters, $page, 20);
            $aiMessages = [];
            $orderConversations = $result['items'];
        }

        $this->render('admin/chat_history', [
            'title'           => 'Riwayat Chat Pelanggan & Mitra - Marketplace Laundry',
            'user'            => $sessionUser,
            'activeTab'       => $activeTab,
            'aiMessages'      => $aiMessages,
            'conversations'   => $orderConversations,
            'pagination'      => $result['pagination'],
            'filters'         => $filters,
            'aiTotalCount'    => $aiTotalCount,
            'orderTotalCount' => $orderTotalCount,
        ]);
    }

    /**
     * Detail percakapan lengkap satu pesanan.
     * GET /admin/chats/{orderId}
     */
    public function detail(int $orderId): void
    {
        if (!$this->authorizeAdmin()) {
            return;
        }

        $sessionUser = Session::get('user');

        if ($orderId <= 0) {
            Session::setFlash('error', 'ID Pesanan tidak valid.');
            $this->redirect('/admin/chats');
            return;
        }

        $chatModel = new OrderChat();
        $detail = $chatModel->getConversationDetail($orderId);

        if (!$detail) {
            Session::setFlash('error', 'Percakapan untuk pesanan tersebut tidak ditemukan.');
            $this->redirect('/admin/chats');
            return;
        }

        $this->render('admin/chat_detail', [
            'title'    => "Riwayat Chat #{$detail['order']['order_number']} - Marketplace Laundry",
            'user'     => $sessionUser,
            'order'    => $detail['order'],
            'messages' => $detail['messages'],
        ]);
    }
}

