<?php

namespace App\Services;

use App\Models\BaseModel;
use App\Services\AI\AIException;
use App\Services\AI\AILogger;
use App\Services\AI\AIProviderInterface;
use App\Services\AI\AIRateLimiter;
use App\Services\AI\AnthropicProvider;
use App\Services\AI\GeminiProvider;
use App\Services\AI\NousProvider;
use PDO;
use Exception;

/**
 * Service untuk menangani kalkulasi analitik toko laundry dan ringkasan insight AI.
 * 
 * Keamanan & Integritas:
 * - Bersifat STRICT READ-ONLY: Sama sekali tidak menjalankan UPDATE/INSERT/DELETE pada order, transaksi, atau database bisnis.
 * - AI hanya diberikan ringkasan metrik statistik hasil query backend.
 * - Filter periode: 'today', 'week', 'month', 'custom' (dengan validasi rentang tanggal).
 */
class OwnerAnalyticsService
{
    private array $config;
    private string $appName;
    private ?AIProviderInterface $provider;
    private AIRateLimiter $rateLimiter;

    public function __construct(?array $config = null, ?AIProviderInterface $provider = null)
    {
        $root = dirname(__DIR__, 2);
        $this->config = $config ?? require $root . '/config/ai.php';
        $appConfig = file_exists($root . '/config/app.php') ? require $root . '/config/app.php' : [];
        $this->appName = (string) ($appConfig['name'] ?? 'Marketplace Laundry');
        // Assign provider only if a valid AIProviderInterface instance is supplied.
        if ($provider instanceof \App\Services\AI\AIProviderInterface) {
            $this->provider = $provider;
        } else {
            $this->provider = null; // defer to resolveProvider() for lazy creation
        }

        // Rate limiter untuk owner request insight
        $this->rateLimiter = new AIRateLimiter(
            max(1, (int) ($this->config['rate_limit']['per_minute'] ?? 10)),
            max(1, (int) ($this->config['rate_limit']['per_day'] ?? 200))
        );
    }

    public function isConfigured(): bool
    {
        $key = $this->config['api_key'] ?? '';
        return $key !== '' && !str_starts_with(strtolower($key), 'your_');
    }

