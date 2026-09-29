<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;

/** 个人资料：修改邮箱 / 手机 / 密码 */
class ProfileController extends Controller
{
    /** 个人资料页 */
    public function index(): string
    {
        $user = $this->requireLogin();
        $user = DB::get("SELECT * FROM `users` WHERE `id` = ? LIMIT 1", [(int)$user['id']]);
        return $this->view('console/profile', ['title' => '个人资料', 'user' => $user]);
    }

    /** 保存个人资料 */
    public function update(): void
    {
        $user = $this->requireLogin();
        csrf_check();
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($phone !== '' && !preg_match('/^[0-9+\-\s]{5,20}$/', $phone)) {
            flash('error', '手机号格式不正确');
            redirect('/console/profile');
        }

        $updates = ['phone' => $phone === '' ? null : $phone];
        $emailChanged = false;
        $oldEmail = (string)($user['email'] ?? '');
        if ($email === '') {
            flash('error', '请填写邮箱地址');
            redirect('/console/profile');
        }
        if ($email !== $oldEmail) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', '邮箱格式不正确');
                redirect('/console/profile');
            }
            if (DB::get(
                "SELECT `id` FROM `users` WHERE `email` = ? AND `id` != ? LIMIT 1",
                [$email, (int)$user['id']]
            )) {
                flash('error', '该邮箱已被其他账号使用');
                redirect('/console/profile');
            }
            // 开启邮箱验证时，换绑需点击新邮箱中的确认链接才生效
            if (setting('register_email_verify', '0') === '1') {
                $r = send_change_email_mail($user, $email);
                if (!$r['ok']) {
                    flash('error', '验证邮件发送失败：' . $r['msg']);
                    redirect('/console/profile');
                }
                $emailChanged = true;
            } else {
                $updates['email'] = $email;
            }
        }

        DB::update('users', $updates, '`id` = :id', ['id' => (int)$user['id']]);
        if ($emailChanged) {
            flash('success', '验证邮件已发送至新邮箱 ' . $email . '，请点击邮件中的确认链接完成换绑（24 小时内有效）');
        } else {
            flash('success', '个人资料已更新');
        }
        redirect('/console/profile');
    }

    /** 修改密码页 */
    public function password(): string
    {
        $user = $this->requireLogin();
        return $this->view('console/password', ['title' => '修改密码', 'user' => $user]);
    }

    /** 保存新密码 */
    public function updatePassword(): void
    {
        $user = $this->requireLogin();
        csrf_check();
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $row = DB::get("SELECT * FROM `users` WHERE `id` = ? LIMIT 1", [(int)$user['id']]);
        if (!password_verify($old, $row['password'])) {
            flash('error', '当前密码不正确');
            redirect('/console/password');
        }
        if (strlen($new) < 6) {
            flash('error', '新密码至少 6 位');
            redirect('/console/password');
        }
        if ($new !== $confirm) {
            flash('error', '两次输入的新密码不一致');
            redirect('/console/password');
        }
        DB::update(
            'users',
            ['password' => password_hash($new, PASSWORD_DEFAULT)],
            '`id` = :id',
            ['id' => (int)$user['id']]
        );
        flash('success', '密码修改成功，请重新登录');
        redirect('/logout');
    }

    /** 换绑邮箱确认（邮件链接） */
    public function verifyEmail(): void
    {
        $token = trim($_GET['token'] ?? '');
        $row = email_token_consume($token, 'change_email');
        if (!$row || empty($row['data'])) {
            flash('error', '链接无效或已过期');
            redirect('/console/profile');
        }
        $newEmail = $row['data'];
        if (DB::get(
            "SELECT `id` FROM `users` WHERE `email` = ? AND `id` != ? LIMIT 1",
            [$newEmail, (int)$row['user_id']]
        )) {
            flash('error', '该邮箱已被其他账号使用，换绑失败');
            redirect('/console/profile');
        }
        DB::update(
            'users',
            ['email' => $newEmail, 'email_verified_at' => date('Y-m-d H:i:s')],
            '`id` = :id',
            ['id' => (int)$row['user_id']]
        );
        flash('success', '邮箱已更换为 ' . $newEmail);
        redirect('/console/profile');
    }
}
