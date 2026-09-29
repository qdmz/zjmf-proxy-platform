<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\DB;
use App\Services\PaymentService;

class UserController extends Controller
{
    public function index(): string
    {
        $admin = $this->requireAdmin();
        $kw = trim($_GET['kw'] ?? '');
        $role = $_GET['role'] ?? 'user';
        if (!in_array($role, ['user', 'admin'], true)) {
            $role = 'user';
        }
        $where = "`role` = ?";
        $params = [$role];
        if ($kw !== '') { $where .= ' AND (username LIKE ? OR email LIKE ?)'; $params[] = "%{$kw}%"; $params[] = "%{$kw}%"; }

        $page = $this->page();
        $per = $this->perPage();
        $total = DB::count("SELECT COUNT(*) FROM `users` WHERE {$where}", $params);
        $users = DB::all(
            "SELECT * FROM `users` WHERE {$where} ORDER BY `id` DESC LIMIT ? OFFSET ?",
            array_merge($params, [$per, ($page - 1) * $per])
        );
        return $this->view('admin/user_index', [
            'title' => '用户管理', 'users' => $users,
            'filter' => ['kw' => $kw, 'role' => $role],
            'pagination' => paginate($total, $page, $per, '/admin/users?' . http_build_query(array_filter(['kw' => $kw, 'role' => $role]))),
            'user' => $admin,
        ], 'layout_admin');
    }

    public function detail(string $id): string
    {
        $admin = $this->requireAdmin();
        $u = DB::get("SELECT * FROM `users` WHERE `id` = ? LIMIT 1", [(int)$id]);
        if (!$u) {
            http_response_code(404);
            return $this->view('errors/404', ['title' => '用户不存在'], 'layout_admin');
        }
        $stats = [
            'hosts' => DB::count("SELECT COUNT(*) FROM `hosts` WHERE `user_id` = ?", [(int)$id]),
            'orders' => DB::count("SELECT COUNT(*) FROM `orders` WHERE `user_id` = ?", [(int)$id]),
            'income' => (float)(DB::get("SELECT COALESCE(SUM(amount),0) AS s FROM `bills` WHERE `user_id` = ? AND `status`='paid' AND `type`!='recharge'", [(int)$id])['s'] ?? 0),
        ];
        $txs = DB::all("SELECT * FROM `transactions` WHERE `user_id` = ? ORDER BY `id` DESC LIMIT 10", [(int)$id]);
        return $this->view('admin/user_detail', [
            'title' => '用户详情', 'u' => $u, 'stats' => $stats, 'txs' => $txs, 'user' => $admin,
        ], 'layout_admin');
    }

    public function toggle(string $id): void
    {
        $this->requireAdmin();
        csrf_check();
        $u = DB::get("SELECT * FROM `users` WHERE `id` = ? LIMIT 1", [(int)$id]);
        if ($u && $u['role'] === 'user') {
            $new = (int)$u['status'] ? 0 : 1;
            DB::update('users', ['status' => $new], '`id` = :id', ['id' => (int)$id]);
            $this->adminLog(($new ? '启用' : '禁用') . "用户 #{$id} {$u['username']}");
        }
        redirect('/admin/users');
    }

    /** 手动调整余额 */
    public function adjust(string $id): void
    {
        $this->requireAdmin();
        csrf_check();
        $u = DB::get("SELECT * FROM `users` WHERE `id` = ? LIMIT 1", [(int)$id]);
        if (!$u) {
            redirect('/admin/users');
        }
        $amount = round((float)($_POST['amount'] ?? 0), 2);
        $remark = trim($_POST['remark'] ?? '管理员手动调整');
        if ($amount == 0) {
            flash('error', '调整金额不能为 0');
            redirect('/admin/users/' . (int)$id);
        }
        try {
            $new = PaymentService::addBalance((int)$id, $amount, 'adjust', $remark);
            $this->adminLog("调整用户 #{$id} {$u['username']} 余额 " . ($amount > 0 ? '+' : '') . $amount . "，当前余额 {$new}");
            flash('success', '调整成功，当前余额：' . $new);
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/admin/users/' . (int)$id);
    }

    public function create(): string
    {
        $admin = $this->requireAdmin();
        return $this->view('admin/admin_create', ['title' => '添加管理员', 'user' => $admin], 'layout_admin');
    }

    public function storeAdmin(): void
    {
        $this->requireAdmin();
        csrf_check();
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $username) || strlen($password) < 6) {
            flash('error', '用户名或密码格式不正确');
            redirect('/admin/users/admin/create');
        }
        if (DB::get("SELECT `id` FROM `users` WHERE `username` = ? LIMIT 1", [$username])) {
            flash('error', '用户名已存在');
            redirect('/admin/users/admin/create');
        }
        $id = DB::insert('users', [
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'admin',
        ]);
        $this->adminLog("添加管理员 #{$id} {$username}");
        flash('success', '管理员已添加');
        redirect('/admin/users?role=admin&kw=' . urlencode($username));
    }
}
