<?php
/**
 * 定时任务：到期检查与续费提醒
 * Cron: 0 9 * * * php /path/to/cron/expire_check.php >> /path/to/storage/logs/cron.log 2>&1
 */
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\CronService;

echo '[' . date('Y-m-d H:i:s') . "] expire_check start\n";
try {
    $r = CronService::expireCheck();
    echo '  续费提醒: ' . $r['reminded'] . " 条，过期标记: " . $r['expired_marked'] . " 个\n";
} catch (\Throwable $e) {
    echo '  ERROR: ' . $e->getMessage() . "\n";
    \App\Core\Logger::log('cron expire_check: ' . $e->getMessage());
}
echo '[' . date('Y-m-d H:i:s') . "] expire_check end\n";
