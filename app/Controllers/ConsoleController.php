<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
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
               AND NOT (h.`status` = 'pending' AND h.`upstream_host_id` = 0)
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
        $prices = DB::all("SELECT * FROM `product_prices` WHERE `product_id` = ? ORDER BY `price`", [(int)$host['product_id']]);
        return $this->view('console/host', [
            'title' => '实例管理',
            'host' => $host,
            'caps' => $caps,
            'prices' => $prices,
            'actions' => HostService::$userActions,
            'user' => $user,
        ]);
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
        // VNC 返回控制台地址
        if ($func === 'vnc' && !empty($ret['data']['url'])) {
            $this->json(['url' => $ret['data']['url']], $ret['msg']);
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
        $ret = OrderService::createRenewOrder((int)$user['id'], (int)$id, $cycle);
        if (!$ret['ok']) {
            flash('error', $ret['msg']);
            redirect('/console/host/' . (int)$id);
        }
        redirect('/pay/' . $ret['bill_no']);
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
