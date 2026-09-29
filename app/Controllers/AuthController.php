<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Captcha;
use App\Core\Controller;
use App\Core\DB;

class AuthController extends Controller
{
    public function showLogin(): string
    {
        if (Auth::check()) {
            redirect('/');
        }
        return $this->view('auth/login', [
            'title' => '登录',
            'next' => $_GET['next'] ?? '/',
            'captcha' => setting('login_captcha', '0') === '1',
        ]);
    }

    public function doLogin(): void
    {
        csrf_check();
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $ip = client_ip();

        // 验证码（开启时）
        if (setting('login_captcha', '0') === '1' && !Captcha::check($_POST['captcha'] ?? '')) {
            flash('error', '验证码错误');
            redirect('/login');
        }

        // 防暴力破解：同一 IP+用户名 5 次失败锁定 15 分钟
        $attempt = DB::get(
            "SELECT * FROM `login_attempts` WHERE `ip` = ? AND `username` = ? LIMIT 1",
            [$ip, $username]
        );
        if ($attempt && (int)$attempt['attempts'] >= 5
            && time() - strtotime($attempt['last_attempt_at']) < 900) {
            flash('error', '尝试次数过多，请 15 分钟后再试');
            redirect('/login');
        }

        $user = DB::get("SELECT * FROM `users` WHERE `username` = ? LIMIT 1", [$username]);
        if (!$user || !password_verify($password, $user['password'])) {
            $this->recordAttempt($ip, $username, $attempt);
            flash('error', '用户名或密码错误');
            redirect('/login');
        }
        if ((int)$user['status'] !== 1) {
            $this->recordAttempt($ip, $username, $attempt);
            flash('error', '账号已被禁用');
            redirect('/login');
        }
        // 邮箱验证开启时，未激活不允许登录
        if (setting('register_email_verify', '0') === '1'
            && empty($user['email_verified_at']) && $user['role'] === 'user') {
            flash('error', '账号尚未激活，请查收邮件完成激活');
            redirect('/login');
        }

        // 登录成功：清除尝试记录
        if ($attempt) {
            DB::delete('login_attempts', '`id` = :id', ['id' => $attempt['id']]);
        }
        Auth::login($user);
        DB::update('users', ['updated_at' => date('Y-m-d H:i:s')], '`id` = :id', ['id' => $user['id']]);
        $next = $_POST['next'] ?? '/';
        if (strpos($next, '/') !== 0) $next = '/';
        redirect($next);
    }

    protected function recordAttempt(string $ip, string $username, $attempt): void
    {
        $now = date('Y-m-d H:i:s');
        if ($attempt) {
            // 超过 15 分钟未尝试则重置计数
            $count = (time() - strtotime($attempt['last_attempt_at']) > 900) ? 1 : ((int)$attempt['attempts'] + 1);
            DB::update('login_attempts', ['attempts' => $count, 'last_attempt_at' => $now], '`id` = :id', ['id' => $attempt['id']]);
        } else {
            DB::insert('login_attempts', ['ip' => $ip, 'username' => $username, 'attempts' => 1, 'last_attempt_at' => $now]);
        }
    }

    public function captcha(): void
    {
        Captcha::output();
    }

    public function showRegister(): string
    {
        if (Auth::check()) {
            redirect('/');
        }
        if (!setting('allow_register', '1')) {
            return $this->view('auth/login', ['title' => '登录', 'error' => '本站暂未开放注册', 'captcha' => false]);
        }
        return $this->view('auth/register', ['title' => '注册']);
    }

