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

    /** 取消订单（仅待支付） */
    public function cancel(string $id): void
    {
        $user = $this->requireLogin();
        csrf_check();
        $order = DB::get(
            "SELECT * FROM `orders` WHERE `id` = ? AND `user_id` = ? LIMIT 1",
            [(int)$id, (int)$user['id']]
        );
        if (!$order) {
            flash('error', '订单不存在');
            redirect('/orders');
        }
        if ($order['status'] !== 'pending') {
            flash('error', '该订单当前状态不可取消');
            redirect('/orders/' . (int)$id);
        }
        DB::beginTransaction();
        try {
            DB::update('orders', ['status' => 'cancelled'], '`id` = :id', ['id' => (int)$id]);
            // 删除关联的未支付账单
            DB::delete('bills', '`order_id` = :oid AND `status` = \'unpaid\'', ['oid' => (int)$id]);
            DB::commit();
            flash('success', '订单已取消');
        } catch (\Throwable $e) {
            DB::rollBack();
            flash('error', '取消失败，请稍后重试');
        }
        redirect('/orders');
    }

    /** 删除订单（仅已取消/开通失败） */
    public function destroy(string $id): void
    {
        $user = $this->requireLogin();
        csrf_check();
        $order = DB::get(
            "SELECT * FROM `orders` WHERE `id` = ? AND `user_id` = ? LIMIT 1",
            [(int)$id, (int)$user['id']]
        );
        if (!$order) {
            flash('error', '订单不存在');
            redirect('/orders');
        }
        if (!in_array($order['status'], ['cancelled', 'failed'], true)) {
            flash('error', '该订单当前状态不可删除');
            redirect('/orders/' . (int)$id);
        }
        DB::beginTransaction();
        try {
            DB::delete('bills', '`order_id` = :oid', ['oid' => (int)$id]);
            DB::delete('orders', '`id` = :id', ['id' => (int)$id]);
            DB::commit();
            flash('success', '订单已删除');
        } catch (\Throwable $e) {
            DB::rollBack();
            flash('error', '删除失败，请稍后重试');
        }
        redirect('/orders');
    }
}
