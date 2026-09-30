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
            $detail = $resp['msg'] ?? '';
            if ($detail === '' && isset($resp['status'])) {
                $detail = '上游接口返回 status=' . $resp['status'];
            }
            if ($detail === '') {
                $detail = '未知错误（上游无响应）';
            }
            return ['ok' => false, 'msg' => '同步失败: ' . $detail];
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
        try {
            DB::update('hosts', $update, '`id` = :id', ['id' => $hostId]);
        } catch (\Throwable $e) {
            // 列不存在时（如 bwusage/suspend_reason 未执行升级 SQL），降级为只更新基础字段
            if (stripos($e->getMessage(), 'unknown column') !== false) {
                unset($update['bwusage'], $update['suspend_reason']);
                try {
                    DB::update('hosts', $update, '`id` = :id', ['id' => $hostId]);
                } catch (\Throwable $e2) {
                    return ['ok' => false, 'msg' => '同步失败: 写库异常(' . $e2->getMessage() . ')，请先执行 database/upgrade_20260930_host_sync_cols.sql'];
                }
                Logger::upstream((int)$provider['id'], $hostId, 'sync_detail', [], ['ok' => true, 'note' => 'downgraded: missing cols'], true);
                return ['ok' => true, 'msg' => '同步成功（部分字段需升级数据库后才能同步）'];
            }
            throw $e;
        }
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
        // VNC URL 处理：上游可能返回它自己的域名，改写为本站域名以使用我们的 novnc 代理页
        if ($func === 'vnc' && is_array($data) && !empty($data['url'])) {
            $data['url'] = self::rewriteVncUrl((string)$data['url']);
            // 检查 token 是否为空（上游 VNC 服务异常时 token 为空）
            if (preg_match('/token=(&|$)/', $data['url'])) {
                Logger::upstream((int)$provider['id'], $hostId, 'module_vnc_empty_token', [], $resp, false);
            }
        }
        return ['ok' => true, 'msg' => self::$userActions[$func] . '指令已发送', 'data' => $data];
    }

    /**
     * 改写 VNC URL 为本站域名
     * 上游返回如 https://upstream.com/dcim/novnc?url=... 时，改为 https://本站/dcim/novnc?url=...
     */
    protected static function rewriteVncUrl(string $url): string
    {
        $parts = parse_url($url);
        if (empty($parts['host'])) {
            return $url;
        }
        // 如果已经是本站域名，直接返回
        $siteHost = $_SERVER['HTTP_HOST'] ?? '';
        if ($parts['host'] === $siteHost) {
            return $url;
        }
        // 只改写 /dcim/novnc 路径的 URL（上游代理型 VNC）
        $path = $parts['path'] ?? '';
        if (strpos($path, '/dcim/novnc') !== 0 && strpos($path, '/novnc') === false) {
            return $url; // 外部直连型 VNC，不改写
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $newUrl = $scheme . '://' . $siteHost . $path;
        if (!empty($parts['query'])) {
            $newUrl .= '?' . $parts['query'];
        }
        return $newUrl;
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
            Logger::upstream((int)$provider['id'], $hostId, 'module_caps', [], $resp, false);
            return [];
        }
        $data = $resp['data'] ?? [];
        // 格式1：带 nat=1 的完整结构，按钮在 module_button.control/console
        if (isset($data['module_button'])) {
            $buttons = [];
            foreach (['control', 'console'] as $group) {
                foreach ((array)($data['module_button'][$group] ?? []) as $btn) {
                    // 上游按钮用 func 或 function 字段
                    $func = $btn['func'] ?? $btn['function'] ?? null;
                    if ($func) {
                        $buttons[$func] = $btn;
                    }
                }
            }
            return ['button' => $buttons, '_raw' => $data];
        }
        // 格式2：默认扁平结构，$data['module'] 是功能数组
        return $data['module'] ?? $data;
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
        Logger::upstream((int)$provider['id'], $hostId, 'module_info', [], $resp, (int)($resp['status'] ?? 0) === 200);
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
