<?php

namespace App\Services;

use App\Helpers\Env;

class MapsService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = Env::get('GOOGLE_MAPS_API_KEY', '');
    }

    /**
     * Calculate distance between two coordinate points in kilometers using Haversine formula (fallback) or Maps API.
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }
}
