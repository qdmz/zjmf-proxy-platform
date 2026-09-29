<?php
// 全局辅助函数

use App\Core\Config;
use App\Core\Crypto;
use App\Core\DB;
use App\Core\View;

function config(string $key, $default = null) {
    return Config::get($key, $default);
}

/** 读取系统设置（settings 表），带静态缓存 */
function setting(string $key, $default = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (DB::all("SELECT `k`,`v` FROM `settings`") as $row) {
                $cache[$row['k']] = $row['v'];
            }
        } catch (\Throwable $e) { /* 安装前无表 */ }
    }
    return $cache[$key] ?? $default;
}

function e($s): string {
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * 库存状态：['in'=>是否可购买, 'label'=>显示文字, 'badge'=>badge样式类]
 */
function stock_status($p): array {
    $control = !empty($p['stock_control']);
    $qty = (int)($p['stock_qty'] ?? 0);
    if (!$control) {
        return ['in' => true, 'label' => '有货', 'badge' => 'badge-active'];
    }
    if ($qty > 0) {
        return ['in' => true, 'label' => '库存 ' . $qty . ' 件', 'badge' => 'badge-unpaid'];
    }
    return ['in' => false, 'label' => '缺货', 'badge' => 'badge-failed'];
}
function clean_product_html($html): string {
    $html = html_entity_decode((string)($html ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return strip_tags($html, '<br><p><li><ul><ol><span><div><b><strong><i><em><u><font><table><tr><td><th><tbody><thead><h1><h2><h3><h4><h5><img><a><blockquote><hr>');
}

/** 产品描述纯文本摘要：先解码 HTML 实体再去标签（库中多为转义存储，直接 strip_tags 剥不掉） */
function product_text_summary($html, int $len = 80): string {
    $text = html_entity_decode((string)($html ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = strip_tags($text);
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    return mb_substr($text, 0, $len, 'UTF-8');
}

function url(string $path = ''): string {
    return $path === '' || $path[0] === '/' ? $path : '/' . $path;
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function json_ok($data = null, string $msg = 'ok'): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => 0, 'message' => $msg, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_fail(string $msg = '操作失败', int $code = 1, $data = null): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => $code, 'message' => $msg, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void {
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$token)) {
        if (is_ajax()) {
            json_fail('表单已过期，请刷新重试', 419);
        }
        die('表单验证失败，请刷新重试');
    }
}

function is_ajax(): bool {
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

/** 订单状态中文 */
function order_status_name(string $s): string
{
    static $map = [
        'pending' => '待支付', 'paid' => '已支付待开通', 'active' => '已开通',
        'failed' => '开通失败', 'cancelled' => '已取消',
    ];
    return $map[$s] ?? $s;
}

/** 账单状态中文 */
function bill_status_name(string $s): string
{
    static $map = [
        'unpaid' => '未支付', 'paid' => '已支付', 'refunded' => '已退款',
    ];
    return $map[$s] ?? $s;
}

/** 账单类型中文 */
function bill_type_name(string $s): string
{
    static $map = [
        'order' => '新购', 'renew' => '续费', 'upgrade' => '升级', 'recharge' => '充值',
    ];
    return $map[$s] ?? $s;
}

/** 资金流水类型中文 */
function tx_type_name(string $t): string
{
    static $map = [
        'recharge' => '充值', 'pay' => '消费', 'refund' => '退款', 'adjust' => '管理员调整',
    ];
    return $map[$t] ?? $t;
}

/** 工单状态中文 */
function ticket_status_name(string $s): string
{
    static $map = [
        'open' => '待回复', 'replied' => '已回复', 'closed' => '已关闭',
    ];
    return $map[$s] ?? $s;
}

function flash(string $key, $value = null) {
    if ($value === null) {
        $v = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $v;
    }
    $_SESSION['flash'][$key] = $value;
}

function money($n): string {
    return number_format((float)$n, 2);
}

function client_ip(): string {
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = explode(',', $_SERVER[$k])[0];
            return trim($ip);
        }
    }
    return '0.0.0.0';
}

/** 生成订单/账单号 */
function gen_no(string $prefix): string {
    return $prefix . date('YmdHis') . substr(str_shuffle('0123456789'), 0, 4);
}

/** 周期中文名 */
function cycle_name(string $cycle): string {
    static $map = [
        'free' => '免费', 'onetime' => '一次性', 'ontrial' => '试用',
        'hour' => '小时', 'day' => '天',
        'monthly' => '月付', 'quarterly' => '季付', 'semiannually' => '半年付',
        'annually' => '年付', 'biennially' => '两年付', 'triennially' => '三年付',
    ];
    return $map[$cycle] ?? $cycle;
}

/** 周期换算天数（用于到期时间计算） */
function cycle_days(string $cycle): int {
    static $map = [
        'hour' => 0, 'day' => 1, 'monthly' => 30, 'quarterly' => 90,
        'semiannually' => 180, 'annually' => 365, 'biennially' => 730, 'triennially' => 1095,
    ];
    return $map[$cycle] ?? 30;
}

/** 主机状态中文 */
function host_status_name(string $status): string {
    static $map = [
        'pending' => '开通中', 'active' => '运行中', 'suspended' => '已暂停',
        'cancelled' => '已取消', 'deleted' => '已删除',
    ];
    return $map[$status] ?? $status;
}

/** 上游 domainstatus 映射到本地状态 */
function map_upstream_status(string $ds): string {
    $ds = strtolower($ds);
    if (in_array($ds, ['active'])) return 'active';
    if (in_array($ds, ['suspended'])) return 'suspended';
    if (in_array($ds, ['pending'])) return 'pending';
    if (in_array($ds, ['cancelled', 'terminated'])) return 'cancelled';
    if (in_array($ds, ['deleted'])) return 'deleted';
    return 'active';
}

function enc_data(string $s): string { return Crypto::encrypt($s); }
function dec_data(string $s): string { return Crypto::decrypt($s); }

/** 简单分页 HTML */
function paginate(int $total, int $page, int $per, string $baseUrl): string {
    $pages = max(1, (int)ceil($total / $per));
    if ($pages <= 1) return '';
    $page = max(1, min($page, $pages));
    $sep = strpos($baseUrl, '?') === false ? '?' : '&';
    $html = '<div class="pagination">';
    if ($page > 1) $html .= '<a href="' . e($baseUrl . $sep . 'page=' . ($page - 1)) . '">上一页</a>';
    $start = max(1, $page - 4); $end = min($pages, $page + 4);
    for ($i = $start; $i <= $end; $i++) {
        $html .= $i === $page
            ? '<span class="current">' . $i . '</span>'
            : '<a href="' . e($baseUrl . $sep . 'page=' . $i) . '">' . $i . '</a>';
    }
    if ($page < $pages) $html .= '<a href="' . e($baseUrl . $sep . 'page=' . ($page + 1)) . '">下一页</a>';
    return $html . '</div>';
}

/* ================= v1.1 新增：邮件 / 优惠券 / 客服 ================= */

/** 创建邮件令牌并返回 token */
function email_token_create(int $userId, string $type, int $ttlSeconds = 3600, string $data = ''): string
{
    $token = bin2hex(random_bytes(32));
    DB::insert('email_tokens', [
        'user_id' => $userId,
        'type' => $type,
        'token' => $token,
        'data' => $data === '' ? null : $data,
        'expires_at' => date('Y-m-d H:i:s', time() + $ttlSeconds),
    ]);
    return $token;
}

/** 校验并消费邮件令牌，成功返回 token 行 */
function email_token_consume(string $token, string $type): ?array
{
    $row = DB::get(
        "SELECT * FROM `email_tokens` WHERE `token` = ? AND `type` = ? LIMIT 1",
        [$token, $type]
    );
    if (!$row || $row['used_at'] || strtotime($row['expires_at']) < time()) {
        return null;
    }
    DB::update('email_tokens', ['used_at' => date('Y-m-d H:i:s')], '`id` = :id', ['id' => $row['id']]);
    return $row;
}

/** 站点绝对地址（用于邮件中的链接） */
function site_base_url(): string
{
    $url = rtrim(setting('site_url', ''), '/');
    if ($url !== '') return $url;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/** 发送账号激活邮件 */
function send_activate_mail(array $user): array
{
    $token = email_token_create((int)$user['id'], 'activate', 86400);
    $link = site_base_url() . '/activate?token=' . $token;
    $site = setting('site_name', '本站');
    $html = "<p>您好，{$user['username']}：</p>"
        . "<p>感谢注册 {$site}，请点击以下链接激活您的账号（24 小时内有效）：</p>"
        . "<p><a href=\"{$link}\">{$link}</a></p>"
        . "<p>若非本人操作，请忽略此邮件。</p>";
    return \App\Core\Mailer::quick($user['email'], "【{$site}】账号激活", $html);
}

/** 发送密码重置邮件 */
function send_reset_mail(array $user): array
{
    $token = email_token_create((int)$user['id'], 'reset', 3600);
    $link = site_base_url() . '/reset-password?token=' . $token;
    $site = setting('site_name', '本站');
    $html = "<p>您好，{$user['username']}：</p>"
        . "<p>您申请了重置密码，请点击以下链接设置新密码（1 小时内有效）：</p>"
        . "<p><a href=\"{$link}\">{$link}</a></p>"
        . "<p>若非本人操作，请忽略此邮件，账号不会受到影响。</p>";
    return \App\Core\Mailer::quick($user['email'], "【{$site}】密码重置", $html);
}

/** 发送换绑邮箱确认邮件（发往新邮箱，点击后才生效） */
function send_change_email_mail(array $user, string $newEmail): array
{
    $token = email_token_create((int)$user['id'], 'change_email', 86400, $newEmail);
    $link = site_base_url() . '/verify-email?token=' . $token;
    $site = setting('site_name', '本站');
    $html = "<p>您好，{$user['username']}：</p>"
        . "<p>您在 {$site} 申请将登录邮箱更换为本邮箱，请点击以下链接确认（24 小时内有效）：</p>"
        . "<p><a href=\"{$link}\">{$link}</a></p>"
        . "<p>若非本人操作，请忽略此邮件，原邮箱不会受到影响。</p>";
    return \App\Core\Mailer::quick($newEmail, "【{$site}】确认更换邮箱", $html);
}

/**
 * 校验优惠券是否可用
 * @return array ['ok'=>bool,'msg'=>string,'coupon'=>?array,'discount'=>float]
 */
function coupon_validate(string $code, int $userId, float $amount): array
{
    $code = strtoupper(trim($code));
    if ($code === '') return ['ok' => false, 'msg' => '请输入优惠券码'];
    $c = DB::get("SELECT * FROM `coupons` WHERE `code` = ? LIMIT 1", [$code]);
    if (!$c || (int)$c['status'] !== 1) return ['ok' => false, 'msg' => '优惠券不存在或已停用'];
    $now = time();
    if ($c['starts_at'] && strtotime($c['starts_at']) > $now) return ['ok' => false, 'msg' => '优惠券尚未生效'];
    if ($c['ends_at'] && strtotime($c['ends_at']) < $now) return ['ok' => false, 'msg' => '优惠券已过期'];
    if ((int)$c['max_uses'] > 0 && (int)$c['used_count'] >= (int)$c['max_uses']) return ['ok' => false, 'msg' => '优惠券已被领完'];
    if ($amount < (float)$c['min_amount']) return ['ok' => false, 'msg' => '订单金额未达到优惠券使用门槛'];
    $usedByUser = DB::count(
        "SELECT COUNT(*) FROM `coupon_usages` WHERE `coupon_id` = ? AND `user_id` = ?",
        [(int)$c['id'], $userId]
    );
    if ($usedByUser >= (int)$c['per_user_limit']) return ['ok' => false, 'msg' => '您已使用过该优惠券'];

    if ($c['type'] === 'percent') {
        // value 为折扣率，如 85 = 85折
        $discount = round($amount * (100 - (float)$c['value']) / 100, 2);
    } else {
        $discount = min($amount, (float)$c['value']);
    }
    $discount = max(0, round($discount, 2));
    return ['ok' => true, 'msg' => 'ok', 'coupon' => $c, 'discount' => $discount];
}

/** 客服机器人：基于 FAQ 关键词的简单问答 */
function chatbot_reply(string $question): string
{
    $q = mb_strtolower(trim($question));
    if ($q === '') return '您好，我是智能客服助手，请问有什么可以帮您？';
    $faqs = DB::all("SELECT `question`,`answer` FROM `faqs` WHERE `status` = 1 ORDER BY `sort` DESC, `id` ASC LIMIT 50");
    $best = null; $bestScore = 0;
    // 分词：中文按二元切分，英文/数字按词切分
    $tokens = [];
    preg_match_all('/[\x{4e00}-\x{9fa5}]+|[a-z0-9]+/u', $q, $m);
    foreach ($m[0] as $seg) {
        if (preg_match('/^[\x{4e00}-\x{9fa5}]+$/u', $seg)) {
            $len = mb_strlen($seg);
            if ($len === 1) { $tokens[] = $seg; }
            else { for ($i = 0; $i < $len - 1; $i++) $tokens[] = mb_substr($seg, $i, 2); }
        } else {
            if (strlen($seg) >= 3) $tokens[] = $seg;
        }
    }
    $tokens = array_unique($tokens);
    foreach ($faqs as $f) {
        $score = 0;
        $text = mb_strtolower($f['question'] . ' ' . $f['answer']);
        foreach ($tokens as $kw) {
            if (mb_strpos($text, $kw) !== false) $score += mb_strlen($kw);
        }
        if ($score > $bestScore) { $bestScore = $score; $best = $f; }
    }
    if ($best && $bestScore >= 2) {
        return $best['answer'] . "\n\n（以上为自动回复，如需人工帮助可点击「转人工」）";
    }
    $fallbacks = [
        '您好，我是智能客服助手。您可以问我：如何注册、忘记密码、如何购买、支付方式、如何续费等问题。',
        '抱歉，我暂时无法理解您的问题。您可以换个问法，或点击「转人工」由客服为您解答。',
    ];
    return $fallbacks[array_rand($fallbacks)];
}
