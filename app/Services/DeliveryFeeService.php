<?php

namespace App\Services;

class DeliveryFeeService
{
    private array $config;

    public function __construct(?array $config = null)
    {
        if ($config !== null) {
            $this->config = $config;
        } else {
            $appConfig = require __DIR__ . '/../../config/app.php';
            $this->config = $appConfig['delivery_fees'] ?? [
                'tier_1' => ['max_km' => 3.0, 'fee' => 5000.00],
                'tier_2' => ['max_km' => 5.0, 'fee' => 8000.00],
                'extra_km_rate' => 2000.00
            ];
        }
    }

    /**
     * Calculate delivery fee based on distance in kilometers
     */
    public function calculateFee(float $distanceKm): float
    {
        $distanceKm = max(0.0, round($distanceKm, 2));

        $tier1Max = (float) ($this->config['tier_1']['max_km'] ?? 3.0);
        $tier1Fee = (float) ($this->config['tier_1']['fee'] ?? 5000.00);

        $tier2Max = (float) ($this->config['tier_2']['max_km'] ?? 5.0);
        $tier2Fee = (float) ($this->config['tier_2']['fee'] ?? 8000.00);

        $extraKmRate = (float) ($this->config['extra_km_rate'] ?? 2000.00);

        // Tier 1: 0 - 3 km
        if ($distanceKm <= $tier1Max) {
            return $tier1Fee;
        }

        // Tier 2: > 3 - 5 km
        if ($distanceKm <= $tier2Max) {
            return $tier2Fee;
        }

        // Tier 3: > 5 km -> Base tier 2 fee + additional fee per extra km
        $extraKm = ceil($distanceKm - $tier2Max);
        return $tier2Fee + ($extraKm * $extraKmRate);
    }
}
