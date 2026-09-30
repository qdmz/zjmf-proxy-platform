<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Logger;
use App\Services\HostService;
use App\Services\OrderService;
use App\Services\UpstreamService;

/**
 * 用户控制台：我的服务器
 */
class ConsoleController extends Controller
{
    public function index(): string
    {
        $user = $this->requireLogin();
        $hosts = DB::all(
            "SELECT h.*, p.name AS product_name, up.name AS provider_name
             FROM `hosts` h
             LEFT JOIN `products` p ON p.id = h.product_id
             LEFT JOIN `upstream_providers` up ON up.id = h.provider_id
             WHERE h.`user_id` = ?
               AND h.`status` != 'failed'
               AND h.`upstream_host_id` > 0
             ORDER BY h.`id` DESC",
            [(int)$user['id']]
        );
        $unread = DB::count("SELECT COUNT(*) FROM `messages` WHERE `user_id` = ? AND `is_read` = 0", [(int)$user['id']]);
        return $this->view('console/index', [
            'title' => '控制台',
            'hosts' => $hosts,
            'user' => $user,
            'unread' => $unread,
        ]);
    }

    public function host(string $id): string
    {
        $user = $this->requireLogin();
        $host = DB::get(
            "SELECT h.*, p.name AS product_name, p.type AS product_type, up.name AS provider_name
             FROM `hosts` h
             LEFT JOIN `products` p ON p.id = h.product_id
             LEFT JOIN `upstream_providers` up ON up.id = h.provider_id
             WHERE h.`id` = ? AND h.`user_id` = ? LIMIT 1",
            [(int)$id, (int)$user['id']]
        );
        if (!$host) {
            http_response_code(404);
            return $this->view('errors/404', ['title' => '实例不存在']);
        }
        $host['password'] = $host['password_enc'] ? dec_data($host['password_enc']) : '';
        $host['assigned_ips_arr'] = json_decode($host['assigned_ips'] ?? '[]', true) ?: [];
        $caps = HostService::capabilities((int)$host['id']);
        $moduleInfo = HostService::moduleInfo((int)$host['id']);
        $prices = DB::all("SELECT * FROM `product_prices` WHERE `product_id` = ? ORDER BY `price`", [(int)$host['product_id']]);
        return $this->view('console/host', [
            'title' => '实例管理',
            'host' => $host,
            'caps' => $caps,
            'moduleInfo' => $moduleInfo,
            'prices' => $prices,
            'actions' => HostService::$userActions,
            'user' => $user,
        ]);
    }

    /** VNC noVNC 代理页面（上游返回本站域名的 novnc 地址时使用） */
    public function novnc(): string
    {
        $user = $this->requireLogin();
        return $this->view('console/novnc', [
            'title' => 'VNC 控制台',
            'user' => $user,
        ]);
    }

