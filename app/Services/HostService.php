<?php
namespace App\Services;

use App\Core\DB;
use App\Core\Logger;

/**
 * 主机实例服务：实例管理操作透传到上游
 */
class HostService
{
    /** 允许用户直接调用的操作 */
    public static array $userActions = [
        'on' => '开机', 'off' => '关机', 'reboot' => '重启',
        'hard_off' => '强制关机', 'hard_reboot' => '强制重启',
        'repassword' => '重置密码', 'reinstall' => '重装系统',
        'rescue' => '救援模式', 'vnc' => 'VNC 控制台',
    ];

    public static function get(int $id)
    {
        return DB::get("SELECT * FROM `hosts` WHERE `id` = ? LIMIT 1", [$id]);
    }

    /**
     * 从上游同步实例详情到本地
     */
    public static function syncFromUpstream(int $hostId): array
    {
        $host = self::get($hostId);
        if (!$host || (int)$host['upstream_host_id'] <= 0) {
            return ['ok' => false, 'msg' => '实例未关联上游'];
        }
        $provider = UpstreamService::get((int)$host['provider_id']);
        if (!$provider) {
            return ['ok' => false, 'msg' => '上游供货商不存在'];
        }
        $client = UpstreamService::client($provider);
        $resp = $client->getHost((int)$host['upstream_host_id']);
        if ((int)($resp['status'] ?? 0) !== 200) {
            Logger::upstream((int)$provider['id'], $hostId, 'sync_detail', [], $resp, false);
            return ['ok' => false, 'msg' => '同步失败: ' . ($resp['msg'] ?? '未知错误')];
        }
        $h = $resp['data']['host'] ?? $resp['data'] ?? [];
        // assignedips 上游返回逗号分隔字符串，转为数组
        $assignedIps = $h['assignedips'] ?? $h['assigned_ips'] ?? [];
        if (is_string($assignedIps)) {
            $assignedIps = array_filter(array_map('trim', explode(',', $assignedIps)));
        }
        $update = [
            'domain' => $h['domain'] ?? $host['domain'],
            'username' => $h['username'] ?? $host['username'],
            'dedicated_ip' => $h['dedicatedip'] ?? $h['dedicated_ip'] ?? $host['dedicated_ip'],
            'assigned_ips' => json_encode(array_values($assignedIps), JSON_UNESCAPED_UNICODE),
            'os' => $h['os'] ?? $host['os'],
            'port' => (int)($h['port'] ?? $host['port']),
            'bwlimit' => (string)($h['bwlimit'] ?? $host['bwlimit'] ?? ''),
            'bwusage' => (string)($h['bwusage'] ?? $host['bwusage'] ?? ''),
            'status' => map_upstream_status($h['domainstatus'] ?? ''),
            'billingcycle' => $h['billingcycle'] ?? $host['billingcycle'],
            'regdate' => self::toDate($h['regdate'] ?? null),
            'nextduedate' => self::toDate($h['nextduedate'] ?? null),
            'initiative_renew' => (int)($h['initiative_renew'] ?? 0),
            'suspend_reason' => ($h['suspend_reason'] ?? '') . ($h['suspend_type'] ?? '' ? ' [' . $h['suspend_type'] . ']' : ''),
        ];
        if (!empty($h['password'])) {
            $update['password_enc'] = enc_data((string)$h['password']);
        }
        // 过滤掉 null 日期
        foreach (['regdate', 'nextduedate'] as $k) {
            if ($update[$k] === null) unset($update[$k]);
        }
        DB::update('hosts', $update, '`id` = :id', ['id' => $hostId]);
        Logger::upstream((int)$provider['id'], $hostId, 'sync_detail', [], ['ok' => true], true);
        return ['ok' => true, 'msg' => '同步成功'];
    }

