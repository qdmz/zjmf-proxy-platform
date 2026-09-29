<?php
namespace App\Services;

use App\Core\DB;

/**
 * 计费服务：加价策略 + 本地报价计算
 */
class BillingService
{
    /**
     * 应用加价
     * @param float $upstreamPrice 上游价
     * @param string $type percent|fixed
     * @param float $value percent: 120 表示加价20%；fixed: 直接加金额
     */
    public static function applyMarkup(float $upstreamPrice, string $type, float $value): float
    {
        if ($upstreamPrice <= 0) {
            return 0.00;
        }
        $ret = $type === 'fixed'
            ? $upstreamPrice + $value
            : $upstreamPrice * $value / 100;
        return round($ret, 2);
    }

    /**
     * 本地报价：产品周期价 + 配置项价格
     * @param int $productId
     * @param string $cycle
     * @param array $configoption [本地option_id => 本地sub_id 或 数量]
     * @return array ['ok'=>bool,'msg'=>string,'total'=>float,'detail'=>array]
     */
    public static function quote(int $productId, string $cycle, array $configoption = []): array
    {
        $product = DB::get("SELECT * FROM `products` WHERE `id` = ? LIMIT 1", [$productId]);
        if (!$product || (int)$product['status'] !== 1) {
            return ['ok' => false, 'msg' => '产品不存在或已下架'];
        }
        $price = DB::get(
            "SELECT * FROM `product_prices` WHERE `product_id` = ? AND `billingcycle` = ? LIMIT 1",
            [$productId, $cycle]
        );
        if (!$price) {
            return ['ok' => false, 'msg' => '该产品不支持所选周期'];
        }
        $salePrice = (float)$price['sale_price'] > 0 ? (float)$price['sale_price'] : (float)$price['price'];
        $total = $salePrice + (float)$price['setup_fee'];
        $detail = [
            ['name' => '产品费用(' . cycle_name($cycle) . ')', 'amount' => $salePrice],
        ];
        if ((float)$price['setup_fee'] > 0) {
            $detail[] = ['name' => '安装费', 'amount' => (float)$price['setup_fee']];
        }

        // 配置项费用
        foreach ($configoption as $optionId => $val) {
            $opt = DB::get(
                "SELECT * FROM `product_config_options` WHERE `id` = ? AND `product_id` = ? LIMIT 1",
                [$optionId, $productId]
            );
            if (!$opt) continue;
            $type = (int)$opt['option_type'];
            if (in_array($type, [4, 7, 9, 11, 14], true)) {
                // 数量型：取该选项第一个子项单价 × 数量
                $qty = max((int)$opt['qty_min'], min((int)$opt['qty_max'], (int)$val));
                $sub = DB::get("SELECT * FROM `product_config_subs` WHERE `option_id` = ? ORDER BY `sort`,`id` LIMIT 1", [$opt['id']]);
                if ($sub) {
                    $pj = json_decode($sub['price_json'] ?? '{}', true) ?: [];
                    $unit = (float)($pj[$cycle] ?? $pj['*'] ?? 0);
                    $amt = round($unit * $qty, 2);
                    if ($amt > 0) {
                        $total += $amt;
                        $detail[] = ['name' => $opt['name'] . ' × ' . $qty . $opt['unit'], 'amount' => $amt];
                    }
                }
            } elseif ($type === 3) {
                // 是否型
                if ($val) {
                    $sub = DB::get("SELECT * FROM `product_config_subs` WHERE `option_id` = ? ORDER BY `sort`,`id` LIMIT 1", [$opt['id']]);
                    if ($sub) {
                        $pj = json_decode($sub['price_json'] ?? '{}', true) ?: [];
                        $amt = (float)($pj[$cycle] ?? $pj['*'] ?? 0);
                        if ($amt > 0) { $total += $amt; $detail[] = ['name' => $opt['name'], 'amount' => $amt]; }
                    }
                }
            } else {
                // 选择型
                $sub = DB::get(
                    "SELECT * FROM `product_config_subs` WHERE `id` = ? AND `option_id` = ? LIMIT 1",
                    [$val, $opt['id']]
                );
                if ($sub) {
                    $pj = json_decode($sub['price_json'] ?? '{}', true) ?: [];
                    $amt = (float)($pj[$cycle] ?? $pj['*'] ?? 0);
                    if ($amt > 0) { $total += $amt; $detail[] = ['name' => $opt['name'] . ': ' . $sub['option_name'], 'amount' => $amt]; }
                }
            }
        }

        return ['ok' => true, 'total' => round($total, 2), 'detail' => $detail];
    }

    /**
     * 将本地配置项选择转换为上游接口格式 [上游option_id => 上游sub_id|数量]
     */
    public static function toUpstreamConfig(int $productId, array $configoption): array
    {
        $out = [];
        foreach ($configoption as $optionId => $val) {
            $opt = DB::get(
                "SELECT * FROM `product_config_options` WHERE `id` = ? AND `product_id` = ? LIMIT 1",
                [$optionId, $productId]
            );
            if (!$opt) continue;
            $uoid = (int)$opt['upstream_option_id'];
            $type = (int)$opt['option_type'];
            if (in_array($type, [4, 7, 9, 11, 14], true)) {
                $out[$uoid] = max((int)$opt['qty_min'], min((int)$opt['qty_max'], (int)$val));
            } elseif ($type === 3) {
                if ($val) {
                    $sub = DB::get("SELECT * FROM `product_config_subs` WHERE `option_id` = ? ORDER BY `sort`,`id` LIMIT 1", [$opt['id']]);
                    if ($sub) $out[$uoid] = (int)$sub['upstream_sub_id'];
                }
            } else {
                $sub = DB::get("SELECT * FROM `product_config_subs` WHERE `id` = ? AND `option_id` = ? LIMIT 1", [$val, $opt['id']]);
                if ($sub) $out[$uoid] = (int)$sub['upstream_sub_id'];
            }
        }
        return $out;
    }
}
