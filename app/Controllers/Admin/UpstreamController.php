<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\DB;
use App\Services\UpstreamService;

class UpstreamController extends Controller
{
    public function index(): string
    {
        $admin = $this->requireAdmin();
        $providers = DB::all("SELECT up.*,
            (SELECT COUNT(*) FROM `products` WHERE `provider_id` = up.id) AS product_count
            FROM `upstream_providers` up ORDER BY up.`id` DESC");
        return $this->view('admin/upstream_index', [
            'title' => '上游供货商', 'providers' => $providers, 'user' => $admin,
        ], 'layout_admin');
    }

    public function create(): string
    {
        $admin = $this->requireAdmin();
        return $this->view('admin/upstream_form', [
            'title' => '添加上游供货商', 'provider' => null, 'user' => $admin,
        ], 'layout_admin');
    }

    public function edit(string $id): string
    {
        $admin = $this->requireAdmin();
        $provider = UpstreamService::get((int)$id);
        if (!$provider) {
            http_response_code(404);
            return $this->view('errors/404', ['title' => '不存在'], 'layout_admin');
        }
        return $this->view('admin/upstream_form', [
            'title' => '编辑上游供货商', 'provider' => $provider, 'user' => $admin,
        ], 'layout_admin');
    }

    public function store(): void
    {
        $this->requireAdmin();
        csrf_check();
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'base_url' => rtrim(trim($_POST['base_url'] ?? ''), '/'),
            'account' => trim($_POST['account'] ?? ''),
            'status' => (int)($_POST['status'] ?? 1),
            'checkout_payment' => trim($_POST['checkout_payment'] ?? '') !== '' ? trim($_POST['checkout_payment']) : 'credit',
            'remark' => trim($_POST['remark'] ?? ''),
        ];
        $password = $_POST['password'] ?? '';
        if ($data['name'] === '' || $data['base_url'] === '' || $data['account'] === '') {
            flash('error', '名称、API 地址、账号不能为空');
            redirect($id ? '/admin/upstream/' . $id . '/edit' : '/admin/upstream/create');
        }
        if ($password !== '') {
            $data['password_enc'] = enc_data($password);
            $data['jwt'] = null;
            $data['jwt_expire'] = 0;
        } elseif ($id === 0) {
            flash('error', '请填写上游 API 密码');
            redirect('/admin/upstream/create');
        }
        if ($id > 0) {
            DB::update('upstream_providers', $data, '`id` = :id', ['id' => $id]);
            $this->adminLog("编辑上游供货商 #{$id} {$data['name']}");
        } else {
            $id = (int) DB::insert('upstream_providers', $data);
            $this->adminLog("添加上游供货商 #{$id} {$data['name']}");
        }
        flash('success', '保存成功');
        redirect('/admin/upstream');
    }

    public function delete(string $id): void
    {
        $this->requireAdmin();
        csrf_check();
        $hasProducts = DB::count("SELECT COUNT(*) FROM `products` WHERE `provider_id` = ?", [(int)$id]);
        if ($hasProducts > 0) {
            flash('error', '该供货商下还有产品，请先删除或转移产品');
            redirect('/admin/upstream');
        }
        DB::delete('upstream_providers', '`id` = ?', [(int)$id]);
        $this->adminLog("删除上游供货商 #{$id}");
        flash('success', '已删除');
        redirect('/admin/upstream');
    }

    /** 测试连接（AJAX） */
    public function test(string $id): void
    {
        $this->requireAdmin();
        set_time_limit(60);
        $provider = UpstreamService::get((int)$id);
        if (!$provider) {
            $this->fail('供货商不存在');
        }
        $ret = UpstreamService::testConnection($provider);
        $ret['ok'] ? $this->json(null, $ret['msg']) : $this->fail($ret['msg']);
    }

    /** 同步产品（AJAX，可能耗时较长） */
    public function sync(string $id): void
    {
        $this->requireAdmin();
        set_time_limit(600);
        $ret = UpstreamService::syncProducts((int)$id);
        $this->adminLog("同步上游产品 #{$id}: " . ($ret['msg'] ?? ''));
        $ret['ok'] ? $this->json(['count' => $ret['count'] ?? 0], $ret['msg']) : $this->fail($ret['msg']);
    }

    /** 上游操作日志 */
    public function logs(): string
    {
        $admin = $this->requireAdmin();
        $page = $this->page();
        $per = $this->perPage();
        $total = DB::count("SELECT COUNT(*) FROM `upstream_logs`");
        $logs = DB::all(
            "SELECT l.*, up.name AS provider_name FROM `upstream_logs` l
             LEFT JOIN `upstream_providers` up ON up.id = l.provider_id
             ORDER BY l.`id` DESC LIMIT ? OFFSET ?",
            [$per, ($page - 1) * $per]
        );
        return $this->view('admin/upstream_logs', [
            'title' => '上游接口日志', 'logs' => $logs,
            'pagination' => paginate($total, $page, $per, '/admin/upstream/logs'),
            'user' => $admin,
        ], 'layout_admin');
    }

    public function logDetail(string $id): string
    {
        $admin = $this->requireAdmin();
        $log = DB::get(
            "SELECT l.*, up.name AS provider_name FROM `upstream_logs` l
             LEFT JOIN `upstream_providers` up ON up.id = l.provider_id
             WHERE l.`id` = ?",
            [(int)$id]
        );
        if (!$log) {
            $this->fail('日志不存在');
        }
        return $this->view('admin/upstream_log_detail', [
            'title' => '上游接口日志详情 #' . $id, 'log' => $log,
            'user' => $admin,
        ], 'layout_admin');
    }
}
