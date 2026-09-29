<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\DB;
use App\Services\OrderService;

class OrderAdminController extends Controller
{
    public function index(): string
    {
        $admin = $this->requireAdmin();
        $status = $_GET['status'] ?? '';
        $kw = trim($_GET['kw'] ?? '');
        $where = '1=1';
        $params = [];
        if ($status !== '') { $where .= ' AND o.status = ?'; $params[] = $status; }
        if ($kw !== '') { $where .= ' AND (o.order_no LIKE ? OR u.username LIKE ?)'; $params[] = "%{$kw}%"; $params[] = "%{$kw}%"; }

        $page = $this->page();
        $per = $this->perPage();
        $total = DB::count(
            "SELECT COUNT(*) FROM `orders` o LEFT JOIN `users` u ON u.id = o.user_id WHERE {$where}", $params
        );
        $orders = DB::all(
            "SELECT o.*, u.username, p.name AS product_name FROM `orders` o
             LEFT JOIN `users` u ON u.id = o.user_id
             LEFT JOIN `products` p ON p.id = o.product_id
             WHERE {$where} ORDER BY o.`id` DESC LIMIT ? OFFSET ?",
            array_merge($params, [$per, ($page - 1) * $per])
        );
        return $this->view('admin/order_index', [
            'title' => '订单管理', 'orders' => $orders,
            'filter' => ['status' => $status, 'kw' => $kw],
            'pagination' => paginate($total, $page, $per, '/admin/orders?' . http_build_query(array_filter(['status' => $status, 'kw' => $kw]))),
            'user' => $admin,
        ], 'layout_admin');
    }

    public function detail(string $id): string
    {
        $admin = $this->requireAdmin();
        $order = DB::get(
            "SELECT o.*, u.username, p.name AS product_name FROM `orders` o
             LEFT JOIN `users` u ON u.id = o.user_id
             LEFT JOIN `products` p ON p.id = o.product_id
             WHERE o.`id` = ? LIMIT 1", [(int)$id]
        );
        if (!$order) {
            http_response_code(404);
            return $this->view('errors/404', ['title' => '订单不存在'], 'layout_admin');
        }
        $bill = DB::get("SELECT * FROM `bills` WHERE `order_id` = ? LIMIT 1", [(int)$id]);
        $host = $order['host_id'] ? DB::get("SELECT * FROM `hosts` WHERE `id` = ? LIMIT 1", [(int)$order['host_id']]) : null;
        return $this->view('admin/order_detail', [
            'title' => '订单详情', 'order' => $order, 'bill' => $bill, 'host' => $host,
            'snapshot' => json_decode($order['config_snapshot'] ?? '{}', true) ?: [],
            'user' => $admin,
        ], 'layout_admin');
    }

    /** 重试开通 */
    public function retry(string $id): void
    {
        $this->requireAdmin();
        csrf_check();
        set_time_limit(300);
        $ret = OrderService::retryProvision((int)$id);
        $this->adminLog("重试开通订单 #{$id}: " . ($ret['msg'] ?? ''));
        flash($ret['ok'] ? 'success' : 'error', $ret['msg'] ?? '');
        redirect('/admin/orders/' . (int)$id);
    }

    /** 标记失败/人工处理 */
    public function markFailed(string $id): void
    {
        $this->requireAdmin();
        csrf_check();
        DB::update('orders', ['status' => 'failed', 'fail_reason' => '管理员标记为人工处理'], '`id` = :id', ['id' => (int)$id]);
        $this->adminLog("标记订单 #{$id} 为人工处理");
        redirect('/admin/orders/' . (int)$id);
    }
}
