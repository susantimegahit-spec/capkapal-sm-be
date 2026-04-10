<?php

namespace App\Modules\Order\Services;

use Illuminate\Support\Facades\Cache;

class ShippingService
{
    private const FLAT_RATES = [
        'flat' => ['flat' => ['price' => 15000, 'etd' => '2-3 hari']],
    ];

    /**
     * Hitung ongkir. Saat ini pakai flat rate.
     * Bisa diganti dengan RajaOngkir / Shipper API di production.
     */
    public function calculate(
        string $courier,
        string $service,
        string $destinationCity,
        float  $totalWeightGram
    ): array {
        $cacheKey = "shipping:{$courier}:{$service}:{$destinationCity}:" . ceil($totalWeightGram / 1000);

        return Cache::remember($cacheKey, now()->addHour(), function () use (
            $courier, $service, $destinationCity, $totalWeightGram
        ) {
            // Flat rate fallback (sebelum integrasi RajaOngkir)
            if ($courier === 'flat') {
                return [
                    'courier'           => 'Flat Rate',
                    'service'           => 'Regular',
                    'price'             => 15000,
                    'estimated_days'    => 3,
                    'estimated_delivery' => now()->addDays(3)->toDateString(),
                ];
            }

            // TODO: integrasi dengan RajaOngkir / Shipper API
            return $this->calculateWithExternalApi($courier, $service, $destinationCity, $totalWeightGram);
        });
    }

    public function getAvailableCouriers(string $destinationCity, float $weightGram): array
    {
        // Stub — akan diisi API call ke RajaOngkir
        return [
            ['courier' => 'jne',     'service' => 'reg', 'price' => 18000, 'etd' => '3-5'],
            ['courier' => 'jne',     'service' => 'yes', 'price' => 35000, 'etd' => '1-2'],
            ['courier' => 'jnt',     'service' => 'reg', 'price' => 16000, 'etd' => '3-5'],
            ['courier' => 'sicepat', 'service' => 'reg', 'price' => 17000, 'etd' => '2-4'],
            ['courier' => 'flat',    'service' => 'flat','price' => 15000, 'etd' => '2-3'],
        ];
    }

    private function calculateWithExternalApi(
        string $courier,
        string $service,
        string $city,
        float  $weightGram
    ): array {
        // Placeholder — implementasi RajaOngkir di sini
        return ['courier' => $courier, 'service' => $service, 'price' => 18000, 'estimated_days' => 3,
            'estimated_delivery' => now()->addDays(3)->toDateString()];
    }
}
