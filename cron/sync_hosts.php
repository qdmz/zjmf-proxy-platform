<?php
/**
 * 定时任务：同步实例状态
 * Cron: *\/5 * * * * php /path/to/cron/sync_hosts.php >> /path/to/storage/logs/cron.log 2>&1
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\CronService;

echo '[' . date('Y-m-d H:i:s') . "] sync_hosts start\n";
try {
    $results = CronService::syncHosts();
    foreach ($results as $name => $msg) {
        echo "  {$name}: {$msg}\n";
    }
} catch (\Throwable $e) {
    echo '  ERROR: ' . $e->getMessage() . "\n";
    \App\Core\Logger::log('cron sync_hosts: ' . $e->getMessage());
}
echo '[' . date('Y-m-d H:i:s') . "] sync_hosts end\n";
