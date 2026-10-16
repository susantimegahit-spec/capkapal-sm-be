<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeder: data dummy orders Jan–Mei 2026 (status=completed)
 * agar chart di admin dashboard terisi.
 *
 * Jalankan: php artisan db:seed --class=DummyOrderChartSeeder
 */
class DummyOrderChartSeeder extends Seeder
{
    // User IDs dari DB (hanya non-admin)
    private array $userIds = [
        '019df8cb-2267-73fb-9f3e-5e45547b9337', // Aulia Selma
        '019df595-6b03-708f-b459-4ff523aa384b', // John Doe
        '019dc878-e32c-7227-83b4-1bac6f787710', // Agfi Hermawan
        '019e0b6d-3b77-73db-89b0-64352a8dad33', // Agfi
        '019e0b54-f79f-70ef-b1a3-d6e4348128c1', // r
    ];

    // Distribusi orders & revenue per bulan (realistis untuk toko garam)
    // Format: [month (1-12), count, min_total, max_total]
    private array $months = [
        [1, 12, 85000,  320000],  // Januari  — mulai pelan
        [2, 18, 90000,  350000],  // Februari — sedikit naik
        [3, 25, 95000,  400000],  // Maret    — naik signifikan
        [4, 20, 88000,  370000],  // April    — sedikit turun
        [5, 15, 92000,  410000],  // Mei      — stabil
    ];

    private array $couriers = ['JNE', 'J&T', 'SiCepat', 'AnterAja', 'Pos Indonesia'];
    private array $services  = ['REG', 'YES', 'OKE', 'ECONOMIC'];
    private array $provinces = ['Jawa Barat', 'Jawa Tengah', 'Jawa Timur', 'DKI Jakarta', 'Bali'];

    public function run(): void
    {
        $orders    = [];
        $year      = 2026;
        $seqStart  = 1000; // mulai dari GCK-1000 agar tidak bentrok dengan data existing

        foreach ($this->months as [$month, $count, $minTotal, $maxTotal]) {
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

            for ($i = 0; $i < $count; $i++) {
                $seqStart++;
                $day     = rand(1, $daysInMonth);
                $hour    = rand(8, 22);
                $minute  = rand(0, 59);
                $second  = rand(0, 59);
                $createdAt = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);

                $subtotal  = rand((int)($minTotal * 0.8), $maxTotal);
                $shipping  = rand(9000, 25000);
                $total     = $subtotal + $shipping;
                $userId    = $this->userIds[array_rand($this->userIds)];
                $courier   = $this->couriers[array_rand($this->couriers)];
                $service   = $this->services[array_rand($this->services)];
                $province  = $this->provinces[array_rand($this->provinces)];
                $orderNum  = 'GCK-' . $year . sprintf('%02d', $month) . '-' . str_pad($seqStart, 4, '0', STR_PAD_LEFT);

                $completedAt = date('Y-m-d H:i:s', strtotime($createdAt) + rand(86400 * 2, 86400 * 7));

                $orders[] = [
                    'id'               => Str::uuid()->toString(),
                    'user_id'          => $userId,
                    'order_number'     => $orderNum,
                    'status'           => 'completed',
                    'subtotal'         => $subtotal,
                    'discount_amount'  => 0,
                    'coupon_discount'  => 0,
                    'points_discount'  => 0,
                    'shipping_fee'     => $shipping,
                    'total'            => $total,
                    'coupon_code'      => null,
                    'points_used'      => 0,
                    'points_earned'    => (int)($total / 1000),
                    'shipping_courier' => $courier,
                    'shipping_service' => $service,
                    'tracking_number'  => strtoupper(Str::random(12)),
                    'estimated_delivery' => null,
                    'customer_notes'   => null,
                    'admin_notes'      => null,
                    'confirmed_at'     => date('Y-m-d H:i:s', strtotime($createdAt) + 3600),
                    'shipped_at'       => date('Y-m-d H:i:s', strtotime($createdAt) + 86400),
                    'delivered_at'     => date('Y-m-d H:i:s', strtotime($createdAt) + 86400 * 4),
                    'completed_at'     => $completedAt,
                    'cancelled_at'     => null,
                    'created_at'       => $createdAt,
                    'updated_at'       => $completedAt,
                    'deleted_at'       => null,
                ];
            }
        }

        // Insert dalam batch 50
        foreach (array_chunk($orders, 50) as $chunk) {
            DB::table('orders')->insert($chunk);
        }

        $total = count($orders);
        $this->command->info("✅ Inserted {$total} dummy completed orders (Jan–Mei 2026)");

        // Ringkasan per bulan
        $summary = DB::table('orders')
            ->where('status', 'completed')
            ->whereYear('created_at', 2026)
            ->selectRaw("to_char(created_at, 'Mon YYYY') as bulan, COUNT(*) as total_order, SUM(total) as total_revenue")
            ->groupByRaw("to_char(created_at, 'Mon YYYY'), DATE_TRUNC('month', created_at)")
            ->orderByRaw("DATE_TRUNC('month', created_at)")
            ->get();

        $this->command->table(
            ['Bulan', 'Orders', 'Revenue'],
            $summary->map(fn($r) => [$r->bulan, $r->total_order, 'Rp ' . number_format($r->total_revenue, 0, ',', '.')])->toArray()
        );
    }
}
