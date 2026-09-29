<?php
/**
 * 定时任务：同步上游产品价格（每天凌晨）
 * Cron: 0 3 * * * php /path/to/cron/sync_products.php >> /path/to/storage/logs/cron.log 2>&1
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\CronService;

echo '[' . date('Y-m-d H:i:s') . "] sync_products start\n";
try {
    $results = CronService::syncProducts();
    foreach ($results as $name => $msg) {
        echo "  {$name}: {$msg}\n";
    }
} catch (\Throwable $e) {
    echo '  ERROR: ' . $e->getMessage() . "\n";
    \App\Core\Logger::log('cron sync_products: ' . $e->getMessage());
}
echo '[' . date('Y-m-d H:i:s') . "] sync_products end\n";