    /** 模块自定义标签页内容（NAT转发/快照/安全组等，AJAX 返回上游 HTML） */
    public function moduleTab(string $id): void
    {
        $user = $this->requireLogin();
        $host = DB::get("SELECT * FROM `hosts` WHERE `id` = ? AND `user_id` = ? LIMIT 1", [(int)$id, (int)$user['id']]);
        if (!$host) {
            $this->fail('实例不存在');
        }
        $key = trim($_GET['key'] ?? '');
        if ($key === '') {
            $this->fail('参数错误');
        }
        $html = HostService::moduleCustomHtml((int)$host['id'], $key);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    /** 实例操作（AJAX） */
    public function action(string $id): void
    {
        $user = $this->requireLogin();
        $host = DB::get("SELECT * FROM `hosts` WHERE `id` = ? AND `user_id` = ? LIMIT 1", [(int)$id, (int)$user['id']]);
        if (!$host) {
            $this->fail('实例不存在');
        }
        $func = $_POST['func'] ?? '';
        $params = [];
        if ($func === 'repassword') {
            $params['password'] = $_POST['password'] ?? '';
        } elseif ($func === 'reinstall') {
            $params['os_id'] = $_POST['os_id'] ?? '';
            if (!empty($_POST['port'])) $params['port'] = (int)$_POST['port'];
        } elseif ($func === 'rescue') {
            $params['rescue_id'] = (int)($_POST['rescue_id'] ?? 1);
        }
        set_time_limit(90);
        $ret = HostService::action((int)$host['id'], $func, $params);
        if (!$ret['ok']) {
            $this->fail($ret['msg']);
        }
        // VNC 返回控制台地址（含上游原地址）
        if ($func === 'vnc' && !empty($ret['data']['url'])) {
            $out = ['url' => $ret['data']['url']];
            if (!empty($ret['data']['url_upstream'])) {
                $out['url_upstream'] = $ret['data']['url_upstream'];
            }
            $this->json($out, $ret['msg']);
        }
        $this->json(null, $ret['msg']);
    }

    /** 可重装系统列表（AJAX） */
    public function reinstallOs(string $id): void
    {
        $user = $this->requireLogin();
        $host = DB::get("SELECT * FROM `hosts` WHERE `id` = ? AND `user_id` = ? LIMIT 1", [(int)$id, (int)$user['id']]);
        if (!$host || (int)$host['upstream_host_id'] <= 0) {
            $this->fail('实例不存在');
        }
        $provider = UpstreamService::get((int)$host['provider_id']);
        $client = UpstreamService::client($provider);
        $resp = $client->getReinstallOs((int)$host['upstream_host_id']);
        if ((int)($resp['status'] ?? 0) !== 200) {
            $this->fail('获取系统列表失败: ' . ($resp['msg'] ?? ''));
        }
        $this->json($resp['data']['os'] ?? []);
    }

    /** 自动续费开关（AJAX） */
    public function autoRenew(string $id): void
    {
        $user = $this->requireLogin();
        $host = DB::get("SELECT * FROM `hosts` WHERE `id` = ? AND `user_id` = ? LIMIT 1", [(int)$id, (int)$user['id']]);
        if (!$host || (int)$host['upstream_host_id'] <= 0) {
            $this->fail('实例不存在');
        }
        $on = !empty($_POST['on']) ? 1 : 0;
        $provider = UpstreamService::get((int)$host['provider_id']);
        $client = UpstreamService::client($provider);
        set_time_limit(60);
        $resp = $client->setAutoRenew((int)$host['upstream_host_id'], $on);
        $ok = (int)($resp['status'] ?? 0) === 200;
        Logger::upstream((int)$provider['id'], (int)$host['id'], 'set_auto_renew', ['on' => $on], $resp, $ok);
        if (!$ok) {
            $this->fail('上游执行失败: ' . ($resp['msg'] ?? '未知错误'));
        }
        DB::update('hosts', ['initiative_renew' => $on], '`id` = :id', ['id' => (int)$host['id']]);
        $this->json(['on' => $on], $on ? '自动续费已开启' : '自动续费已关闭');
    }

    /** 同步实例信息（AJAX） */
    public function sync(string $id): void
    {
        $user = $this->requireLogin();
        $host = DB::get("SELECT * FROM `hosts` WHERE `id` = ? AND `user_id` = ? LIMIT 1", [(int)$id, (int)$user['id']]);
        if (!$host) {
            $this->fail('实例不存在');
        }
        $ret = HostService::syncFromUpstream((int)$host['id']);
        $ret['ok'] ? $this->json(null, $ret['msg']) : $this->fail($ret['msg']);
    }

    /** 实例电源状态查询（AJAX） */
    public function powerStatus(string $id): void
    {
        $user = $this->requireLogin();
        $host = DB::get("SELECT * FROM `hosts` WHERE `id` = ? AND `user_id` = ? LIMIT 1", [(int)$id, (int)$user['id']]);
        if (!$host || (int)$host['upstream_host_id'] <= 0) {
            $this->fail('实例不存在');
        }
        $provider = UpstreamService::get((int)$host['provider_id']);
        $client = UpstreamService::client($provider);
        $resp = $client->getModuleStatus((int)$host['upstream_host_id'], 'host');
        if ((int)($resp['status'] ?? 0) !== 200) {
            $this->fail($resp['msg'] ?? '查询失败');
        }
        $this->json($resp['data']);
    }

    /** 续费下单 */
    public function renew(string $id): void
    {
        $user = $this->requireLogin();
        csrf_check();
        $cycle = $_POST['billingcycle'] ?? 'monthly';
        $couponCode = trim($_POST['coupon_code'] ?? '');
        $ret = OrderService::createRenewOrder((int)$user['id'], (int)$id, $cycle, $couponCode);
        if (!$ret['ok']) {
            flash('error', $ret['msg']);
            redirect('/console/host/' . (int)$id);
        }
        redirect('/pay/' . $ret['bill_no']);
    }

    /** 升降级配置页面 */
    public function upgrade(string $id): string
    {
        $user = $this->requireLogin();
        $host = DB::get("SELECT * FROM `hosts` WHERE `id` = ? AND `user_id` = ? LIMIT 1", [(int)$id, (int)$user['id']]);
        if (!$host || (int)$host['upstream_host_id'] <= 0) {
            http_response_code(404);
            return $this->view('errors/404', ['title' => '实例不存在']);
        }
        $provider = UpstreamService::get((int)$host['provider_id']);
        $client = UpstreamService::client($provider);
        // 获取可升降级配置项
        $resp = $client->upgradeConfig((int)$host['upstream_host_id'], 'GET');
        $options = [];
        $err = '';
        if ((int)($resp['status'] ?? 0) === 200) {
            $options = $resp['data']['options'] ?? $resp['data'] ?? [];
        } else {
            $err = $resp['msg'] ?? '该产品不支持升降级';
        }
        return $this->view('console/upgrade', [
            'title' => '升降级配置',
            'host' => $host,
            'options' => $options,
            'err' => $err,
            'user' => $user,
        ]);
    }

    /** 升降级报价（AJAX） */
    public function upgradeQuote(string $id): void
    {
        $user = $this->requireLogin();
        $host = DB::get("SELECT * FROM `hosts` WHERE `id` = ? AND `user_id` = ? LIMIT 1", [(int)$id, (int)$user['id']]);
        if (!$host || (int)$host['upstream_host_id'] <= 0) {
            $this->fail('实例不存在');
        }
        $configoption = $_POST['configoption'] ?? [];
        if (!is_array($configoption) || !$configoption) {
            $this->fail('请选择要变更的配置项');
        }
        $provider = UpstreamService::get((int)$host['provider_id']);
        $client = UpstreamService::client($provider);
        $resp = $client->upgradeConfig((int)$host['upstream_host_id'], 'POST', ['configoption' => $configoption]);
        if ((int)($resp['status'] ?? 0) !== 200) {
            $this->fail('报价失败: ' . ($resp['msg'] ?? '未知错误'));
        }
        $this->ok($resp['data'] ?? []);
    }

    /** 升降级结算（余额支付） */
    public function upgradeCheckout(string $id): void
    {
        $user = $this->requireLogin();
        csrf_check();
        $host = DB::get("SELECT * FROM `hosts` WHERE `id` = ? AND `user_id` = ? LIMIT 1", [(int)$id, (int)$user['id']]);
        if (!$host || (int)$host['upstream_host_id'] <= 0) {
            flash('error', '实例不存在');
            redirect('/console/host/' . (int)$id);
        }
        $provider = UpstreamService::get((int)$host['provider_id']);
        $client = UpstreamService::client($provider);
        set_time_limit(90);
        $resp = $client->upgradeConfigCheckout((int)$host['upstream_host_id']);
        if ((int)($resp['status'] ?? 0) !== 200) {
            flash('error', '升降级失败: ' . ($resp['msg'] ?? '未知错误'));
            redirect('/console/host/' . (int)$id . '/upgrade');
        }
        // 上游扣费成功，同步实例信息
        HostService::syncFromUpstream((int)$host['id']);
        flash('success', '升降级成功，配置已更新');
        redirect('/console/host/' . (int)$id);
    }

    /** 退订申请 */
    public function cancel(string $id): void
    {
        $user = $this->requireLogin();
        csrf_check();
        $host = DB::get("SELECT * FROM `hosts` WHERE `id` = ? AND `user_id` = ? LIMIT 1", [(int)$id, (int)$user['id']]);
        if (!$host || (int)$host['upstream_host_id'] <= 0) {
            flash('error', '实例不存在');
            redirect('/console/host/' . (int)$id);
        }
        $type = $_POST['type'] ?? 'Endofbilling';
        if (!in_array($type, ['Immediate', 'Endofbilling'], true)) {
            $type = 'Endofbilling';
        }
        $provider = UpstreamService::get((int)$host['provider_id']);
        $client = UpstreamService::client($provider);
        set_time_limit(60);
        $resp = $client->cancel((int)$host['upstream_host_id'], $type, '用户自助退订');
        if ((int)($resp['status'] ?? 0) !== 200) {
            flash('error', '退订失败: ' . ($resp['msg'] ?? '未知错误'));
        } else {
            if ($type === 'Immediate') {
                DB::update('hosts', ['status' => 'cancelled'], '`id` = :id', ['id' => $host['id']]);
            }
            flash('success', $type === 'Immediate' ? '已提交立即删除，稍后生效' : '已提交到期删除申请');
        }
        redirect('/console/host/' . (int)$id);
    }

    /** 站内消息 */
    public function messages(): string
    {
        $user = $this->requireLogin();
        $page = $this->page();
        $per = $this->perPage();
        $total = DB::count("SELECT COUNT(*) FROM `messages` WHERE `user_id` = ?", [(int)$user['id']]);
        $list = DB::all(
            "SELECT * FROM `messages` WHERE `user_id` = ? ORDER BY `id` DESC LIMIT ? OFFSET ?",
            [(int)$user['id'], $per, ($page - 1) * $per]
        );
        DB::update('messages', ['is_read' => 1], '`user_id` = :uid', ['uid' => (int)$user['id']]);
        return $this->view('console/messages', [
            'title' => '站内消息',
            'list' => $list,
            'pagination' => paginate($total, $page, $per, '/messages'),
            'user' => $user,
        ]);
    }
}