    protected static function toDate($v): ?string
    {
        if (!$v) return null;
        $ts = strtotime((string)$v);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    /**
     * 用户/管理员执行实例操作（透传上游）
     */
    public static function action(int $hostId, string $func, array $params = [], int $operatorId = 0): array
    {
        $host = self::get($hostId);
        if (!$host || (int)$host['upstream_host_id'] <= 0) {
            return ['ok' => false, 'msg' => '实例未关联上游'];
        }
        if (!isset(self::$userActions[$func]) && $operatorId !== -1) {
            return ['ok' => false, 'msg' => '不支持的操作'];
        }
        $provider = UpstreamService::get((int)$host['provider_id']);
        $client = UpstreamService::client($provider);

        // 操作前参数处理
        if ($func === 'repassword' && empty($params['password'])) {
            return ['ok' => false, 'msg' => '请输入新密码'];
        }
        if ($func === 'reinstall' && empty($params['os_id'])) {
            return ['ok' => false, 'msg' => '请选择操作系统'];
        }

        $resp = $client->moduleAction((int)$host['upstream_host_id'], $func, $params);
        $ok = (int)($resp['status'] ?? 0) === 200;
        Logger::upstream((int)$provider['id'], $hostId, 'module_' . $func, $params, $resp, $ok);

        if (!$ok) {
            return ['ok' => false, 'msg' => '上游执行失败: ' . ($resp['msg'] ?? '未知错误')];
        }
        if ($func === 'repassword' && !empty($params['password'])) {
            DB::update('hosts', ['password_enc' => enc_data($params['password'])], '`id` = :id', ['id' => $hostId]);
        }
        // 电源类操作后刷新状态
        if (in_array($func, ['on', 'off', 'reboot', 'hard_off', 'hard_reboot'], true)) {
            // 延迟状态以 query 为准，这里仅记录
        }
        $data = $resp['data'] ?? null;
        return ['ok' => true, 'msg' => self::$userActions[$func] . '指令已发送', 'data' => $data];
    }

    /** 获取上游能力按钮清单 */
    public static function capabilities(int $hostId): array
    {
        $host = self::get($hostId);
        if (!$host || (int)$host['upstream_host_id'] <= 0) {
            return [];
        }
        $provider = UpstreamService::get((int)$host['provider_id']);
        $client = UpstreamService::client($provider);
        $resp = $client->getHostModule((int)$host['upstream_host_id']);
        if ((int)($resp['status'] ?? 0) !== 200) {
            return [];
        }
        return $resp['data']['module'] ?? [];
    }

    /** 获取上游模块完整信息（含自定义区域标签页、NAT信息等） */
    public static function moduleInfo(int $hostId): array
    {
        $host = self::get($hostId);
        if (!$host || (int)$host['upstream_host_id'] <= 0) {
            return [];
        }
        $provider = UpstreamService::get((int)$host['provider_id']);
        $client = UpstreamService::client($provider);
        $resp = $client->getHostModule((int)$host['upstream_host_id']);
        if ((int)($resp['status'] ?? 0) !== 200) {
            return [];
        }
        return $resp['data'] ?? [];
    }

    /** 获取模块自定义标签页内容（如 NAT转发、快照、安全组），上游返回 HTML */
    public static function moduleCustomHtml(int $hostId, string $key): string
    {
        $host = self::get($hostId);
        if (!$host || (int)$host['upstream_host_id'] <= 0 || $key === '') {
            return '';
        }
        $provider = UpstreamService::get((int)$host['provider_id']);
        $client = UpstreamService::client($provider);
        // 上游 /v1/hosts/:id/module/custom?key= 返回 HTML
        $resp = $client->api('GET', '/v1/hosts/' . (int)$host['upstream_host_id'] . '/module/custom', ['key' => $key]);
        // api() 返回数组；若上游直接返回 HTML 字符串，这里做兼容
        if (is_string($resp)) {
            return $resp;
        }
        return (string)($resp['data']['html'] ?? $resp['data'] ?? '');
    }

    /** 批量同步某供货商下所有实例状态（供 cron 调用） */
    public static function syncAllByProvider(int $providerId): array
    {
        $provider = UpstreamService::get($providerId);
        if (!$provider) {
            return ['ok' => false, 'msg' => '供货商不存在'];
        }
        $client = UpstreamService::client($provider);
        $page = 1;
        $updated = 0;
        $map = [];
        foreach (DB::all("SELECT `id`,`upstream_host_id` FROM `hosts` WHERE `provider_id` = ? AND `upstream_host_id` > 0", [$providerId]) as $h) {
            $map[(int)$h['upstream_host_id']] = (int)$h['id'];
        }
        if (!$map) {
            return ['ok' => true, 'msg' => '无实例需要同步', 'updated' => 0];
        }
        do {
            $resp = $client->getHosts(['page' => $page, 'limit' => 100]);
            if ((int)($resp['status'] ?? 0) !== 200) {
                return ['ok' => false, 'msg' => '拉取上游实例失败: ' . ($resp['msg'] ?? '')];
            }
            $list = $resp['data']['host'] ?? [];
            foreach ($list as $uh) {
                $uid = (int)($uh['id'] ?? 0);
                if (isset($map[$uid])) {
                    DB::update('hosts', [
                        'status' => map_upstream_status($uh['domainstatus'] ?? ''),
                        'nextduedate' => self::toDate($uh['nextduedate'] ?? null) ?? DB::get("SELECT `nextduedate` FROM `hosts` WHERE `id`=?", [$map[$uid]])['nextduedate'],
                        'dedicated_ip' => $uh['dedicatedip'] ?? '',
                    ], '`id` = :id', ['id' => $map[$uid]]);
                    $updated++;
                }
            }
            $total = (int)($resp['data']['total'] ?? 0);
            $page++;
        } while (($page - 1) * 100 < $total);
        return ['ok' => true, 'msg' => "同步完成，更新 {$updated} 个实例", 'updated' => $updated];
    }
}
