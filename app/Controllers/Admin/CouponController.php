<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\DB;

/** 优惠券管理 */
class CouponController extends Controller
{
    public function index(): string
    {
        $admin = $this->requireAdmin();
        $list = DB::all("SELECT * FROM `coupons` ORDER BY `id` DESC");
        return $this->view('admin/coupon_index', [
            'title' => '优惠券管理', 'list' => $list, 'user' => $admin,
        ], 'layout_admin');
    }

    public function create(): string
    {
        $admin = $this->requireAdmin();
        return $this->view('admin/coupon_form', ['title' => '创建优惠券', 'c' => null, 'user' => $admin], 'layout_admin');
    }

    protected function readForm(): array
    {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        if ($code === '') $code = 'CPN' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        return [
            'code' => $code,
            'name' => trim($_POST['name'] ?? ''),
            'type' => ($_POST['type'] ?? 'fixed') === 'percent' ? 'percent' : 'fixed',
            'value' => max(0, (float)($_POST['value'] ?? 0)),
            'min_amount' => max(0, (float)($_POST['min_amount'] ?? 0)),
            'max_uses' => max(0, (int)($_POST['max_uses'] ?? 0)),
            'per_user_limit' => max(1, (int)($_POST['per_user_limit'] ?? 1)),
            'starts_at' => trim($_POST['starts_at'] ?? '') ?: null,
            'ends_at' => trim($_POST['ends_at'] ?? '') ?: null,
            'status' => isset($_POST['status']) ? 1 : 0,
        ];
    }

    public function store(): void
    {
        $this->requireAdmin();
        csrf_check();
        $data = $this->readForm();
        if ($data['name'] === '' || $data['value'] <= 0) {
            flash('error', '名称和面值不能为空');
            redirect('/admin/coupons/create');
        }
        if (DB::get("SELECT `id` FROM `coupons` WHERE `code` = ? LIMIT 1", [$data['code']])) {
            flash('error', '券码已存在');
            redirect('/admin/coupons/create');
        }
        DB::insert('coupons', $data);
        $this->adminLog("创建优惠券 {$data['code']}（{$data['name']}）");
        flash('success', '优惠券已创建，券码：' . $data['code']);
        redirect('/admin/coupons');
    }

    public function edit(string $id): string
    {
        $admin = $this->requireAdmin();
        $c = DB::get("SELECT * FROM `coupons` WHERE `id` = ? LIMIT 1", [(int)$id]);
        if (!$c) {
            flash('error', '不存在');
            redirect('/admin/coupons');
        }
        return $this->view('admin/coupon_form', ['title' => '编辑优惠券', 'c' => $c, 'user' => $admin], 'layout_admin');
    }

    public function update(string $id): void
    {
        $this->requireAdmin();
        csrf_check();
        $data = $this->readForm();
        $old = DB::get("SELECT * FROM `coupons` WHERE `id` = ? LIMIT 1", [(int)$id]);
        if (!$old) {
            flash('error', '不存在');
            redirect('/admin/coupons');
        }
        // 券码不允许修改（避免混淆）
        unset($data['code']);
        DB::update('coupons', $data, '`id` = :id', ['id' => (int)$id]);
        $this->adminLog("编辑优惠券 #{$id}（{$old['code']}）");
        flash('success', '已保存');
        redirect('/admin/coupons');
    }

    public function delete(string $id): void
    {
        $this->requireAdmin();
        csrf_check();
        DB::delete('coupons', '`id` = :id', ['id' => (int)$id]);
        $this->adminLog("删除优惠券 #{$id}");
        flash('success', '已删除');
        redirect('/admin/coupons');
    }

    public function usages(string $id): string
    {
        $admin = $this->requireAdmin();
        $c = DB::get("SELECT * FROM `coupons` WHERE `id` = ? LIMIT 1", [(int)$id]);
        if (!$c) {
            flash('error', '不存在');
            redirect('/admin/coupons');
        }
        $list = DB::all(
            "SELECT cu.*, u.username, o.order_no FROM `coupon_usages` cu
             LEFT JOIN `users` u ON u.id = cu.user_id
             LEFT JOIN `orders` o ON o.id = cu.order_id
             WHERE cu.`coupon_id` = ? ORDER BY cu.`id` DESC",
            [(int)$id]
        );
        return $this->view('admin/coupon_usages', [
            'title' => '使用记录 - ' . $c['code'], 'c' => $c, 'list' => $list, 'user' => $admin,
        ], 'layout_admin');
    }
}