    /**
     * Hitung metrik analitik toko berdasarkan filter periode waktu yang aman.
     *
     * @param int $storeId
     * @param string $filter 'today' | 'week' | 'month' | 'custom'
     * @param string|null $startDate 'YYYY-MM-DD'
     * @param string|null $endDate 'YYYY-MM-DD'
     * @return array
     */
    public function getAnalyticsData(int $storeId, string $filter = 'week', ?string $startDate = null, ?string $endDate = null): array
    {
        $pdo = BaseModel::getPdo();
        if (!$pdo) {
            throw new Exception("Koneksi database tidak tersedia.");
        }

        // Tentukan rentang tanggal
        $now = new \DateTime('now', new \DateTimeZone('Asia/Jakarta'));
        $periodLabel = '';
        $startSql = '';
        $endSql = '';

        // Initialize display dates for period label
        $displayStart = '';
        $displayEnd = '';
        switch ($filter) {
            case 'today':
                $startSql = $now->format('Y-m-d 00:00:00');
                $endSql = $now->format('Y-m-d 23:59:59');
                $periodLabel = 'Hari Ini (' . $now->format('d M Y') . ')';
                // also set start and end as dates for display
                $displayStart = $now->format('d M Y');
                $displayEnd = $now->format('d M Y');
                break;

            case 'month':
                $startMonth = clone $now;
                $startMonth->modify('first day of this month')->setTime(0, 0, 0);
                $endMonth = clone $now;
                $endMonth->modify('last day of this month')->setTime(23, 59, 59);
                $startSql = $startMonth->format('Y-m-d H:i:s');
                $endSql = $endMonth->format('Y-m-d H:i:s');
                $periodLabel = $startMonth->format('d M Y') . ' - ' . $endMonth->format('d M Y');
                $displayStart = $startMonth->format('d M Y');
                $displayEnd = $endMonth->format('d M Y');
                break;

            case 'custom':
                if (!empty($startDate) && !empty($endDate)) {
                    $sDate = \DateTime::createFromFormat('Y-m-d', substr($startDate, 0, 10));
                    $eDate = \DateTime::createFromFormat('Y-m-d', substr($endDate, 0, 10));

                    if ($sDate && $eDate) {
                        if ($sDate > $eDate) {
                            $tmp = $sDate;
                            $sDate = $eDate;
                            $eDate = $tmp;
                        }
                        $sDate->setTime(0, 0, 0);
                        $eDate->setTime(23, 59, 59);
                        $startSql = $sDate->format('Y-m-d H:i:s');
                        $endSql = $eDate->format('Y-m-d H:i:s');
                        $periodLabel = $sDate->format('d M Y') . ' s/d ' . $eDate->format('d M Y');
                        break;
                    }
                }
                // Jika custom tidak valid, fallback ke 'week'
                $filter = 'week';
                // fall through to week

            case 'week':
            default:
                $filter = 'week';
                $startWeek = clone $now;
                // Awal minggu (Senin)
                $dayOfWeek = (int)$startWeek->format('N'); // 1 (Senin) - 7 (Minggu)
                $startWeek->modify('-' . ($dayOfWeek - 1) . ' days')->setTime(0, 0, 0);
                $endWeek = clone $startWeek;
                $endWeek->modify('+6 days')->setTime(23, 59, 59);

                $startSql = $startWeek->format('Y-m-d H:i:s');
                $endSql = $endWeek->format('Y-m-d H:i:s');
                $periodLabel = 'Minggu Ini (' . $startWeek->format('d M') . ' - ' . $endWeek->format('d M Y') . ')';
                break;
        }

        // 1. Hitung Total Order, Selesai, Pending, & Pendapatan
        // Pendapatan toko dan laporan HANYA menghitung pesanan yang sudah lunas (payments.payment_status = 'paid')
        $sqlSummary = "
            SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN o.order_status IN ('delivered', 'completed') THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN o.order_status NOT IN ('delivered', 'completed', 'cancelled') THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN o.order_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                COALESCE(SUM(CASE WHEN p.payment_status = 'paid' AND o.order_status != 'cancelled' THEN o.total_amount ELSE 0 END), 0) as total_revenue
            FROM laundry_orders o
            LEFT JOIN payments p ON p.order_id = o.id
            WHERE o.store_id = :store_id
              AND o.created_at >= :start_date 
              AND o.created_at <= :end_date
        ";

        $stmt = $pdo->prepare($sqlSummary);
        $stmt->execute([
            'store_id'   => $storeId,
            'start_date' => $startSql,
            'end_date'   => $endSql
        ]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_orders'     => 0,
            'completed_orders' => 0,
            'pending_orders'   => 0,
            'cancelled_orders' => 0,
            'total_revenue'    => 0,
        ];

        $totalOrders     = (int) ($summary['total_orders'] ?? 0);
        $completedOrders = (int) ($summary['completed_orders'] ?? 0);
        $pendingOrders   = (int) ($summary['pending_orders'] ?? 0);
        $cancelledOrders = (int) ($summary['cancelled_orders'] ?? 0);
        $totalRevenue    = (float) ($summary['total_revenue'] ?? 0.0);

        // Rata-rata nilai order (hanya untuk order yang bukan cancelled)
        $validOrdersCount = $totalOrders - $cancelledOrders;
        $averageOrderValue = $validOrdersCount > 0 ? round($totalRevenue / $validOrdersCount, 2) : 0.0;

        // 2. Layanan Paling Populer (berdasarkan qty/frekuensi di order_items)
        $sqlPopular = "
            SELECT 
                COALESCE(s.service_name, 'Layanan Tidak Dikenal') as service_name,
                s.unit,
                COUNT(oi.id) as order_frequency,
                COALESCE(SUM(oi.qty), 0) as total_qty,
                COALESCE(SUM(oi.subtotal), 0) as total_sales
            FROM order_items oi
            JOIN laundry_orders o ON oi.order_id = o.id
            LEFT JOIN services s ON oi.service_id = s.id
            WHERE o.store_id = :store_id
              AND o.created_at >= :start_date 
              AND o.created_at <= :end_date
              AND o.order_status != 'cancelled'
            GROUP BY oi.service_id, s.service_name, s.unit
            ORDER BY order_frequency DESC, total_sales DESC
            LIMIT 5
        ";

        $stmtPop = $pdo->prepare($sqlPopular);
        $stmtPop->execute([
            'store_id'   => $storeId,
            'start_date' => $startSql,
            'end_date'   => $endSql
        ]);
        $popularServices = $stmtPop->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $mostPopularServiceName = !empty($popularServices)
            ? $popularServices[0]['service_name']
            : 'Belum ada transaksi';

        // 3. Status breakdown sederhana untuk visualisasi
        $sqlStatusBreakdown = "
            SELECT order_status, COUNT(*) as count
            FROM laundry_orders
            WHERE store_id = :store_id
              AND created_at >= :start_date 
              AND created_at <= :end_date
            GROUP BY order_status
        ";
        $stmtStatus = $pdo->prepare($sqlStatusBreakdown);
        $stmtStatus->execute([
            'store_id'   => $storeId,
            'start_date' => $startSql,
            'end_date'   => $endSql
        ]);
        $statusBreakdown = $stmtStatus->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        return [
            'filter'                  => $filter,
            'start_date'              => substr($startSql, 0, 10),
            'end_date'                => substr($endSql, 0, 10),
            'period_label'            => $periodLabel,
            'total_orders'            => $totalOrders,
            'completed_orders'        => $completedOrders,
            'pending_orders'          => $pendingOrders,
            'cancelled_orders'        => $cancelledOrders,
            'total_revenue'           => $totalRevenue,
            'formatted_revenue'       => 'Rp ' . number_format($totalRevenue, 0, ',', '.'),
            'average_order_value'     => $averageOrderValue,
            'formatted_avg_order'     => 'Rp ' . number_format($averageOrderValue, 0, ',', '.'),
            'most_popular_service'    => $mostPopularServiceName,
            'popular_services'        => $popularServices,
            'status_breakdown'        => $statusBreakdown,
        ];
    }

