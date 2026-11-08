<?php

namespace App\Services;

use App\Helpers\Env;
use Exception;

class GoogleMapsService
{
    private string $apiKey;
    private string $cacheDir;
    private int $cacheTtlSeconds;

    public function __construct()
    {
        $this->apiKey = Env::get('GOOGLE_MAPS_API_KEY', '');
        $this->cacheDir = __DIR__ . '/../../storage/cache/google_maps/';
        $this->cacheTtlSeconds = 86400; // 24 hours cache TTL

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Get driving distance (in km) between two coordinate pairs.
     * Uses Google Maps Distance Matrix API.
     * Implements file-based caching to avoid redundant API calls.
     *
     * @throws Exception if API key missing, network fails, or API returns error
     */
    public function getDrivingDistanceKm(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng
    ): float {
        if (empty($this->apiKey) || $this->apiKey === 'your_google_maps_api_key_here') {
            $isLocal = (Env::get('APP_ENV', 'local') === 'local') || (Env::get('APP_DEBUG', 'true') === 'true');
            if ($isLocal) {
                return $this->calculateHaversineDistanceKm($originLat, $originLng, $destLat, $destLng);
            }
            throw new Exception(
                'Google Maps API Key belum dikonfigurasi. Tambahkan GOOGLE_MAPS_API_KEY pada file .env'
            );
        }

        // Build cache key from rounded coordinates (4 decimal places = ~11m precision)
        $cacheKey = sprintf(
            'dist_%s_%s_%s_%s',
            round($originLat, 4),
            round($originLng, 4),
            round($destLat, 4),
            round($destLng, 4)
        );
        $cacheFile = $this->cacheDir . md5($cacheKey) . '.json';

        // Return cached result if still valid
        $cachedResult = $this->readCache($cacheFile);
        if ($cachedResult !== null) {
            return $cachedResult;
        }

        // Build API URL
        $url = sprintf(
            'https://maps.googleapis.com/maps/api/distancematrix/json?origins=%s,%s&destinations=%s,%s&mode=driving&key=%s',
            $originLat,
            $originLng,
            $destLat,
            $destLng,
            urlencode($this->apiKey)
        );

        $responseBody = $this->httpGet($url);

        $data = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Google Maps API mengembalikan respons yang tidak valid (bukan JSON).');
        }

        // Validate API status
        $apiStatus = $data['status'] ?? 'UNKNOWN';
        if ($apiStatus !== 'OK') {
            $errorMessage = $data['error_message'] ?? "Status: {$apiStatus}";
            throw new Exception("Google Maps API Error: {$errorMessage}");
        }

        // Validate element status
        $elementStatus = $data['rows'][0]['elements'][0]['status'] ?? 'UNKNOWN';
        if ($elementStatus !== 'OK') {
            throw new Exception(
                "Google Maps tidak dapat menghitung rute untuk koordinat yang diberikan. Status: {$elementStatus}. " .
                "Pastikan koordinat customer dan toko valid."
            );
        }

        // Distance in meters -> convert to km
        $distanceMeters = (int) ($data['rows'][0]['elements'][0]['distance']['value'] ?? 0);
        if ($distanceMeters === 0) {
            throw new Exception('Google Maps mengembalikan jarak 0. Periksa koordinat origin dan destination.');
        }

        $distanceKm = round($distanceMeters / 1000, 2);

        // Save to cache
        $this->writeCache($cacheFile, $distanceKm);

        return $distanceKm;
    }

    /**
     * HTTP GET using cURL with timeout.
     */
    private function httpGet(string $url): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT      => 'MarketplaceLaundry/1.0',
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === false || !empty($curlError)) {
                throw new Exception("Koneksi ke Google Maps API gagal: {$curlError}");
            }
            if ($httpCode !== 200) {
                throw new Exception("Google Maps API mengembalikan HTTP {$httpCode}.");
            }
            return $response;
        }

        // Fallback: file_get_contents with stream context
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'method'  => 'GET',
                'header'  => "User-Agent: MarketplaceLaundry/1.0\r\n"
            ],
            'ssl' => ['verify_peer' => true]
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new Exception('Koneksi ke Google Maps API gagal. Periksa koneksi internet server.');
        }
        return $response;
    }

    /**
     * Read distance result from file cache if still valid.
     */
    private function readCache(string $cacheFile): ?float
    {
        if (!file_exists($cacheFile)) {
            return null;
        }
        $mtime = filemtime($cacheFile);
        if ((time() - $mtime) > $this->cacheTtlSeconds) {
            @unlink($cacheFile);
            return null;
        }
        $data = json_decode(file_get_contents($cacheFile), true);
        return isset($data['distance_km']) ? (float) $data['distance_km'] : null;
    }

    /**
     * Write distance result to file cache.
     */
    private function writeCache(string $cacheFile, float $distanceKm): void
    {
        file_put_contents($cacheFile, json_encode([
            'distance_km' => $distanceKm,
            'cached_at'   => date('Y-m-d H:i:s')
        ]));
    }

    /**
     * Calculate direct distance between coordinates using Haversine formula
     * and apply road curvature factor (1.3x) for local fallback.
     */
    public function calculateHaversineDistanceKm(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $directKm = $earthRadius * $c;

        return max(0.5, round($directKm * 1.3, 2));
    }
}

