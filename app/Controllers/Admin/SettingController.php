<?php
namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Mailer;

class SettingController extends Controller
{
    protected array $groups = [
        'site' => '站点设置',
        'mail' => '邮件设置',
        'payment' => '支付设置',
        'cron' => '提醒设置',
    ];

    /** 各分组的字段定义：key => [label, type] */
    protected array $fields = [
        'site' => [
            'site_name' => ['站点名称', 'text'],
            'site_url' => ['站点网址（用于支付回调）', 'text'],
            'site_icp' => ['备案号', 'text'],
            'currency' => ['货币单位', 'text'],
            'allow_register' => ['开放注册', 'bool'],
            'register_email_verify' => ['注册需邮箱激活', 'bool'],
            'login_captcha' => ['登录验证码（防暴力破解）', 'bool'],
        ],
        'mail' => [
            'mail_enabled' => ['启用邮件功能', 'bool'],
            'smtp_host' => ['SMTP 主机（如 smtp.qq.com）', 'text'],
            'smtp_port' => ['SMTP 端口（tls:587 / ssl:465）', 'text'],
            'smtp_enc' => ['加密方式（tls / ssl / none）', 'text'],
            'smtp_user' => ['SMTP 用户名', 'text'],
            'smtp_pass' => ['SMTP 密码/授权码', 'password_enc'],
            'smtp_from' => ['发件人邮箱', 'text'],
            'smtp_from_name' => ['发件人名称', 'text'],
        ],
        'payment' => [
            'epay_url' => ['易支付网关地址（如 https://pay.xxx.com）', 'text'],
            'epay_pid' => ['易支付商户 PID', 'text'],
            'epay_key' => ['易支付商户 Key', 'password'],
            'epay_types' => ['启用的支付方式（逗号分隔：alipay,wxpay,qqpay）', 'text'],
        ],
        'cron' => [
            'renew_remind_days' => ['到期提醒天数（逗号分隔，如 7,3,1）', 'text'],
        ],
    ];

    public function index(): string
    {
        $admin = $this->requireAdmin();
        $group = $_GET['group'] ?? 'site';
        if (!isset($this->groups[$group])) $group = 'site';
        $rows = DB::all("SELECT `k`,`v` FROM `settings` WHERE `group` = ?", [$group]);
        $settings = [];
        foreach ($rows as $r) {
            $settings[$r['k']] = $r['v'];
        }
        return $this->view('admin/setting', [
            'title' => '系统设置', 'groups' => $this->groups, 'group' => $group,
            'fields' => $this->fields[$group], 'settings' => $settings, 'user' => $admin,
        ], 'layout_admin');
    }

    public function save(): void
    {
        $this->requireAdmin();
        csrf_check();
        $group = $_POST['group'] ?? 'site';
        if (!isset($this->groups[$group])) {
            redirect('/admin/settings');
        }
        $saved = 0;
        foreach (array_keys($this->fields[$group]) as $key) {
            $type = $this->fields[$group][$key][1];
            if ($type === 'bool') {
                $val = isset($_POST[$key]) ? '1' : '0';
            } elseif ($type === 'password' || $type === 'password_enc') {
                // 密码型：留空表示不修改
                $val = trim($_POST[$key] ?? '');
                if ($val === '') {
                    continue;
                }
                if ($type === 'password_enc') {
                    $val = \App\Core\Crypto::encrypt($val);
                }
            } else {
                if (!array_key_exists($key, $_POST)) {
                    continue;
                }
                $val = trim($_POST[$key]);
            }
            $n = DB::update('settings', ['v' => $val, 'group' => $group], '`k` = :k', ['k' => $key]);
            if ($n === 0 && !DB::get("SELECT `k` FROM `settings` WHERE `k` = ? LIMIT 1", [$key])) {
                DB::insert('settings', ['k' => $key, 'v' => $val, 'group' => $group]);
            }
            $saved++;
        }
        $this->adminLog("修改系统设置（{$this->groups[$group]}），更新 {$saved} 项");
        flash('success', '设置已保存');
        redirect('/admin/settings?group=' . $group);
    }

    /** 发送测试邮件 */
    public function testMail(): void
    {
        $this->requireAdmin();
        csrf_check();
        $to = trim($_POST['test_email'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('error', '请填写正确的测试收件邮箱');
            redirect('/admin/settings?group=mail');
        }
        $site = setting('site_name', '本站');
        $r = Mailer::quick($to, "【{$site}】SMTP 测试邮件", "<p>这是一封测试邮件，说明 SMTP 配置正确。</p><p>发送时间：" . date('Y-m-d H:i:s') . "</p>");
        if ($r['ok']) {
            $this->adminLog("发送 SMTP 测试邮件至 {$to} 成功");
            flash('success', '测试邮件已发送，请查收');
        } else {
            flash('error', '发送失败：' . $r['msg']);
        }
        redirect('/admin/settings?group=mail');
    }
}
