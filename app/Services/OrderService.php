<?php
namespace App\Services;

use App\Core\DB;
use App\Core\Logger;

/**
 * 订单服务：创建订单 → 支付 → 上游自动开通
 *
 * 开通流程（智简魔方 v1 API）：
 *  1. POST /v1/cart/products      加入购物车
 *  2. POST /v1/cart/checkout      结算（payment=credit），返回 invoiceid；
 *     免费订单直接返回 status=1001 + hostid（已开通）
 *  3. POST /v1/invoices/{id}/fund 余额支付，返回 status=1001 + hostid（已开通）
 *  4. GET  /v1/hosts/{hostid}     拉取实例详情回写本地
 *
 * 注意：上游余额支付消耗的是「上游供货商账号」的余额，
 * 管理员需定期在上游面板为 API 账号充值，本平台用户支付的款项进入平台账户。
 */
class OrderService
{
    /**
     * 创建新购订单 + 账单
     * @return array ['ok'=>bool,'msg'=>string,'bill_id'=>int,'bill_no'=>string,'amount'=>float]
     */
    public static function createOrder(
        int $userId,
        int $productId,
        string $cycle,
        int $qty,
        array $configoption,
        array $customfield,
        string $host,
        string $password,
        string $couponCode = ''
    ): array {
        $product = DB::get("SELECT * FROM `products` WHERE `id` = ? LIMIT 1", [$productId]);
        if (!$product || (int)$product['status'] !== 1) {
            return ['ok' => false, 'msg' => '产品不存在或已下架'];
        }
        if ($product['stock_control'] && (int)$product['stock_qty'] < $qty) {
            return ['ok' => false, 'msg' => '库存不足'];
        }
        $quote = BillingService::quote($productId, $cycle, $configoption);
        if (!$quote['ok']) {
            return $quote;
        }
        $amount = round($quote['total'] * max(1, $qty), 2);

        // 优惠券抵扣
        $couponId = 0;
        $discountAmount = 0.0;
        $couponCode = trim($couponCode);
        if ($couponCode !== '') {
            $cv = coupon_validate($couponCode, $userId, $amount);
            if (!$cv['ok']) {
                return ['ok' => false, 'msg' => $cv['msg']];
            }
            $couponId = (int)$cv['coupon']['id'];
            $discountAmount = (float)$cv['discount'];
            $amount = round(max(0, $amount - $discountAmount), 2);
        }

        // 配置快照（本地 id 映射 + 上游 id 映射）
        $snapshot = [
            'product_name' => $product['name'],
            'upstream_pid' => (int)$product['upstream_pid'],
            'billingcycle' => $cycle,
            'qty' => $qty,
            'host' => $host,
            'configoption_local' => $configoption,
            'configoption_upstream' => BillingService::toUpstreamConfig($productId, $configoption),
            'customfield' => $customfield,
            'price_detail' => $quote['detail'],
        ];

        DB::beginTransaction();
        try {
            $orderNo = gen_no('O');
            $orderId = DB::insert('orders', [
                'order_no' => $orderNo,
                'user_id' => $userId,
                'type' => 'new',
                'product_id' => $productId,
                'billingcycle' => $cycle,
                'qty' => max(1, $qty),
                'config_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                'amount' => $amount,
                'coupon_id' => $couponId,
                'discount_amount' => $discountAmount,
                'status' => 'pending',
            ]);
            if ($couponId > 0) {
                DB::insert('coupon_usages', [
                    'coupon_id' => $couponId,
                    'user_id' => $userId,
                    'order_id' => $orderId,
                    'discount_amount' => $discountAmount,
                ]);
                DB::query("UPDATE `coupons` SET `used_count` = `used_count` + 1 WHERE `id` = ?", [$couponId]);
            }
            $billNo = gen_no('B');
            $billId = DB::insert('bills', [
                'bill_no' => $billNo,
                'user_id' => $userId,
                'order_id' => $orderId,
                'type' => 'order',
                'title' => '购买 ' . $product['name'] . ' (' . cycle_name($cycle) . ')',
                'amount' => $amount,
                'status' => 'unpaid',
            ]);
            if ($product['stock_control']) {
                DB::query(
                    "UPDATE `products` SET `stock_qty` = GREATEST(0, `stock_qty` - ?) WHERE `id` = ?",
                    [$qty, $productId]
                );
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Logger::log('create order failed: ' . $e->getMessage());
            return ['ok' => false, 'msg' => '订单创建失败，请重试'];
        }

        return ['ok' => true, 'msg' => '订单创建成功', 'bill_id' => (int)$billId, 'bill_no' => $billNo, 'amount' => $amount];
    }

    /** 账单支付成功后的统一处理（幂等）：标记已付 → 开通/续费 */
    public static function markBillPaid(int $billId, string $payment, string $tradeNo = ''): array
    {
        $bill = DB::get("SELECT * FROM `bills` WHERE `id` = ? LIMIT 1", [$billId]);
        if (!$bill) {
            return ['ok' => false, 'msg' => '账单不存在'];
        }
        if ($bill['status'] === 'paid') {
            return ['ok' => true, 'msg' => '账单已处理'];
        }
        if ($bill['status'] !== 'unpaid') {
            return ['ok' => false, 'msg' => '账单状态异常，无法支付'];
        }
        DB::beginTransaction();
        try {
            DB::update('bills', [
                'status' => 'paid',
                'payment' => $payment,
                'trade_no' => $tradeNo,
                'paid_at' => date('Y-m-d H:i:s'),
            ], '`id` = :id AND `status` = :st', ['id' => $billId, 'st' => 'unpaid']);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return ['ok' => false, 'msg' => '账单状态更新失败'];
        }

        $bill = DB::get("SELECT * FROM `bills` WHERE `id` = ? LIMIT 1", [$billId]);
        if ($bill['type'] === 'recharge') {
            // 充值：直接入账
            PaymentService::addBalance((int)$bill['user_id'], (float)$bill['amount'], 'recharge', '在线充值，账单 ' . $bill['bill_no']);
            return ['ok' => true, 'msg' => '充值成功'];
        }

        $order = DB::get("SELECT * FROM `orders` WHERE `id` = ? LIMIT 1", [(int)$bill['order_id']]);
        if (!$order) {
            return ['ok' => false, 'msg' => '关联订单不存在'];
        }
        DB::update('orders', ['status' => 'paid', 'paid_at' => date('Y-m-d H:i:s')], '`id` = :id', ['id' => $order['id']]);

        if ($order['type'] === 'new') {
            return self::provision((int)$order['id']);
        }
        if ($order['type'] === 'renew') {
            return self::doRenew((int)$order['id']);
        }
        return ['ok' => true, 'msg' => '支付成功'];
    }

    /**
     * 向上游下单并开通（同步执行，可能耗时 10~60 秒）
     */
    public static function provision(int $orderId): array
    {
        $order = DB::get("SELECT * FROM `orders` WHERE `id` = ? LIMIT 1", [$orderId]);
        if (!$order || $order['status'] !== 'paid') {
            return ['ok' => false, 'msg' => '订单状态异常'];
        }
        // 已开通则直接返回（幂等）
        if ((int)$order['upstream_host_id'] > 0) {
            return ['ok' => true, 'msg' => '已开通'];
        }
        $product = DB::get("SELECT * FROM `products` WHERE `id` = ? LIMIT 1", [(int)$order['product_id']]);
        $provider = UpstreamService::get((int)$product['provider_id']);
        if (!$provider || !(int)$provider['status']) {
            return self::provisionFailed($orderId, '上游供货商不可用');
        }
        $client = UpstreamService::client($provider);
        $snap = json_decode($order['config_snapshot'] ?? '{}', true) ?: [];

        // 先创建本地主机占位记录（重试时复用已有占位，避免重复创建）
        $hostId = (int)$order['host_id'];
        if ($hostId <= 0) {
            $hostId = DB::insert('hosts', [
                'user_id' => (int)$order['user_id'],
                'product_id' => (int)$order['product_id'],
                'provider_id' => (int)$provider['id'],
                'domain' => $snap['host'] ?? '',
                'status' => 'pending',
                'billingcycle' => $order['billingcycle'],
                'amount' => $order['amount'],
                'config_snapshot' => $order['config_snapshot'],
            ]);
            DB::update('orders', ['host_id' => $hostId], '`id` = :id', ['id' => $orderId]);
        }

        try {
            // 1. 加入购物车
            $cartParams = [
                'product_id' => (int)$product['upstream_pid'],
                'billingcycle' => $order['billingcycle'],
                'qty' => max(1, (int)$order['qty']),
                'host' => $snap['host'] ?? ('host' . $hostId),
                'password' => self::makePassword($hostId),
                'configoption' => $snap['configoption_upstream'] ?? [],
                'customfield' => $snap['customfield'] ?? [],
            ];
            $r1 = $client->cartAdd($cartParams);
            Logger::upstream((int)$provider['id'], (int)$hostId, 'cart_add', $cartParams, $r1, $client->ok($r1));
            if (!$client->ok($r1)) {
                throw new \RuntimeException('上游加购失败: ' . ($r1['msg'] ?? '未知错误'));
            }

            // 2. 结算（支付方式可在供货商配置中调整）
            $r2 = $client->cartCheckout($provider['checkout_payment'] ?? 'credit', [0]);
            Logger::upstream((int)$provider['id'], (int)$hostId, 'cart_checkout', [], $r2, true);
            $status = (int)($r2['status'] ?? 0);
            $upstreamHostId = 0;
            $invoiceId = 0;
            if ($status === 1001) {
                // 免费/全额抵扣，已开通
                $hids = (array)($r2['data']['hostid'] ?? []);
                $upstreamHostId = (int)($hids[0] ?? 0);
            } elseif ($status === 200) {
                $invoiceId = (int)(($r2['data']['invoiceid'] ?? 0));
                if ($invoiceId <= 0) {
                    throw new \RuntimeException('上游未返回账单号');
                }
                DB::update('orders', ['upstream_invoice_id' => $invoiceId], '`id` = :id', ['id' => $orderId]);
                // 3. 余额支付上游账单
                $r3 = $client->invoiceFund($invoiceId);
                Logger::upstream((int)$provider['id'], (int)$hostId, 'invoice_fund', ['invoiceid' => $invoiceId], $r3, (int)($r3['status'] ?? 0) === 1001);
                if ((int)($r3['status'] ?? 0) === 1001) {
                    $hids = (array)($r3['data']['hostid'] ?? []);
                    $upstreamHostId = (int)($hids[0] ?? 0);
                } else {
                    throw new \RuntimeException('上游余额支付失败: ' . ($r3['msg'] ?? '未知错误') . '（请检查上游账号余额）');
                }
            } else {
                throw new \RuntimeException('上游结算失败: ' . ($r2['msg'] ?? '未知错误'));
            }

            if ($upstreamHostId <= 0) {
                throw new \RuntimeException('上游未返回实例 ID');
            }

            // 4. 拉取实例详情回写
            DB::update('orders', ['upstream_host_id' => $upstreamHostId, 'status' => 'active'], '`id` = :id', ['id' => $orderId]);
            DB::update('hosts', ['upstream_host_id' => $upstreamHostId], '`id` = :id', ['id' => $hostId]);
            DB::update('bills', ['host_id' => $hostId, 'upstream_invoice_id' => $invoiceId], '`order_id` = :oid', ['oid' => $orderId]);
            HostService::syncFromUpstream((int)$hostId);

            // 站内通知
            DB::insert('messages', [
                'user_id' => (int)$order['user_id'],
                'title' => '服务器开通成功',
                'content' => "您的 {$product['name']} 已开通，主机名：" . ($snap['host'] ?? '') . "，请前往控制台查看管理信息。",
            ]);
            return ['ok' => true, 'msg' => '开通成功', 'host_id' => (int)$hostId];
        } catch (\Throwable $e) {
            return self::provisionFailed($orderId, $e->getMessage(), (int)$hostId);
        }
    }

    protected static function provisionFailed(int $orderId, string $reason, int $hostId = 0): array
    {
        DB::update('orders', ['status' => 'failed', 'fail_reason' => mb_substr($reason, 0, 250)], '`id` = :id', ['id' => $orderId]);
        if ($hostId > 0) {
            DB::update('hosts', ['status' => 'pending'], '`id` = :id', ['id' => $hostId]);
        }
        $order = DB::get("SELECT * FROM `orders` WHERE `id` = ? LIMIT 1", [$orderId]);
        $refunded = false;
        if ($order) {
            // 下单时预扣的库存还回去（仅新购订单）
            if ($order['type'] === 'new') {
                $p = DB::get("SELECT `stock_control` FROM `products` WHERE `id` = ? LIMIT 1", [(int)$order['product_id']]);
                if ($p && (int)$p['stock_control']) {
                    DB::query(
                        "UPDATE `products` SET `stock_qty` = `stock_qty` + ? WHERE `id` = ?",
                        [max(1, (int)$order['qty']), (int)$order['product_id']]
                    );
                }
            }
            $refunded = self::refundBillForOrder((int)$order['id'], $reason);
            DB::insert('messages', [
                'user_id' => (int)$order['user_id'],
                'title' => '服务器开通失败',
                'content' => "订单 {$order['order_no']} 自动开通失败：{$reason}。"
                    . ($refunded ? '款项已退回您的余额，可重新下单。' : '请联系客服处理或在后台重试开通。'),
            ]);
        }
        Logger::log("provision failed order #{$orderId}: {$reason}" . ($refunded ? ' (refunded)' : ''));
        return ['ok' => false, 'msg' => $reason . ($refunded ? '（已退款到余额）' : '')];
    }

    /**
     * 开通/续费失败时，将已支付账单退回用户余额（幂等：仅处理 status=paid 的账单）
     */
    /** 管理员手动退款（账单已支付时退回余额） */
    public static function adminRefund(int $orderId): array
    {
        $order = DB::get("SELECT * FROM `orders` WHERE `id` = ? LIMIT 1", [$orderId]);
        if (!$order) {
            return ['ok' => false, 'msg' => '订单不存在'];
        }
        $ok = self::refundBillForOrder($orderId, '管理员手动退款');
        if (!$ok) {
            return ['ok' => false, 'msg' => '退款失败：账单不是已支付状态或已退款'];
        }
        // 同步标记订单为失败（若还在待开通）
        if (in_array($order['status'], ['paid', 'pending'], true)) {
            DB::update('orders', ['status' => 'failed', 'fail_reason' => '管理员手动退款'], '`id` = :id', ['id' => $orderId]);
        }
        Logger::log("admin refund order #{$orderId}");
        return ['ok' => true, 'msg' => '已退款到用户余额'];
    }

    protected static function refundBillForOrder(int $orderId, string $reason): bool
    {
        $bill = DB::get(
            "SELECT * FROM `bills` WHERE `order_id` = ? AND `type` IN ('order','renew') ORDER BY `id` DESC LIMIT 1",
            [$orderId]
        );
        if (!$bill || $bill['status'] !== 'paid' || (float)$bill['amount'] <= 0) {
            return false;
        }
        try {
            PaymentService::addBalance(
                (int)$bill['user_id'],
                (float)$bill['amount'],
                'refund',
                '开通失败退款，账单 ' . $bill['bill_no'] . '：' . mb_substr($reason, 0, 60)
            );
        } catch (\Throwable $e) {
            Logger::log("refund failed bill #{$bill['id']}: " . $e->getMessage());
            return false;
        }
        $n = DB::query(
            "UPDATE `bills` SET `status` = 'refunded' WHERE `id` = ? AND `status` = 'paid'",
            [(int)$bill['id']]
        )->rowCount();
        return $n > 0;
    }

    /** 生成主机初始密码并加密存储快照 */
    protected static function makePassword(int $hostId): string
    {
        $pwd = 'A' . substr(str_shuffle('abcdefghjkmnpqrstuvwxyz'), 0, 3)
            . substr(str_shuffle('ABCDEFGHJKMNPQRSTUVWXYZ'), 0, 3)
            . substr(str_shuffle('23456789'), 0, 4) . '!';
        DB::update('hosts', ['password_enc' => enc_data($pwd)], '`id` = :id', ['id' => $hostId]);
        return $pwd;
    }

    /**
     * 创建续费订单 + 账单
     */
    public static function createRenewOrder(int $userId, int $hostId, string $cycle): array
    {
        $host = DB::get("SELECT h.*, p.name AS product_name FROM `hosts` h LEFT JOIN `products` p ON p.id=h.product_id WHERE h.`id` = ? LIMIT 1", [$hostId]);
        if (!$host || (int)$host['user_id'] !== $userId) {
            return ['ok' => false, 'msg' => '实例不存在'];
        }
        if (!in_array($host['status'], ['active', 'suspended'], true)) {
            return ['ok' => false, 'msg' => '当前状态不允许续费'];
        }
        $price = DB::get(
            "SELECT * FROM `product_prices` WHERE `product_id` = ? AND `billingcycle` = ? LIMIT 1",
            [(int)$host['product_id'], $cycle]
        );
        if (!$price) {
            return ['ok' => false, 'msg' => '该产品不支持所选续费周期'];
        }
        $amount = (float)$price['sale_price'] > 0 ? (float)$price['sale_price'] : (float)$price['price'];
        DB::beginTransaction();
        try {
            $orderId = DB::insert('orders', [
                'order_no' => gen_no('O'),
                'user_id' => $userId,
                'type' => 'renew',
                'product_id' => (int)$host['product_id'],
                'host_id' => $hostId,
                'billingcycle' => $cycle,
                'qty' => 1,
                'config_snapshot' => json_encode(['product_name' => $host['product_name'], 'billingcycle' => $cycle], JSON_UNESCAPED_UNICODE),
                'amount' => $amount,
                'coupon_id' => $couponId,
                'discount_amount' => $discountAmount,
                'status' => 'pending',
            ]);
            $billId = DB::insert('bills', [
                'bill_no' => gen_no('B'),
                'user_id' => $userId,
                'order_id' => $orderId,
                'host_id' => $hostId,
                'type' => 'renew',
                'title' => '续费 ' . $host['product_name'] . ' (' . cycle_name($cycle) . ')',
                'amount' => $amount,
                'status' => 'unpaid',
            ]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return ['ok' => false, 'msg' => '续费订单创建失败'];
        }
        $bill = DB::get("SELECT * FROM `bills` WHERE `id` = ? LIMIT 1", [$billId]);
        return ['ok' => true, 'bill_id' => (int)$billId, 'bill_no' => $bill['bill_no'], 'amount' => $amount];
    }

    /** 执行续费：上游生成续费账单 → 余额支付 → 同步到期时间 */
    public static function doRenew(int $orderId): array
    {
        $order = DB::get("SELECT * FROM `orders` WHERE `id` = ? LIMIT 1", [$orderId]);
        if (!$order || $order['type'] !== 'renew') {
            return ['ok' => false, 'msg' => '续费订单异常'];
        }
        $host = DB::get("SELECT * FROM `hosts` WHERE `id` = ? LIMIT 1", [(int)$order['host_id']]);
        if (!$host || (int)$host['upstream_host_id'] <= 0) {
            return ['ok' => false, 'msg' => '实例未关联上游'];
        }
        $provider = UpstreamService::get((int)$host['provider_id']);
        $client = UpstreamService::client($provider);
        try {
            $r1 = $client->renew((int)$host['upstream_host_id'], $order['billingcycle']);
            Logger::upstream((int)$provider['id'], (int)$host['id'], 'renew', ['cycle' => $order['billingcycle']], $r1, $client->ok($r1));
            if (!$client->ok($r1)) {
                throw new \RuntimeException('上游续费下单失败: ' . ($r1['msg'] ?? '未知错误'));
            }
            $invoiceId = (int)(($r1['data']['invoiceid'] ?? 0));
            if ($invoiceId > 0) {
                $r2 = $client->invoiceFund($invoiceId);
                Logger::upstream((int)$provider['id'], (int)$host['id'], 'renew_fund', ['invoiceid' => $invoiceId], $r2, (int)($r2['status'] ?? 0) === 1001);
                if ((int)($r2['status'] ?? 0) !== 1001) {
                    throw new \RuntimeException('上游续费支付失败: ' . ($r2['msg'] ?? '未知错误'));
                }
            }
            HostService::syncFromUpstream((int)$host['id']);
            DB::insert('messages', [
                'user_id' => (int)$order['user_id'],
                'title' => '续费成功',
                'content' => "实例 {$host['domain']} 已续费至 " . (DB::get("SELECT `nextduedate` FROM `hosts` WHERE `id`=?", [$host['id']])['nextduedate'] ?? ''),
            ]);
            return ['ok' => true, 'msg' => '续费成功'];
        } catch (\Throwable $e) {
            Logger::log("renew failed order #{$orderId}: " . $e->getMessage());
            DB::update('orders', ['status' => 'failed', 'fail_reason' => mb_substr($e->getMessage(), 0, 250)], '`id` = :id', ['id' => $orderId]);
            $refunded = self::refundBillForOrder($orderId, $e->getMessage());
            $msg = $e->getMessage() . ($refunded ? '（已退款到余额）' : '');
            return ['ok' => false, 'msg' => $msg];
        }
    }

    /** 管理员手动重试开通 */
    public static function retryProvision(int $orderId): array
    {
        $order = DB::get("SELECT * FROM `orders` WHERE `id` = ? LIMIT 1", [$orderId]);
        if (!$order) {
            return ['ok' => false, 'msg' => '订单不存在'];
        }
        $bill = DB::get(
            "SELECT * FROM `bills` WHERE `order_id` = ? AND `type` IN ('order','renew') ORDER BY `id` DESC LIMIT 1",
            [$orderId]
        );
        if ($bill && $bill['status'] === 'refunded') {
            return ['ok' => false, 'msg' => '该订单已退款，如需开通请重新下单'];
        }
        DB::update('orders', ['status' => 'paid', 'fail_reason' => ''], '`id` = :id', ['id' => $orderId]);
        return self::provision($orderId);
    }
}
