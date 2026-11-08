<?php

namespace App\Helpers;

class OrderStatusHelper
{
    public const LABELS = [
        'pending_pickup'      => ['bg-warning text-dark',   'Menunggu Kurir Ditugaskan'],
        'driver_assigned'     => ['bg-info text-white',     'Kurir Ditugaskan'],
        'driver_on_the_way'   => ['bg-info text-white',     'Kurir Dalam Perjalanan'],
        'arrived_at_customer' => ['bg-primary text-white',  'Kurir Tiba di Lokasi'],
        'picked_up'           => ['bg-warning text-dark',   'Pakaian Telah Dijemput'],
        'arrived_at_store'    => ['bg-success text-white',  'Tiba di Toko — Siap Timbang'],
        'weighed'             => ['bg-secondary text-white', 'Selesai Ditimbang — Menunggu Bayar'],
        'waiting_payment'     => ['bg-warning text-dark',   'Menunggu Pembayaran'],
        'in_process'          => ['bg-primary text-white',  'Sedang Dicuci'],
        'ready_for_delivery'  => ['bg-success text-white',  'Siap Diantar'],
        'delivery_assigned'   => ['bg-info text-white',     'Kurir Pengiriman Ditugaskan'],
        'delivered'           => ['bg-success text-white',  'Sudah Diantar'],
        'completed'           => ['bg-success text-white',  'Selesai'],
        'cancelled'           => ['bg-danger text-white',   'Dibatalkan'],
    ];

    public static function all(): array
    {
        return self::LABELS;
    }

    public static function getBadge(string $status): array
    {
        return self::LABELS[$status] ?? ['bg-secondary text-white', $status];
    }

    public static function getLabel(string $status): string
    {
        return self::getBadge($status)[1];
    }

    public static function getBadgeClass(string $status): string
    {
        return self::getBadge($status)[0];
    }
}

