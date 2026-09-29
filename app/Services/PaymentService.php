<?php
namespace App\Services;

use App\Core\DB;
use App\Core\Logger;

/**
 * 支付服务：余额 + 易支付（支付宝/微信/QQ）
 *
 * 易支付为国内常用的聚合支付网关，商户在易支付平台申请 pid/key 后填入系统设置即可使用。
 * 协议：submit.php 下单 / 异步 notify 验证 MD5 签名。
 */
class PaymentService
{
    /** 余额变动（带事务行锁） */
    public static function addBalance(int $userId, float $amount, string $type, string $remark): float
    {
        DB::beginTransaction();
        try {
            $user = DB::get("SELECT * FROM `users` WHERE `id` = ? FOR UPDATE", [$userId]);
            if (!$user) {
                throw new \RuntimeException('用户不存在');
            }
            $new = round((float)$user['balance'] + $amount, 2);
            if ($new < 0) {
                throw new \RuntimeException('余额不足');
            }
            DB::update('users', ['balance' => $new], '`id` = :id', ['id' => $userId]);
            DB::insert('transactions', [
                'user_id' => $userId,
                'amount' => $amount,
                'balance_after' => $new,
                'type' => $type,
                'remark' => $remark,
            ]);
            DB::commit();
            return $new;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /** 余额支付账单 */
    public static function payWithBalance(int $billId, int $userId): array
    {
        $bill = DB::get("SELECT * FROM `bills` WHERE `id` = ? LIMIT 1", [$billId]);
        if (!$bill || (int)$bill['user_id'] !== $userId) {
            return ['ok' => false, 'msg' => '账单不存在'];
        }
        if ($bill['status'] === 'paid') {
            return ['ok' => true, 'msg' => '账单已支付'];
        }
        try {
            self::addBalance($userId, - (float)$bill['amount'], 'pay', '支付账单 ' . $bill['bill_no']);
        } catch (\Throwable $e) {
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
        return OrderService::markBillPaid($billId, 'balance');
    }

    /** 创建充值账单 */
    public static function createRechargeBill(int $userId, float $amount): array
    {
        $amount = round($amount, 2);
        if ($amount < 0.01) {
            return ['ok' => false, 'msg' => '充值金额无效'];
        }
        $billId = DB::insert('bills', [
            'bill_no' => gen_no('B'),
            'user_id' => $userId,
            'type' => 'recharge',
            'title' => '账户充值',
            'amount' => $amount,
            'status' => 'unpaid',
        ]);
        $bill = DB::get("SELECT * FROM `bills` WHERE `id` = ? LIMIT 1", [$billId]);
        return ['ok' => true, 'bill_id' => (int)$billId, 'bill_no' => $bill['bill_no'], 'amount' => $amount];
    }

    // ---------------- 易支付网关 ----------------

    public static function epayEnabled(): bool
    {
        return (bool) setting('epay_url') && (bool) setting('epay_pid') && (bool) setting('epay_key');
    }

    public static function epayTypes(): array
    {
        $map = ['alipay' => '支付宝', 'wxpay' => '微信支付', 'qqpay' => 'QQ钱包', 'jdpay' => '京东支付'];
        $enabled = array_filter(array_map('trim', explode(',', (string)setting('epay_types', 'alipay,wxpay'))));
        $out = [];
        foreach ($enabled as $t) {
            if (isset($map[$t])) $out[$t] = $map[$t];
        }
        return $out;
    }

    /** 生成签名 */
    public static function epaySign(array $params): string
    {
        unset($params['sign'], $params['sign_type']);
        ksort($params);
        $str = urldecode(http_build_query($params)) . setting('epay_key');
        return md5($str);
    }

    /** 构建跳转到易支付的自动提交表单 HTML */
    public static function epaySubmitForm(array $bill, string $type): string
    {
        $params = [
            'pid' => setting('epay_pid'),
            'type' => $type,
            'out_trade_no' => $bill['bill_no'],
            'notify_url' => rtrim(setting('site_url'), '/') . '/pay/notify/epay',
            'return_url' => rtrim(setting('site_url'), '/') . '/pay/return/' . $bill['bill_no'],
            'name' => $bill['title'],
            'money' => number_format((float)$bill['amount'], 2, '.', ''),
            'sign_type' => 'MD5',
        ];
        $params['sign'] = self::epaySign($params);
        $html = '<form id="epaysubmit" action="' . e(rtrim(setting('epay_url'), '/') . '/submit.php') . '" method="post">';
        foreach ($params as $k => $v) {
            $html .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '"/>';
        }
        $html .= '</form><script>document.getElementById("epaysubmit").submit();</script>';
        return $html;
    }

    /**
     * 易支付异步通知处理（幂等）
     * @return string 返回 'success' 或 'fail' 给网关
     */
    public static function epayNotify(array $params): string
    {
        $sign = $params['sign'] ?? '';
        if (!$sign || !hash_equals(self::epaySign($params), $sign)) {
            Logger::log('epay notify sign invalid: ' . json_encode($params, JSON_UNESCAPED_UNICODE));
            return 'fail';
        }
        if (($params['trade_status'] ?? '') !== 'TRADE_SUCCESS') {
            return 'fail';
        }
        $billNo = $params['out_trade_no'] ?? '';
        $bill = DB::get("SELECT * FROM `bills` WHERE `bill_no` = ? LIMIT 1", [$billNo]);
        if (!$bill) {
            return 'fail';
        }
        if ($bill['status'] === 'paid') {
            return 'success';
        }
        // 金额校验
        if (abs((float)($params['money'] ?? 0) - (float)$bill['amount']) > 0.01) {
            Logger::log("epay amount mismatch: {$billNo}");
            return 'fail';
        }
        $ret = OrderService::markBillPaid((int)$bill['id'], 'epay', $params['trade_no'] ?? '');
        return $ret['ok'] ? 'success' : 'fail';
    }
}
