<?php

namespace App\Console\Commands;

use App\Modules\Admin\Services\DashboardService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class GenerateDailyReport extends Command
{
    protected $signature   = 'admin:generate-daily-report';
    protected $description = 'Pre-generate dan cache KPI dashboard harian';

    public function handle(DashboardService $service): void
    {
        $this->info('Generating daily dashboard cache...');

        // Flush cache lama
        Cache::forget('admin:dashboard:kpis:today');
        Cache::forget('admin:dashboard:kpis:week');
        Cache::forget('admin:dashboard:kpis:month');
        Cache::forget('admin:dashboard:revenue_chart:day:30');
        Cache::forget('admin:dashboard:top_products:10');

        // Pre-warm cache baru
        $service->getKpis('today');
        $service->getKpis('week');
        $service->getKpis('month');
        $service->getRevenueChart('day', 30);
        $service->getTopProducts(10);

        $this->info('Dashboard cache refreshed successfully.');
    }
}