    public function doRegister(): void
    {
        csrf_check();
        if (!\App\Core\Captcha::check(trim($_POST['captcha'] ?? ''))) {
            flash('error', '验证码错误，请重新输入');
            redirect('/register');
        }
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $username)) {
            flash('error', '用户名为 4-20 位字母、数字或下划线');
            redirect('/register');
        }
        if (strlen($password) < 6) {
            flash('error', '密码至少 6 位');
            redirect('/register');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', '邮箱格式不正确');
            redirect('/register');
        }
        if (DB::get("SELECT `id` FROM `users` WHERE `username` = ? LIMIT 1", [$username])) {
            flash('error', '用户名已存在');
            redirect('/register');
        }

        $needVerify = setting('register_email_verify', '0') === '1';
        if ($needVerify && $email === '') {
            flash('error', '本站要求验证邮箱，请填写邮箱');
            redirect('/register');
        }
        if ($needVerify && DB::get("SELECT `id` FROM `users` WHERE `email` = ? LIMIT 1", [$email])) {
            flash('error', '该邮箱已被注册');
            redirect('/register');
        }

        $id = DB::insert('users', [
            'username' => $username,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'status' => $needVerify ? 0 : 1,
            'reg_ip' => client_ip(),
        ]);
        $user = DB::get("SELECT * FROM `users` WHERE `id` = ? LIMIT 1", [$id]);

        if ($needVerify) {
            $r = send_activate_mail($user);
            if (!$r['ok']) {
                flash('error', '注册成功，但激活邮件发送失败：' . $r['msg'] . '，请联系客服');
            } else {
                flash('success', '注册成功！激活邮件已发送至 ' . $email . '，请查收并完成激活');
            }
            redirect('/login');
        }

        Auth::login($user);
        redirect('/');
    }

    /** 邮箱激活 */
    public function activate(): void
    {
        $token = trim($_GET['token'] ?? '');
        $row = email_token_consume($token, 'activate');
        if (!$row) {
            flash('error', '激活链接无效或已过期');
            redirect('/login');
        }
        DB::update(
            'users',
            ['status' => 1, 'email_verified_at' => date('Y-m-d H:i:s')],
            '`id` = :id',
            ['id' => $row['user_id']]
        );
        flash('success', '账号激活成功，请登录');
        redirect('/login');
    }

    public function showForgot(): string
    {
        return $this->view('auth/forgot', ['title' => '忘记密码']);
    }

    public function doForgot(): void
    {
        csrf_check();
        if (!\App\Core\Captcha::check(trim($_POST['captcha'] ?? ''))) {
            flash('error', '验证码错误，请重新输入');
            redirect('/forgot');
        }
        $email = trim($_POST['email'] ?? '');
        $done = function () {
            flash('success', '如果该邮箱已注册，密码重置邮件已发送，请查收（1 小时内有效）');
            redirect('/login');
        };
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $done();
        }
        $user = DB::get("SELECT * FROM `users` WHERE `email` = ? LIMIT 1", [$email]);
        if ($user) {
            send_reset_mail($user);
        }
        $done();
    }

    public function showReset(): string
    {
        $token = trim($_GET['token'] ?? '');
        $row = DB::get(
            "SELECT * FROM `email_tokens` WHERE `token` = ? AND `type` = 'reset' AND `used_at` IS NULL LIMIT 1",
            [$token]
        );
        if (!$row || strtotime($row['expires_at']) < time()) {
            flash('error', '重置链接无效或已过期');
            redirect('/forgot');
        }
        return $this->view('auth/reset', ['title' => '重置密码', 'token' => $token]);
    }

    public function doReset(): void
    {
        csrf_check();
        $token = trim($_POST['token'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';
        if (strlen($password) < 6) {
            flash('error', '密码至少 6 位');
            redirect('/reset-password?token=' . urlencode($token));
        }
        if ($password !== $confirm) {
            flash('error', '两次输入的密码不一致');
            redirect('/reset-password?token=' . urlencode($token));
        }
        $row = email_token_consume($token, 'reset');
        if (!$row) {
            flash('error', '重置链接无效或已过期');
            redirect('/forgot');
        }
        DB::update(
            'users',
            ['password' => password_hash($password, PASSWORD_DEFAULT)],
            '`id` = :id',
            ['id' => $row['user_id']]
        );
        flash('success', '密码已重置，请使用新密码登录');
        redirect('/login');
    }

    /** 重发激活邮件 */
    public function resendActivate(): void
    {
        csrf_check();
        $email = trim($_POST['email'] ?? '');
        $user = DB::get("SELECT * FROM `users` WHERE `email` = ? LIMIT 1", [$email]);
        if ($user && empty($user['email_verified_at'])) {
            send_activate_mail($user);
        }
        flash('success', '如果该邮箱尚未激活，激活邮件已重新发送');
        redirect('/login');
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('/');
    }
}
