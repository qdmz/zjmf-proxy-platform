<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;

/** 后台登录 */
class LoginController extends Controller
{
    public function show(): string
    {
        if (Auth::isAdmin()) {
            redirect('/admin');
        }
        return $this->view('admin/login', ['title' => '管理登录'], 'layout_empty');
    }

    public function doLogin(): void
    {
        csrf_check();
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $user = DB::get("SELECT * FROM `users` WHERE `username` = ? AND `role` = 'admin' LIMIT 1", [$username]);
        if (!$user || !password_verify($password, $user['password'])) {
            flash('error', '账号或密码错误');
            redirect('/admin/login');
        }
        if ((int)$user['status'] !== 1) {
            flash('error', '账号已被禁用');
            redirect('/admin/login');
        }
        $_SESSION['admin_id'] = $user['id'];
        Auth::login($user);
        DB::insert('admin_logs', ['admin_id' => $user['id'], 'action' => '后台登录']);
        redirect('/admin');
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('/admin/login');
    }
}
