<?php

use App\Services\AdminDashboardFinance;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// Read-only aggregate benchmark. Run as the application user with a release path.
// It does not print customer data, create users or bypass HTTP authentication.
$root = $argv[1] ?? dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (in_array('--uncached', $argv, true)) {
    config(['performance.portal_cache_enabled' => false]);
}

foreach (range(1, 3) as $iteration) {
    DB::enableQueryLog();
    DB::flushQueryLog();
    $start = microtime(true);
    app(AdminDashboardFinance::class)->snapshot(30);
    echo json_encode([
        'component' => 'admin_finance_30_days',
        'iteration' => $iteration,
        'queries' => count(DB::getQueryLog()),
        'elapsed_ms' => round((microtime(true) - $start) * 1000, 2),
    ]), PHP_EOL;
    DB::disableQueryLog();
}
