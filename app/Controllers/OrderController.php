<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Services\OrderService;

class OrderController extends Controller
{
    /** 提交订单（从商品详情页） */
    public function create(): void
    {
        $user = $this->requireLogin();
        csrf_check();
        $productId = (int)($_POST['product_id'] ?? 0);
        $cycle = $_POST['billingcycle'] ?? 'monthly';
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        $configoption = $_POST['configoption'] ?? [];
        if (!is_array($configoption)) {
            $decoded = json_decode((string)$configoption, true);
            $configoption = is_array($decoded) ? $decoded : [];
        }
        $customfield = $_POST['customfield'] ?? [];
        if (!is_array($customfield)) {
            $decoded = json_decode((string)$customfield, true);
            $customfield = is_array($decoded) ? $decoded : [];
        }
        $host = trim($_POST['host'] ?? '');
        $password = $_POST['password'] ?? '';
        $couponCode = trim($_POST['coupon_code'] ?? '');

        if ($host === '' || !preg_match('/^[a-zA-Z0-9-]{3,32}$/', $host)) {
            flash('error', '主机名格式不正确（3-32 位字母/数字/横线）');
            redirect('/shop/' . $productId);
        }

        $result = OrderService::createOrder(
            (int)$user['id'], $productId, $cycle, $qty,
            is_array($configoption) ? $configoption : [],
            is_array($customfield) ? $customfield : [],
            $host, $password, $couponCode
        );
        if (!$result['ok']) {
            flash('error', $result['msg']);
            redirect('/shop/' . $productId);
        }
        redirect('/pay/' . $result['bill_no']);
    }

    public function index(): string
    {
        $user = $this->requireLogin();
        $page = $this->page();
        $per = $this->perPage();
        $total = DB::count("SELECT COUNT(*) FROM `orders` WHERE `user_id` = ?", [(int)$user['id']]);
        $orders = DB::all(
            "SELECT o.*, p.name AS product_name FROM `orders` o
             LEFT JOIN `products` p ON p.id = o.product_id
             WHERE o.`user_id` = ? ORDER BY o.`id` DESC LIMIT ? OFFSET ?",
            [(int)$user['id'], $per, ($page - 1) * $per]
        );
        return $this->view('order/index', [
            'title' => '我的订单',
            'orders' => $orders,
            'pagination' => paginate($total, $page, $per, '/orders'),
            'user' => $user,
        ]);
    }

    public function detail(string $id): string
    {
        $user = $this->requireLogin();
        $order = DB::get(
            "SELECT o.*, p.name AS product_name FROM `orders` o
             LEFT JOIN `products` p ON p.id = o.product_id
             WHERE o.`id` = ? AND o.`user_id` = ? LIMIT 1",
            [(int)$id, (int)$user['id']]
        );
        if (!$order) {
            http_response_code(404);
            return $this->view('errors/404', ['title' => '订单不存在']);
        }
        $bill = DB::get("SELECT * FROM `bills` WHERE `order_id` = ? LIMIT 1", [(int)$order['id']]);
        return $this->view('order/detail', [
            'title' => '订单详情',
            'order' => $order,
            'bill' => $bill,
            'snapshot' => json_decode($order['config_snapshot'] ?? '{}', true) ?: [],
            'user' => $user,
        ]);
    }
}