    /**
     * Menghasilkan AI summary insight berdasarkan data teragregasi di atas.
     * Bersifat STRICT READ-ONLY: AI hanya menerima prompt teks berisi metrik ringkasan.
     */
    public function generateAiInsight(int $ownerUserId, array $analyticsData): string
    {
        // Rate limit check
        $this->rateLimiter->hit('owner_analytics_' . $ownerUserId);

        // Fallback teks jika API AI belum aktif atau error
        $fallbackInsight = sprintf(
            "Pada %s terdapat %d order dengan total pendapatan %s. Layanan paling banyak digunakan adalah %s. Rata-rata nilai order tercatat %s dengan %d order telah selesai dan %d order masih dalam proses.",
            strtolower($analyticsData['period_label']),
            $analyticsData['total_orders'],
            $analyticsData['formatted_revenue'],
            $analyticsData['most_popular_service'],
            $analyticsData['formatted_avg_order'],
            $analyticsData['completed_orders'],
            $analyticsData['pending_orders']
        );

        if (!$this->isConfigured()) {
            return $fallbackInsight;
        }

        // Susun prompt yang ketat dan tidak mengizinkan aksi database
        $systemPrompt = <<<PROMPT
Anda adalah AI Business Analytics Assistant untuk Pemilik Laundry di aplikasi {$this->appName}.
TUGAS ANDA:
Membuat ringkasan eksekutif dan insight bisnis yang ringkas, profesional, ramah, dan memotivasi berdasarkan data statistik yang diberikan oleh backend.

ATURAN KEAMANAN & INTEGRITAS KETAT:
1. HANYA gunakan angka dan data statistik yang disediakan di dalam prompt. JANGAN mengarang data atau berhalusinasi angka di luar fakta.
2. Anda TIDAK MEMILIKI akses ke database dan DILARANG KERAS berpura-pura mengeksekusi perintah SQL atau mengubah data.
3. Jawaban harus langsung dalam Bahasa Indonesia yang lugas (maksimal 2-3 paragraf singkat atau 1 ringkasan padat disertai 2 saran praktis singkat).
4. Contoh format pembuka:
"Pada [periode] terdapat [X] order dengan total pendapatan [Rp Y]. Layanan paling banyak digunakan adalah [Layanan Z]."
5. Berikan 1-2 highlight atau rekomendasi bisnis praktis (misalnya percepatan penyelesaian order pending, promosi layanan populer, dsb).
PROMPT;

        $popularList = '';
        foreach ($analyticsData['popular_services'] as $idx => $srv) {
            $rank = $idx + 1;
            $popularList .= "  {$rank}. {$srv['service_name']}: {$srv['order_frequency']} order ({$srv['total_qty']} {$srv['unit']}), Omset: Rp " . number_format((float)$srv['total_sales'], 0, ',', '.') . "\n";
        }
        if ($popularList === '') {
            $popularList = "  (Belum ada data layanan terjual pada periode ini)\n";
        }

        $userMessage = <<<DATA
Berikut adalah data analytics transaksi toko laundry untuk dianalisis:
- Periode Transaksi: {$analyticsData['period_label']}
- Total Order: {$analyticsData['total_orders']} pesanan
- Order Selesai: {$analyticsData['completed_orders']} pesanan
- Order Pending/Dalam Proses: {$analyticsData['pending_orders']} pesanan
- Order Dibatalkan: {$analyticsData['cancelled_orders']} pesanan
- Total Pendapatan Toko: {$analyticsData['formatted_revenue']}
- Rata-rata Nilai Order: {$analyticsData['formatted_avg_order']}
- Layanan Paling Populer: {$analyticsData['most_popular_service']}
- Daftar Layanan Teratas:
{$popularList}

Buatlah ringkasan eksekutif dan insight analitik yang informatif untuk Owner Laundry sesuai aturan di atas.
DATA;

        try {
            $messages = [
                ['role' => 'user', 'content' => $userMessage]
            ];

            $reply = $this->resolveProvider()->generate($systemPrompt, $messages, 450);
            return trim($reply) ?: $fallbackInsight;
        } catch (\Throwable $e) {
            AILogger::error('owner_analytics_ai_failed', [
                'owner_id' => $ownerUserId,
                'error'    => $e->getMessage()
            ]);
            return $fallbackInsight;
        }
    }

    private function resolveProvider(): AIProviderInterface
    {
        if ($this->provider !== null) {
            return $this->provider;
        }

        $args = [
            $this->config['api_key'],
            $this->config['model'],
            (int) ($this->config['timeout'] ?? 25),
            (int) ($this->config['connect_timeout'] ?? 8),
            $this->config['ca_bundle'] ?? '',
        ];

        switch ($this->config['provider'] ?? 'gemini') {
            case 'nous':
                return $this->provider = new NousProvider(...$args);
            case 'anthropic':
                return $this->provider = new AnthropicProvider(...$args);
            case 'gemini':
            default:
                return $this->provider = new GeminiProvider(...$args);
        }
    }
}
