<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\DB;
use App\Services\HostService;

class HostAdminController extends Controller
{
    public function index(): string
    {
        $admin = $this->requireAdmin();
        $status = $_GET['status'] ?? '';
        $kw = trim($_GET['kw'] ?? '');
        $where = '1=1';
        $params = [];
        if ($status !== '') { $where .= ' AND h.status = ?'; $params[] = $status; }
        if ($kw !== '') { $where .= ' AND (h.domain LIKE ? OR u.username LIKE ?)'; $params[] = "%{$kw}%"; $params[] = "%{$kw}%"; }

        $page = $this->page();
        $per = $this->perPage();
        $total = DB::count(
            "SELECT COUNT(*) FROM `hosts` h LEFT JOIN `users` u ON u.id = h.user_id WHERE {$where}", $params
        );
        $hosts = DB::all(
            "SELECT h.*, u.username, p.name AS product_name FROM `hosts` h
             LEFT JOIN `users` u ON u.id = h.user_id
             LEFT JOIN `products` p ON p.id = h.product_id
             WHERE {$where} ORDER BY h.`id` DESC LIMIT ? OFFSET ?",
            array_merge($params, [$per, ($page - 1) * $per])
        );
        return $this->view('admin/host_index', [
            'title' => '实例管理', 'hosts' => $hosts,
            'filter' => ['status' => $status, 'kw' => $kw],
            'pagination' => paginate($total, $page, $per, '/admin/hosts?' . http_build_query(array_filter(['status' => $status, 'kw' => $kw]))),
            'user' => $admin,
        ], 'layout_admin');
    }

    public function detail(string $id): string
    {
        $admin = $this->requireAdmin();
        $host = DB::get(
            "SELECT h.*, u.username, p.name AS product_name, up.name AS provider_name FROM `hosts` h
             LEFT JOIN `users` u ON u.id = h.user_id
             LEFT JOIN `products` p ON p.id = h.product_id
             LEFT JOIN `upstream_providers` up ON up.id = h.provider_id
             WHERE h.`id` = ? LIMIT 1", [(int)$id]
        );
        if (!$host) {
            http_response_code(404);
            return $this->view('errors/404', ['title' => '实例不存在'], 'layout_admin');
        }
        $host['password'] = $host['password_enc'] ? dec_data($host['password_enc']) : '';
        return $this->view('admin/host_detail', [
            'title' => '实例详情', 'host' => $host, 'user' => $admin,
        ], 'layout_admin');
    }

    public function sync(string $id): void
    {
        $this->requireAdmin();
        set_time_limit(120);
        $ret = HostService::syncFromUpstream((int)$id);
        $this->adminLog("同步实例 #{$id}: " . ($ret['msg'] ?? ''));
        flash($ret['ok'] ? 'success' : 'error', $ret['msg'] ?? '');
        redirect('/admin/hosts/' . (int)$id);
    }

    /** 同步某供货商全部实例状态 */
    public function syncProvider(string $providerId): void
    {
        $this->requireAdmin();
        set_time_limit(600);
        $ret = HostService::syncAllByProvider((int)$providerId);
        $this->adminLog("同步供货商 #{$providerId} 实例: " . ($ret['msg'] ?? ''));
        flash($ret['ok'] ? 'success' : 'error', $ret['msg'] ?? '');
        redirect('/admin/hosts');
    }
}
