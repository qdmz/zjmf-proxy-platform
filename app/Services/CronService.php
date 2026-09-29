<?php
namespace App\Services;

use App\Core\DB;
use App\Core\Logger;

/**
 * 定时任务服务（由 cron/*.php 调用）
 */
class CronService
{
    /** 同步所有启用供货商的实例状态 */
    public static function syncHosts(): array
    {
        $results = [];
        foreach (DB::all("SELECT * FROM `upstream_providers` WHERE `status` = 1") as $p) {
            try {
                $r = HostService::syncAllByProvider((int)$p['id']);
                $results[$p['name']] = $r['msg'] ?? 'ok';
            } catch (\Throwable $e) {
                $results[$p['name']] = '异常: ' . $e->getMessage();
                Logger::log('cron syncHosts ' . $p['name'] . ': ' . $e->getMessage());
            }
        }
        return $results;
    }

    /**
     * 到期检查：
     * - 到期前 N 天（系统设置 renew_remind_days）发送续费提醒
     * - 已过期实例标记（上游会自动暂停/删除，本地同步状态）
     */
    public static function expireCheck(): array
    {
        $days = array_filter(array_map('intval', explode(',', (string)setting('renew_remind_days', '7,3,1'))));
        $today = date('Y-m-d');
        $reminded = 0;
        $expired = 0;

        $hosts = DB::all(
            "SELECT h.*, p.name AS product_name FROM `hosts` h
             LEFT JOIN `products` p ON p.id = h.product_id
             WHERE h.`status` IN ('active','suspended') AND h.`nextduedate` IS NOT NULL"
        );
        foreach ($hosts as $h) {
            $diff = (strtotime($h['nextduedate']) - strtotime($today)) / 86400;
            $diff = (int) floor($diff);
            // 到期提醒
            if (in_array($diff, $days, true)) {
                $exists = DB::count(
                    "SELECT COUNT(*) FROM `messages` WHERE `user_id` = ? AND `title` = ? AND DATE(`created_at`) = ?",
                    [(int)$h['user_id'], "实例即将到期提醒", $today]
                );
                if ($exists === 0) {
                    DB::insert('messages', [
                        'user_id' => (int)$h['user_id'],
                        'title' => '实例即将到期提醒',
                        'content' => "您的实例 {$h['domain']}（{$h['product_name']}）将于 {$h['nextduedate']} 到期（剩余 {$diff} 天），请及时续费以免被暂停。",
                    ]);
                    $reminded++;
                }
            }
            // 已过期 30 天以上且上游仍显示 active，标记为 cancelled（以上游同步为准，这里只做兜底）
            if ($diff < -30 && $h['status'] === 'active') {
                DB::update('hosts', ['status' => 'cancelled'], '`id` = :id', ['id' => $h['id']]);
                $expired++;
            }
        }
        return ['reminded' => $reminded, 'expired_marked' => $expired];
    }

    /** 同步所有供货商产品价格（不改变上下架状态） */
    public static function syncProducts(): array
    {
        $results = [];
        foreach (DB::all("SELECT * FROM `upstream_providers` WHERE `status` = 1") as $p) {
            try {
                $r = UpstreamService::syncProducts((int)$p['id']);
                $results[$p['name']] = $r['msg'] ?? 'ok';
            } catch (\Throwable $e) {
                $results[$p['name']] = '异常: ' . $e->getMessage();
                Logger::log('cron syncProducts ' . $p['name'] . ': ' . $e->getMessage());
            }
        }
        return $results;
    }
}
