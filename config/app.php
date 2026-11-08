<?php

use App\Helpers\Env;

return [
    'name' => Env::get('APP_NAME', 'Marketplace Laundry'),
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => (bool) Env::get('APP_DEBUG', false),
    'url' => Env::get('APP_URL', 'http://localhost:8000'),
    'timezone' => 'Asia/Jakarta',

    // Configurable Delivery Fee Tiers (Not hardcoded in controllers)
    'delivery_fees' => [
        'tier_1' => [
            'max_km' => 3.0,
            'fee' => 5000.00
        ],
        'tier_2' => [
            'max_km' => 5.0,
            'fee' => 8000.00
        ],
        'extra_km_rate' => 2000.00 // fee per additional km beyond 5 km
    ]
];
